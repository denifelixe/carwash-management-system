<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('admin_modules', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        $now = now();

        DB::table('admin_modules')->insert([
            ['key' => 'dashboard', 'name' => 'Dashboard', 'description' => 'Ringkasan operasional', 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'orders', 'name' => 'Order', 'description' => 'Proses kendaraan masuk', 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'pos', 'name' => 'Kasir POS', 'description' => 'Pembayaran order', 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'bookings', 'name' => 'Booking Order', 'description' => 'Jadwal pelanggan', 'sort_order' => 4, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'finance', 'name' => 'Keuangan', 'description' => 'Arus kas harian', 'sort_order' => 5, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'customers', 'name' => 'Member', 'description' => 'Database & stempel', 'sort_order' => 6, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'inventory', 'name' => 'Stock Inventory', 'description' => 'Stok operasional', 'sort_order' => 7, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'rewards', 'name' => 'Reward', 'description' => 'Katalog & syarat stempel', 'sort_order' => 8, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'users_and_roles', 'name' => 'User & Role', 'description' => 'Hak akses pegawai', 'sort_order' => 9, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'reports', 'name' => 'Laporan', 'description' => 'Monitoring & rekap', 'sort_order' => 10, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_modules');
    }
};
