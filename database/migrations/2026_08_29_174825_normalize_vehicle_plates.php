<?php

use App\Support\VehiclePlate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Plates were stored as the counter typed them, so "B 8120 DS" and "B8120DS"
 * sat in the database as two different cars. Every plate is pulled into the one
 * canonical form the models now write, which is what lets an order's plate be
 * matched against the vehicles members have already registered.
 */
return new class extends Migration
{
    /**
     * Each plate column, and whether the table holds a unique index over it.
     *
     * @var array<string, array{column: string, unique: bool}>
     */
    private const PLATE_COLUMNS = [
        'orders' => ['column' => 'vehicle_plate', 'unique' => false],
        'member_vehicles' => ['column' => 'plate', 'unique' => true],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::PLATE_COLUMNS as $table => $plate) {
            $this->normalize($table, $plate['column'], $plate['unique']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        /*
         * Nothing to undo: the spacing a plate was originally typed with is not
         * recorded anywhere, so it cannot be put back.
         */
    }

    /**
     * Two `member_vehicles` rows that differ only by their spacing collapse onto
     * the same plate, which the unique index would reject. That is a genuine
     * duplicate for a human to resolve, not something a migration should decide,
     * so the later row is left exactly as it was rather than failing the run.
     * Orders carry no such index — the same car may of course visit twice.
     */
    private function normalize(string $table, string $column, bool $unique): void
    {
        $taken = $unique
            ? DB::table($table)->pluck($column)->flip()->all()
            : [];

        DB::table($table)
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($table, $column, $unique, &$taken): void {
                foreach ($rows as $row) {
                    $stored = (string) $row->{$column};
                    $normalized = VehiclePlate::normalize($stored);

                    if ($normalized === $stored) {
                        continue;
                    }

                    if ($unique && isset($taken[$normalized])) {
                        continue;
                    }

                    if ($unique) {
                        unset($taken[$stored]);
                        $taken[$normalized] = true;
                    }

                    DB::table($table)
                        ->where('id', $row->id)
                        ->update([$column => $normalized]);
                }
            });
    }
};
