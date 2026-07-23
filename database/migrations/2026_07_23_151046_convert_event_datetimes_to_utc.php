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
        $this->convertValues('Europe/Berlin', 'UTC');
        $this->changeColumnTypes(toTimestamp: true);
    }

    public function down(): void
    {
        $this->changeColumnTypes(toTimestamp: false);
        $this->convertValues('UTC', 'Europe/Berlin');
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
