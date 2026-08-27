<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    config(['session.driver' => 'database']);

    app('session')->forgetDrivers();
});

test('database sessions do not require a default authentication guard', function () {
    expect(config('auth.defaults.guard'))->toBeNull()
        ->and(config('auth.defaults.passwords'))->toBeNull()
        ->and(Schema::hasColumn('sessions', 'user_id'))->toBeFalse()
        ->and(Schema::hasColumn('sessions', 'admin_id'))->toBeTrue()
        ->and(Schema::hasColumn('sessions', 'member_id'))->toBeTrue();

    $this->get(route('demo.home'))->assertOk();

    $databaseSession = DB::table('sessions')->first();

    expect($databaseSession)
        ->not->toBeNull()
        ->and($databaseSession->admin_id)->toBeNull()
        ->and($databaseSession->member_id)->toBeNull()
        ->and($databaseSession->payload)->not->toBeEmpty()
        ->and($databaseSession->last_activity)->toBeInt();
});

test('the user id migration supports a sessions table without the conventional index', function () {
    Schema::table('sessions', function (Blueprint $table) {
        $table->foreignId('user_id')->nullable();
    });

    expect(Schema::hasColumn('sessions', 'user_id'))->toBeTrue();

    $migration = require database_path('migrations/2026_08_27_061230_remove_user_id_from_sessions_table.php');

    $migration->up();

    expect(Schema::hasColumn('sessions', 'user_id'))->toBeFalse();
});
