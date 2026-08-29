<?php

namespace App\Models;

use App\Support\VehiclePlate;
use Database\Factories\MemberVehicleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $member_id
 * @property string $name
 * @property string $plate
 * @property string $type
 * @property bool $is_primary
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['member_id', 'name', 'plate', 'type', 'is_primary'])]
class MemberVehicle extends Model
{
    /** @use HasFactory<MemberVehicleFactory> */
    use HasFactory;

    /** @return BelongsTo<Member, $this> */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * The plate is the car's identity, so it is stored canonically rather than
     * as it was typed; the unique index then catches the same car twice.
     *
     * @return Attribute<never, string>
     */
    protected function plate(): Attribute
    {
        return Attribute::set(fn (?string $plate): string => VehiclePlate::normalize($plate));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }
}
