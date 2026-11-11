<?php

declare(strict_types=1);

namespace Nowo\DeviceIntelligenceBundle\Tests\Support;

use Doctrine\DBAL\Tools\DsnParser;

/**
 * Database of the persistence tests (1.7, audit LI-DI-L1): SQLite by default; CI also runs them on MySQL and PostgreSQL
 * through `DI_TEST_DATABASE_URL` (e.g. `pdo-pgsql://postgres@127.0.0.1:5432/di_test`), and with
 * `DI_TEST_ID_GENERATION=sequence` the generated ids use a SEQUENCE on PostgreSQL (DBAL 3 default,
 * `identity_generation_preferences: sequence`).
 *
 * @author Héctor Franco Aceituno <hectorfranco@nowo.tech>
 * @copyright 2026 Nowo.tech
 */
final class TestDatabase
{
    /**
     * The external database URL, or null for SQLite.
     */
    public static function url(): ?string
    {
        $url = getenv('DI_TEST_DATABASE_URL');

        return \is_string($url) && '' !== $url ? $url : null;
    }

    public static function sequenceIds(): bool
    {
        return 'sequence' === getenv('DI_TEST_ID_GENERATION');
    }

    /**
     * DBAL connection parameters of {@see self::url()}.
     *
     * @return array<string, mixed>
     */
    public static function params(): array
    {
        return (new DsnParser([
            'pdo-mysql' => 'pdo_mysql',
            'mysql' => 'pdo_mysql',
            'pdo-pgsql' => 'pdo_pgsql',
            'postgresql' => 'pdo_pgsql',
            'pgsql' => 'pdo_pgsql',
        ]))->parse((string) self::url());
    }
}
