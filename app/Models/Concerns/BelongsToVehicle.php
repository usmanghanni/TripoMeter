<?php

namespace App\Models\Concerns;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Records that belong to a vehicle: new ones go to the current vehicle unless
 * one is given, and forVehicle() narrows queries to one (current by default).
 */
trait BelongsToVehicle
{
    public static function bootBelongsToVehicle(): void
    {
        static::creating(function (self $model): void {
            $model->vehicle_id ??= Vehicle::current()->id;
        });
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeForVehicle(Builder $query, ?Vehicle $vehicle = null): void
    {
        $query->where($query->qualifyColumn('vehicle_id'), ($vehicle ?? Vehicle::current())->id);
    }
}
