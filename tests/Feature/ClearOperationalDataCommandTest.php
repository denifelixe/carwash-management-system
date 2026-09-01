<?php

use App\Models\Admin;
use App\Models\AdminShift;
use App\Models\AppSetting;
use App\Models\CashEntry;
use App\Models\CashEntryAttachment;
use App\Models\Member;
use App\Models\MemberVehicle;
use App\Models\Order;
use App\Models\OrderTransaction;
use App\Models\Service;
use App\Support\DangerousKeyManager;
use Dotenv\Dotenv;
use Illuminate\Support\Facades\DB;

function bindDangerousKeyFile(string $key = 'dangerous-key-current'): string
{
    $path = tempnam(sys_get_temp_dir(), 'carwash-dangerous-key-');

    if ($path === false) {
        throw new RuntimeException('Unable to create a temporary environment file.');
    }

    file_put_contents($path, "APP_ENV=testing\nDANGEROUS_KEY={$key}\n");
    app()->instance(DangerousKeyManager::class, new DangerousKeyManager($path));

    return $path;
}

function dangerousKeyFrom(string $path): string
{
    $values = Dotenv::parse((string) file_get_contents($path));

    return (string) $values['DANGEROUS_KEY'];
}

test('production requires force before consuming the dangerous key', function () {
    $environmentPath = bindDangerousKeyFile();
    $this->app->detectEnvironment(fn (): string => 'production');

    try {
        $this->artisan('app:clear-data', ['--key' => 'dangerous-key-current'])
            ->expectsOutput('Production mewajibkan opsi --force.')
            ->assertFailed();

        expect(dangerousKeyFrom($environmentPath))->toBe('dangerous-key-current');
    } finally {
        unlink($environmentPath);
    }
});

test('an invalid dangerous key cannot clear or rotate data', function () {
    $environmentPath = bindDangerousKeyFile();
    $member = Member::factory()->create();

    try {
        $this->artisan('app:clear-data', ['--force' => true, '--key' => 'wrong-key'])
            ->expectsOutput('Dangerous key tidak valid.')
            ->assertFailed();

        $this->assertModelExists($member);
        expect(dangerousKeyFrom($environmentPath))->toBe('dangerous-key-current');
    } finally {
        unlink($environmentPath);
    }
});

test('it clears operational data while preserving users roles and masters', function () {
    $environmentPath = bindDangerousKeyFile();
    $roleId = DB::table('admin_roles')->value('id');
    $admin = Admin::factory()->create(['role_id' => $roleId]);
    AdminShift::query()->create([
        'key' => 'test-shift',
        'name' => 'Test Shift',
        'starts_at' => '08:00',
        'ends_at' => '16:00',
    ]);
    $service = Service::factory()->create();
    AppSetting::query()->updateOrCreate(['key' => 'test-master'], ['value' => 'preserved']);

    $member = Member::factory()->create();
    $vehicle = MemberVehicle::factory()->for($member)->create();
    $order = Order::factory()->create([
        'member_id' => $member->id,
        'member_vehicle_id' => $vehicle->id,
        'created_by_admin_id' => $admin->id,
    ]);
    $variation = $service->serviceVariations()->firstOrFail();
    $order->serviceVariations()->attach($variation->id, [
        'service_name' => $service->name,
        'unit_price' => $service->price,
        'quantity' => 1,
        'total_price' => $service->price,
        'stamps' => $service->stamps,
    ]);
    OrderTransaction::factory()->for($order)->create(['recorded_by_admin_id' => $admin->id]);
    $cashEntry = CashEntry::factory()->create(['recorded_by_admin_id' => $admin->id]);
    CashEntryAttachment::factory()->for($cashEntry)->create();

    DB::table('admin_password_reset_tokens')->insert([
        'email' => $admin->email,
        'token' => 'admin-token',
        'created_at' => now(),
    ]);
    DB::table('member_password_reset_tokens')->insert([
        'email' => $member->email,
        'token' => 'member-token',
        'created_at' => now(),
    ]);
    DB::table('sessions')->insert([
        'id' => 'test-session',
        'admin_id' => $admin->id,
        'member_id' => null,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Pest',
        'payload' => 'test',
        'last_activity' => now()->getTimestamp(),
    ]);
    DB::table('cache')->insert(['key' => 'test', 'value' => 'cached', 'expiration' => 9999999999]);
    DB::table('cache_locks')->insert(['key' => 'test', 'owner' => 'pest', 'expiration' => 9999999999]);
    DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => '{}',
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => now()->getTimestamp(),
        'created_at' => now()->getTimestamp(),
    ]);
    DB::table('job_batches')->insert([
        'id' => 'test-batch',
        'name' => 'Test batch',
        'total_jobs' => 1,
        'pending_jobs' => 1,
        'failed_jobs' => 0,
        'failed_job_ids' => '[]',
        'options' => null,
        'cancelled_at' => null,
        'created_at' => now()->getTimestamp(),
        'finished_at' => null,
    ]);
    DB::table('failed_jobs')->insert([
        'uuid' => fake()->uuid(),
        'connection' => 'sync',
        'queue' => 'default',
        'payload' => '{}',
        'exception' => 'test',
        'failed_at' => now(),
    ]);

    $preservedCounts = collect([
        'admins',
        'admin_roles',
        'admin_modules',
        'admin_role_module',
        'admin_shifts',
        'services',
        'app_settings',
        'migrations',
    ])->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()]);

    try {
        $this->artisan('app:clear-data', ['--force' => true, '--key' => 'dangerous-key-current'])
            ->expectsOutputToContain('Data operasional berhasil dibersihkan.')
            ->expectsOutput('DANGEROUS_KEY telah dirotasi di file environment.')
            ->assertSuccessful();

        foreach ([
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
        ] as $table) {
            $this->assertDatabaseCount($table, 0);
        }

        $preservedCounts->each(
            fn (int $count, string $table) => $this->assertDatabaseCount($table, $count),
        );

        $rotatedKey = dangerousKeyFrom($environmentPath);

        expect($rotatedKey)
            ->not->toBe('dangerous-key-current')
            ->toHaveLength(64);

        $newMember = Member::factory()->create();

        $this->artisan('app:clear-data', ['--force' => true, '--key' => 'dangerous-key-current'])
            ->expectsOutput('Dangerous key tidak valid.')
            ->assertFailed();

        $this->assertModelExists($newMember);
    } finally {
        unlink($environmentPath);
    }
});
