<?php

declare(strict_types=1);

namespace App\Support\Database;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use stdClass;

/**
 * Card T10.1 and conventions.md §14a: the app refuses to run on a database that is not
 * the MariaDB 11.8 of record running with the connection settings the app enforces in
 * code. Read-only: SELECTs only. `zb:db-check` and `zb:verify` run it, and the web app
 * runs it once on boot (AppServiceProvider::configureDatabaseGuard).
 *
 * The server's own defaults are not trusted: the shared host answered with no strict mode
 * and no ONLY_FULL_GROUP_BY, so a query that passes in tests would behave differently in
 * production (M10 fact sheet, 15 October 2026).
 */
final class DatabaseGuard
{
    /** The oldest MariaDB whose CHECK constraints are enforced (card T10.1). */
    public const MINIMUM_VERSION = '10.2';

    /** The session sql_mode entries a strict session must carry (conventions §14a). */
    public const REQUIRED_MODES = ['STRICT_TRANS_TABLES', 'ONLY_FULL_GROUP_BY'];

    /** The connection time zone that keeps now()/UTC_TIMESTAMP() on the DATETIME(3) columns. */
    public const REQUIRED_TIME_ZONE = '+00:00';

    /** The only storage engine the schema may use. */
    public const REQUIRED_ENGINE = 'InnoDB';

    /**
     * One message per failing item; an empty list means the database is right.
     *
     * @return list<string>
     */
    public static function failures(?Connection $connection = null): array
    {
        $connection ??= DB::connection();

        if ($connection->getDriverName() !== 'mysql') {
            return ['the connection driver is not mysql (MariaDB)'];
        }

        $failures = [];
        $row = $connection->selectOne(
            'select version() as version, @@session.sql_mode as sql_mode, '
            .'@@session.time_zone as time_zone, @@default_storage_engine as storage_engine, '
            .'@@session.character_set_connection as charset, @@session.collation_connection as collation',
        );

        $version = (string) ($row->version ?? '');
        if (! str_contains($version, 'MariaDB')) {
            $failures[] = 'the server is not MariaDB (found '.self::versionNumber($version).')';
        } elseif (version_compare(self::versionNumber($version), self::MINIMUM_VERSION, '<')) {
            $failures[] = 'MariaDB is older than '.self::MINIMUM_VERSION.' (found '.self::versionNumber($version).'), so CHECK constraints are ignored';
        }

        $modes = array_map('trim', explode(',', (string) ($row->sql_mode ?? '')));
        foreach (self::REQUIRED_MODES as $mode) {
            if (! in_array($mode, $modes, true)) {
                $failures[] = 'the session sql_mode does not include '.$mode;
            }
        }

        if ((string) ($row->time_zone ?? '') !== self::REQUIRED_TIME_ZONE) {
            $failures[] = 'the connection time zone is not '.self::REQUIRED_TIME_ZONE;
        }

        if (strcasecmp((string) ($row->storage_engine ?? ''), self::REQUIRED_ENGINE) !== 0) {
            $failures[] = 'the default storage engine is not '.self::REQUIRED_ENGINE;
        }

        if ((string) ($row->charset ?? '') !== 'utf8mb4') {
            $failures[] = 'the connection charset is not utf8mb4';
        }

        foreach ($connection->select(
            'select table_name as name from information_schema.tables '
            .'where table_schema = database() and table_type = ? and engine <> ?',
            ['BASE TABLE', self::REQUIRED_ENGINE],
        ) as $table) {
            $failures[] = 'table '.$table->name.' is not '.self::REQUIRED_ENGINE;
        }

        $present = array_map(
            static fn (stdClass $t): string => (string) $t->trigger_name,
            $connection->select('select trigger_name from information_schema.triggers where trigger_schema = database()'),
        );

        foreach (self::requiredTriggers() as $trigger) {
            if (! in_array($trigger, $present, true)) {
                $failures[] = 'the protection trigger '.$trigger.' is missing';
            }
        }

        return $failures;
    }

    /**
     * Throw when the database is not the MariaDB of record. The message names every
     * failing item (card T10.1).
     */
    public static function check(?Connection $connection = null): void
    {
        $failures = self::failures($connection);

        if ($failures !== []) {
            throw new RuntimeException('The database is not the MariaDB of record: '.implode('; ', $failures).'.');
        }
    }

    /**
     * @return list<string>
     */
    private static function requiredTriggers(): array
    {
        /** @var list<string> $triggers */
        $triggers = config('zb.required_triggers', []);

        return $triggers;
    }

    /** The "11.8.9" from a version string such as "11.8.9-MariaDB-ubu2404". */
    private static function versionNumber(string $version): string
    {
        preg_match('/^(\d+(?:\.\d+){1,2})/', $version, $matches);

        return $matches[1] ?? '0';
    }
}
