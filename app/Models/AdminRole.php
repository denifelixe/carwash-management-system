<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['key', 'name', 'description', 'is_active'])]
class AdminRole extends Model
{
    /**
     * @return BelongsToMany<AdminModule, $this>
     */
    public function readableModules(): BelongsToMany
    {
        return $this->belongsToMany(AdminModule::class, 'admin_role_module')
            ->wherePivot('can_read', true)
            ->withPivot(['can_create', 'can_read', 'can_update', 'can_delete']);
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
