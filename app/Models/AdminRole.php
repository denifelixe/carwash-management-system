<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['key', 'name', 'description', 'is_active'])]
class AdminRole extends Model
{
    /**
     * @return HasMany<Admin, $this>
     */
    public function admins(): HasMany
    {
        return $this->hasMany(Admin::class, 'role_id');
    }

    /**
     * @return BelongsToMany<AdminModule, $this>
     */
    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(AdminModule::class, 'admin_role_module')
            ->withPivot(['can_create', 'can_read', 'can_update', 'can_delete']);
    }

    /**
     * @return BelongsToMany<AdminModule, $this>
     */
    public function readableModules(): BelongsToMany
    {
        return $this->modules()
            ->wherePivot('can_read', true)
            ->where('admin_modules.is_active', true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
