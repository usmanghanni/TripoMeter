<?php

namespace App\Support;

use App\Models\Vehicle;
use Illuminate\Support\Collection;
use Native\Mobile\Attributes\Computed;

/**
 * For screens that show one vehicle's data: the vehicle list for the
 * switcher partial (native.partials.vehicle-switcher) and the action it calls.
 */
trait SwitchesVehicle
{
    /**
     * @return Collection<int, Vehicle>
     */
    #[Computed]
    public function vehicles(): Collection
    {
        return Vehicle::orderBy('id')->get();
    }

    #[Computed]
    public function currentVehicle(): Vehicle
    {
        return Vehicle::current();
    }

    public function switchVehicle(int $vehicleId): void
    {
        Vehicle::find($vehicleId)?->makeCurrent();
    }
}
