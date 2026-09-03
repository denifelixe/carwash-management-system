<?php

namespace App\Models;

use App\Support\VehiclePlate;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A walk-in the outlet has served but who is not a member yet (BR-06).
 *
 * @property int $id
 * @property string $name
 * @property string|null $phone
 * @property string|null $vehicle_name
 * @property string $vehicle_plate
 * @property string|null $notes
 * @property bool $is_active
 * @property int|null $converted_member_id
 * @property Carbon|null $converted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'phone', 'vehicle_name', 'vehicle_plate', 'notes', 'is_active', 'converted_member_id', 'converted_at'])]
class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use HasFactory;

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** @return BelongsTo<Member, $this> */
    public function convertedMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'converted_member_id');
    }

    /**
     * The plate is this record's identity, so it is stored canonically rather
     * than as it was typed; the unique index then catches the same car twice.
     *
     * @return Attribute<never, string>
     */
    protected function vehiclePlate(): Attribute
    {
        return Attribute::set(fn (?string $plate): string => VehiclePlate::normalize($plate));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'converted_at' => 'datetime',
        ];
    }
}
