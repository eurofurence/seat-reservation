<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // These columns hold Berlin wall-clock in naive DATETIME. Convert each value
    // through Europe/Berlin so CET (winter, +01:00) and CEST (summer, +02:00)
    // values both land on the correct UTC instant.
    private array $columns = ['starts_at', 'reservation_ends_at', 'booking_starts_at'];

    public function up(): void
    {
        if ($this->alreadyConverted()) { // idempotent: safe if `migrate` runs twice after a full success
            return;
        }

        $this->convertValues('Europe/Berlin', 'UTC');
        // ponytail: doesn't protect against a hard process kill between this line and the
        // ALTER below (MySQL has no transactional DDL) — that would need a resumable
        // migration; not worth it for a one-time conversion, so we only guard the common
        // "ran to completion, then ran again" case above.
        $this->assertWithinTimestampRange();
        $this->changeColumnTypes(toTimestamp: true);
    }

    public function down(): void
    {
        if (! $this->alreadyConverted()) {
            return;
        }

        $this->changeColumnTypes(toTimestamp: false);
        $this->convertValues('UTC', 'Europe/Berlin');
    }

    // True once the columns are already `timestamp` (i.e. up() already completed).
    private function alreadyConverted(): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            return false;
        }

        $type = DB::selectOne(
            'SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            ['events', $this->columns[0]]
        )->DATA_TYPE;

        return $type === 'timestamp';
    }

    // MySQL TIMESTAMP only covers 1970-01-01 00:00:01 to 2038-01-19 03:14:07 UTC; DATETIME
    // covers 1000-9999. Fail loudly instead of silently truncating an out-of-range event date.
    private function assertWithinTimestampRange(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        $bounds = DB::table('events')->selectRaw(
            implode(', ', array_map(fn ($c) => "MIN({$c}) as min_{$c}, MAX({$c}) as max_{$c}", $this->columns))
        )->first();

        foreach ($this->columns as $column) {
            $min = $bounds->{"min_{$column}"};
            $max = $bounds->{"max_{$column}"};

            if (($min !== null && $min < '1970-01-01 00:00:01') || ($max !== null && $max > '2038-01-19 03:14:07')) {
                throw new RuntimeException("Column {$column} has a UTC value outside the TIMESTAMP range (1970-2038); aborting before schema change.");
            }
        }
    }

    private function assertMysql(): void
    {
        $driver = DB::getDriverName();

        if ($driver !== 'mysql') {
            throw new RuntimeException("Timezone conversion is only implemented for MySQL; {$driver} needs its own migration.");
        }
    }

    // CONVERT_TZ with named zones returns NULL when the tz tables are missing,
    // which would silently blank every value. Fail loudly instead.
    private function assertTimezoneTablesLoaded(): void
    {
        $probe = DB::selectOne("SELECT CONVERT_TZ('2026-07-15 12:00:00', 'Europe/Berlin', 'UTC') AS v")->v;

        if ($probe === null) {
            throw new RuntimeException('MySQL timezone tables are not loaded; run mysql_tzinfo_to_sql to populate them before migrating.');
        }
    }

    private function convertValues(string $from, string $to): void
    {
        if (DB::getDriverName() === 'sqlite') { // (used in tests, stores raw text so nothing to convert)
            return;
        }
        $this->assertMysql();
        $this->assertTimezoneTablesLoaded();

        foreach ($this->columns as $column) {
            DB::table('events')
                ->whereNotNull($column)
                ->update([
                    $column => DB::raw("CONVERT_TZ({$column}, '{$from}', '{$to}')"),
                ]);
        }
    }

    private function changeColumnTypes(bool $toTimestamp): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }
        $this->assertMysql();

        Schema::table('events', function (Blueprint $table) use ($toTimestamp) {
            foreach ($this->columns as $column) {
                $definition = $toTimestamp
                    ? $table->timestamp($column)
                    : $table->dateTime($column);

                $definition->nullable()->change();
            }
        });
    }
};
