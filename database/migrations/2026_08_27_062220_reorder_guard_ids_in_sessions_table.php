<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        Schema::table('sessions', function (Blueprint $table) {
            $table->foreignId('admin_id')->nullable()->after('id')->change();
            $table->foreignId('member_id')->nullable()->after('admin_id')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        Schema::table('sessions', function (Blueprint $table) {
            $table->foreignId('admin_id')->nullable()->after('last_activity')->change();
            $table->foreignId('member_id')->nullable()->after('admin_id')->change();
        });
    }
};
