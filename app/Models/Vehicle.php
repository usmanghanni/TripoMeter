<?php

namespace App\Models;

use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A car or bike. Trips, fill-ups and expenses belong to one; the "current"
 * vehicle (Settings) is the one shown and recorded for.
 */
class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = ['name', 'tank_capacity_l', 'oil_change_interval_km', 'oil_change_interval_months'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tank_capacity_l' => 'float',
            'oil_change_interval_km' => 'integer',
            'oil_change_interval_months' => 'integer',
        ];
    }

    /**
     * The vehicle being shown and recorded for. Falls back to the first one,
     * creating "My car" if there are none.
     */
    public static function current(): self
    {
        $activeId = Setting::current()->active_vehicle_id;

        return ($activeId ? static::find($activeId) : null)
            ?? static::orderBy('id')->first()
            ?? static::create(['name' => 'My car']);
    }

    public function makeCurrent(): void
    {
        Setting::current()->update(['active_vehicle_id' => $this->id]);
    }

    /**
     * @return HasMany<Trip, $this>
     */
    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    /**
     * @return HasMany<FuelFill, $this>
     */
    public function fuelFills(): HasMany
    {
        return $this->hasMany(FuelFill::class);
    }

    /**
     * @return HasMany<Expense, $this>
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}
