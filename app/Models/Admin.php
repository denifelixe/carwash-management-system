<?php

namespace App\Models;

use Database\Factories\AdminFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int|null $role_id
 * @property int|null $work_shift_id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string|null $profile_photo_path
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property bool $is_owner
 * @property bool $is_active
 * @property Carbon|null $last_login_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'phone', 'password', 'role_id', 'work_shift_id', 'is_active'])]
#[Hidden(['password', 'remember_token', 'profile_photo_path'])]
class Admin extends Authenticatable
{
    /** @use HasFactory<AdminFactory> */
    use HasFactory, Notifiable;

    /**
     * @return BelongsTo<AdminRole, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(AdminRole::class, 'role_id');
    }

    /**
     * @return BelongsTo<AdminWorkShift, $this>
     */
    public function workShift(): BelongsTo
    {
        return $this->belongsTo(AdminWorkShift::class, 'work_shift_id');
    }

    public function hasModulePermission(string $moduleKey, string $permission): bool
    {
        if ($this->is_owner) {
            return true;
        }

        if (! in_array($permission, ['create', 'read', 'update', 'delete'], true)) {
            return false;
        }

        $role = $this->role;

        if ($role === null || ! $role->is_active) {
            return false;
        }

        return $role->modules()
            ->where('admin_modules.key', $moduleKey)
            ->where('admin_modules.is_active', true)
            ->wherePivot("can_{$permission}", true)
            ->exists();
    }

    public function profilePhotoUrl(): ?string
    {
        return $this->profile_photo_path !== null
            ? route('admin.users.photo', [
                'adminUser' => $this,
                'version' => Str::afterLast($this->profile_photo_path, '/'),
            ], absolute: false)
            : null;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'is_owner' => 'boolean',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
