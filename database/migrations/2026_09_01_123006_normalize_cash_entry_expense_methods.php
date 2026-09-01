<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A hand-written expense now only names whether the money left the till,
     * so every card network already on file collapses into one non-cash label.
     * The daily balance read those rows the same way all along — anything but
     * 'Tunai' counted as non-cash — so no snapshot moves.
     */
    public function up(): void
    {
        DB::table('cash_entries')
            ->where('direction', 'out')
            ->where('method', '!=', 'Tunai')
            ->update(['method' => 'Non-Tunai']);
    }

    /**
     * Irreversible: which network paid a bill is not recoverable once merged,
     * and nothing downstream reads an expense method beyond cash or not.
     */
    public function down(): void {}
};
