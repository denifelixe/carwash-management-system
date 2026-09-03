<?php

namespace App\Support\Auth;

use App\Models\Admin;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;

/**
 * Clears everything that could let a deactivated account back in before its
 * session would have expired on its own.
 */
class AccountSessions
{
    public static function revoke(Authenticatable $account): void
    {
        $account->forceFill(['remember_token' => null])->saveQuietly();

        DB::table((string) config('session.table', 'sessions'))
            ->where($account instanceof Admin ? 'admin_id' : 'member_id', $account->getKey())
            ->delete();
    }
}
