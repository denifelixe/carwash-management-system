<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * A member registered at the till gives the cashier a name, a phone, and a car
 * — never an email or a password. Those two columns therefore stop being the
 * price of being a member and become what they always were: portal credentials,
 * filled in if and when that person wants to sign in.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table): void {
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        /*
         * Members registered at the till hold no credentials, so each is given
         * an unusable placeholder rather than blocking the rollback on a column
         * that is about to be NOT NULL again. Neither value can be signed in
         * with: the address is unroutable and the hash matches no password.
         */
        DB::table('members')
            ->where(fn ($query) => $query->whereNull('email')->orWhereNull('password'))
            ->orderBy('id')
            ->chunkById(500, function ($members): void {
                foreach ($members as $member) {
                    DB::table('members')->where('id', $member->id)->update([
                        'email' => $member->email ?? 'member-'.$member->id.'@invalid.local',
                        'password' => $member->password ?? bcrypt(Str::random(40)),
                    ]);
                }
            });

        Schema::table('members', function (Blueprint $table): void {
            $table->string('email')->nullable(false)->change();
            $table->string('password')->nullable(false)->change();
        });
    }
};
