<?php

namespace App\Actions;

use Illuminate\Support\Facades\DB;

class ClearOperationalData
{
    /**
     * Tables are listed explicitly so a future master table is never deleted by accident.
     *
     * @var list<string>
     */
    private const TABLES = [
        'daily_balance',
        'cash_entry_attachments',
        'cash_entries',
        'order_transactions',
        'order_services',
        'orders',
        'member_vehicles',
        'member_password_reset_tokens',
        'members',
        'admin_password_reset_tokens',
        'sessions',
        'jobs',
        'job_batches',
        'failed_jobs',
        'cache_locks',
        'cache',
    ];

    /**
     * @return array<string, int>
     */
    public function handle(): array
    {
        return DB::transaction(function (): array {
            $deletedRows = [];

            foreach (self::TABLES as $table) {
                $deletedRows[$table] = DB::table($table)->delete();
            }

            return $deletedRows;
        });
    }
}
