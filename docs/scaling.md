# Scaling

| Devices | Observations | Candidate index |
| --- | --- | --- |
| ~10k | Doctrine + SQL composite index | SQL (`os_family`, `browser_family`, `last_seen_at`) |
| ~1M | Doctrine + Redis sets | implement `DeviceCandidateProviderInterface` |
| ~100M observations | partition / cold storage; matching store separate | dedicated matching service |

The MVP ships the SQL path. Never `SELECT` the whole device table. Matching stays synchronous; historical stability can use Messenger.
