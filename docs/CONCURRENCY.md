# Concurrency

How the bundle stays correct when two requests, two workers or a retried message write the same rows at the same time
(Nowo bundle requirement REQ-RUNTIME-003).
Applies from 1.10. Worker mode with `kernel.reset` off is covered in [FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md).

## Table of contents

- [Principles](#principles)
- [Write paths](#write-paths)
- [Conflict answers and codes](#conflict-answers-and-codes)
- [Host options](#host-options)
- [Custom repository adapters](#custom-repository-adapters)
- [Tests](#tests)
- [Residual risks](#residual-risks)

## Principles

- **Only the bundle's rows, never the host's entity manager.** Every write is a DBAL statement built from the entity
  metadata (`DbalEntityWriter`). The bundle never flushes, clears or resets the host's entity manager. A manager
  closed by an earlier failure is reset through the registry, and only when it is already closed.
- **One write per change, no read-modify-write of a whole row.**
  - A counter is incremented in SQL: `login_count = login_count + 1`, `observation_count = observation_count + 1`.
  - A revocation writes `revoked_at` alone.
  - An upsert is an `UPDATE` by id, or an `INSERT`. When a concurrent `INSERT` of the same key wins (a unique
    violation), the row it stored is found again with a locking read (`SELECT … FOR UPDATE`) and updated.
- **Exact keys.** Device and user identifiers are compared byte for byte after every SQL filter, so a folding
  collation never makes `Bob` match `bob` (REQ-SEC-006). A unique violation whose key is held by a row that only the
  folding filter matches is a look-alike: `LookAlikeIdentifierException`, and nothing is written (fail closed). A
  unique violation whose row is gone by the locking read (deleted meanwhile by an erasure or a cleanup) is not a
  look-alike: the `INSERT` is tried again, at most three times in all, then the unique violation is rethrown.
- **One transaction per write unit.** The analysis writes the device and its observation in one transaction, after
  the risk assessment, so no GeoIP, reputation or risk-rule call runs while a row is locked.
- **Savepoints inside the host's transaction.** Every bundle write inside an open host transaction runs in its own
  `SAVEPOINT`, rolled back alone on failure. On PostgreSQL the host transaction therefore stays usable, and on DBAL 3
  without nested savepoints the host transaction is not marked rollback-only.
- **Transient failures are never swallowed.** A deadlock, lock-wait timeout or serialization failure is rethrown. The
  analysis unit rethrows it as `Nowo\TransactionContract\TransientStorageFailure`, with nothing of the unit committed.

## Write paths

| Path | Transaction | Control (rule 4) | Conflict answer (rule 6) |
| --- | --- | --- | --- |
| Collect endpoint `POST /_device/collect` (`CollectController` → `DeviceIntelligence::analyze()`) | One unit: device + observation, through `DeviceIntelligenceTransactionRunner`. Its own transaction (transaction-doctrine's connection runner), or a savepoint inside the host's. | Matched device: `saveObserved()` (count +1 in SQL; status, first-seen time and metadata not written). New device: INSERT with a fresh ULID. Observation: INSERT with a fresh ULID. | **409** (or 503) JSON `{"error": "conflict", "code": "CONFLICT_001"}` + `X-Conflict-Code` + `Retry-After`, or `CONFLICT_002` without `Retry-After`. |
| Analysis on host routes (`#[DeviceRisk]`, `AnalyzeSubscriber`, a host calling `AnalyzeService` or `DeviceIntelligence`) | Same unit as above | Same as above | `TransientStorageFailure` is rethrown to the host, and the bundle does not answer for host routes. transaction-doctrine's responder (or the host's own) answers it. |
| Login link (`SecurityDeviceSubscriber` → `DeviceManager::associate()` → `recordLogin()`) | One statement (`UPDATE … login_count + 1`, or `INSERT`), in a savepoint inside the host's transaction | Unique `(device_id, user_identifier)` + catch → UPDATE. Two logins at once both count. | Rethrown to the host (rule 8). A look-alike is logged and the login continues (REQ-SEC-006). |
| Trust grant (`DeviceTrustService::trust()` → `DoctrineTrustedDeviceRepository::save()`) | One statement (UPDATE by id or INSERT), in a savepoint inside the host's transaction | The whole record is written, never merged with a row read earlier. Unique + catch → UPDATE, so a concurrent identical grant is not an error, and the last one wins. | Rethrown to the host |
| Trust revocation (`DeviceTrustService::revoke()` → `revoke()`) | One statement: `UPDATE … SET revoked_at`, or an INSERT of a revoked row | Only `revoked_at` is written. A grant or relabel committed meanwhile keeps its expiry and label, and the device is not trusted afterwards. | Rethrown to the host |
| Device-user `save()` (custom callers) | One statement | Upsert as for the trust grant | Rethrown |
| Stability recalculation (`RecalculateStabilityHandler`, `device-intelligence:recalculate`) | One `UPDATE … SET stability` per device | Only the stability is written: no count, last-seen or status reverted. The message can run twice, and the result is recomputed from the observations. | Rethrown, so Messenger retries it |
| Cleanup (`CleanupHandler`, `device-intelligence:cleanup`) | Keyset batches of `DELETE … WHERE id IN (…)`, one statement each | Idempotent: a batch deleted twice deletes nothing the second time | Rethrown (Messenger retry). Batches already deleted stay deleted. |
| GDPR erasure (`DeviceIntelligencePersonalDataEraser`) | Its own transaction, or the caller's (UserKit) | Deletes by exact user identifier. It can run twice. | Rethrown to the caller |

Read-only paths: `DeviceRequestSubscriber`, `ControllerAttributeSubscriber` (`#[RequireTrustedDevice]`), the exporter,
`device-intelligence:device:show`, `device-intelligence:user:devices`, `device-intelligence:stats`,
`device-intelligence:risk:test`.

## Conflict answers and codes

A **lock conflict** is a deadlock, a lock-wait timeout or a serialization failure: MySQL 1213 / 1205, MariaDB 1020,
PostgreSQL 40001 / 40P01 / 55P03. It may come wrapped in `TransientStorageFailure`, or it may be a lost savepoint
whose commit outcome is unknown. The collect endpoint answers it as follows:

| Code | When | Status | Headers | Client |
| --- | --- | --- | --- | --- |
| `CONFLICT_001` | Nothing of the request was committed. The unit rolled back as a whole. | 409 (or the configured 503) | `X-Conflict-Code: CONFLICT_001`, `Retry-After: <seconds>` | Retrying is safe. |
| `CONFLICT_002` | Something was committed earlier in the same request, or the commit outcome is unknown | 409 (or 503) | `X-Conflict-Code: CONFLICT_002`, no `Retry-After` | Read again, do not repeat blindly. |

- The JSON body keeps its existing `error` key (`"conflict"`). The code is added in `code`. Clients key on `code` or
  the header, not on `error`.
- `X-Conflict-Code` carries only these two values. Cross-origin callers of the collect endpoint must list it in
  `Access-Control-Expose-Headers`.
- "Committed earlier in the same request" uses transaction-doctrine's request watermark (`ConflictCode::forRequest()`).
  The watermark is taken at `kernel.request` when the host registers `NowoTransactionDoctrineBundle` with
  `commit_detection` on. Without it, the failure alone decides.
- **Not conflicts:** unique-constraint violations and lost connections are never mapped. A unique violation on the
  bundle's keys is handled where it is expected, as an upsert or as `LookAlikeIdentifierException`. Anything else is
  a real error (500).
- The bundle answers only on its own route. On host routes, a conflict reaches the host as `TransientStorageFailure`,
  or as the DBAL exception for single statements, and the host's error handling answers it.

## Host options

```yaml
nowo_device_intelligence:
    conflict_response:
        status: null        # 409 (default) or 503; null: transaction-doctrine's conflict_response when that bundle is registered, else 409
        retry_after: null   # seconds for CONFLICT_001; null: transaction-doctrine's, else 1
    doctrine:
        lock_timeout_ms: 5000   # lock-wait bound of the analysis unit's own transaction; 0 = the server's
```

- `doctrine.lock_timeout_ms` bounds how long the analysis unit's own transaction waits for a row lock held elsewhere:
  - PostgreSQL: `SET LOCAL lock_timeout`, which ends with the transaction.
  - MySQL and MariaDB (from 1.10.1): the session `innodb_lock_wait_timeout` is lowered to the bound, in whole seconds
    rounded up (at least 1 s), for that transaction only. The session's previous value is set back afterwards, also
    when the unit fails, so the next request on the same connection (worker mode) keeps the host's value. A session
    value already at or below the bound is left alone.
  - Inside an open host transaction, the bundle changes neither setting: the host's apply.
  - `0` keeps the server's value (MySQL and MariaDB: 50 s by default).
- For CONFLICT_002 on host routes and the request watermark, register `nowo-tech/transaction-doctrine`'s Symfony
  bundle (`commit_detection: true`).

## Custom repository adapters

A host that binds its own core ports gets the atomic paths by implementing the optional ports:

- `AtomicDeviceRepositoryInterface`: `saveObserved()` and `saveStability()`;
- `AtomicDeviceUserRepositoryInterface`: `recordLogin()`;
- `AtomicTrustedDeviceRepositoryInterface`: `revoke()`.

These are in `Nowo\DeviceIntelligence\Port`. **An adapter whose storage is shared between requests or workers must
implement them** to be safe under concurrent writes. In particular, `AtomicDeviceRepositoryInterface` is what closes
the device status overwrite (advisory GHSA-crq5-q557-89xf) for a custom adapter:

- `saveObserved()` must add one to the **stored** observation count in storage (never write back a count read
  earlier), write only the observation fields (last seen, confidence, stability, index key, label, last signals),
  leave the status, first-seen time and metadata as they are, and insert a device that is not stored yet.
- `saveStability()` must write only the stability of a stored device, and leave a deleted device deleted.
- Each must be one atomic write in your storage (for example one `UPDATE … SET n = n + 1` statement, or a row lock
  taken before the read).

Without them, the bundle falls back to `find()` + `save()`:

- `DeviceManager` (login link, revocation) keeps the read-modify-write of earlier releases.
- The analysis and the stability recalculation (from 1.10.1) read the device again just before `save()`: inside the
  analysis write unit, and after the history in the recalculation. The observed device keeps the stored status,
  first-seen time and metadata and adds one to the stored count; the recalculation changes only the stability, and does
  not store a device deleted meanwhile. A status change made during the risk assessment is therefore kept. The re-read
  and `save()` are still two calls, though: a status change committed between them can still be reverted. In the
  analysis this closes only if `find()` in your adapter takes a row lock inside the unit's transaction. The stability
  recalculation has no surrounding transaction (it runs without the transaction runner), so a row lock cannot help
  there: only the atomic port closes that window.
- The re-read helps only if `find()` **reads storage**. If your adapter is built on the Doctrine ORM `find()`, or on a
  cache, it returns the object it already holds (the identity map or the cache) and not the current row, so the host's
  revocation is still reverted, with no protection from the re-read at all. For ORM-based adapters, implement the atomic
  port.

The in-memory adapters use that fallback, since they are request-scoped.

## Tests

- `tests/Integration/Concurrency/ConcurrentWritesDbTest.php` uses two connections on MySQL 8.4, MariaDB 11.4 and
  PostgreSQL 16 (CI job "Databases", DBAL 3 and 4, a required check). Both sessions have a 1 s lock-wait timeout. It
  covers:
  - a revocation racing a relabel: no stale write-back, and the device is not trusted;
  - two first grants of the same pair: one row and no error;
  - two logins: both counted;
  - two observations of one device: both counted, and the host's status is kept;
  - a held row lock in the analysis unit: `TransientStorageFailure`, `CONFLICT_001` + `Retry-After`, nothing stored,
    and the connection is still usable; after an earlier commit in the same request, `CONFLICT_002` without
    `Retry-After`; once the lock is released, the retry stores the unit exactly once;
  - the unit's own lock-wait bound: with a session that would wait 10 s, the unit gives up after about 1 s, and on
    MySQL and MariaDB the session value is set back.
- `tests/Unit/Controller/CollectControllerConflictTest.php` checks the 409 / 503 body and headers for deadlock,
  lock-wait, serialization failure and runner failures. It also checks that unique violations, lost connections and
  domain errors are not mapped.
- `tests/Unit/Doctrine/DeviceIntelligenceTransactionRunnerTest.php` checks the own transaction, the host savepoint,
  the PostgreSQL `lock_timeout` and the MySQL / MariaDB `innodb_lock_wait_timeout` bound and its restore.
- `tests/Unit/Doctrine/DoctrineRepositoriesTest.php` checks the upsert after a unique violation: the concurrent row is
  updated, a look-alike fails closed, and a row deleted meanwhile is inserted again.
- `tests/Core/Unit/AtomicWritesTest.php` and `tests/Unit/Messenger/HandlersTest.php` check the fallback for adapters
  without the atomic port: a status changed during the risk assessment is kept.

## Residual risks

Accepted, contention-only (no data loss, leak or permission impact):

- **Approximate device fields.** Two analyses of the same device at the same moment both count. But the stability
  moving average, index key, label, last signals and last-seen time are written by the last one. These are
  approximate matching signals, and the next observation corrects them.
- **Erasure racing an analysis.** An analysis that matched a device just before a GDPR erasure or cleanup deleted it
  stores the device again (as before 1.10), with the new observation.
- **PostgreSQL REPEATABLE READ / SERIALIZABLE host transactions.** When a concurrent first write of the same device and
  user commits after the host transaction's snapshot, the locking read cannot see it. The write then tries the
  `INSERT` again and ends in the `UniqueConstraintViolationException` (1.10.0: `LookAlikeIdentifierException`) instead
  of updating that row. Under the default READ COMMITTED isolation this does not happen.
- **Login after credentials are accepted.** A lock conflict on the login link write is rethrown (rule 8), so that
  login request ends in the host's error answer, and the client signs in again.
- **Custom adapters** without the atomic ports keep a read-modify-write. For devices the window is only the re-read
  just before `save()` (see above); implement the atomic ports to close it.
- **Lock waits inside a host transaction** follow the host's settings (MySQL and MariaDB: 50 s by default).
