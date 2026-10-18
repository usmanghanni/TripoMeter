<?php

namespace App\NativeComponents;

use App\Models\Vehicle;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Edge\NativeComponent;

class VehicleList extends NativeComponent
{
    public function navTitle(): string
    {
        return 'Vehicles';
    }

    /**
     * @return Collection<int, Vehicle>
     */
    #[Computed]
    public function vehicles(): Collection
    {
        return Vehicle::withCount(['trips', 'fuelFills', 'expenses'])->orderBy('id')->get();
    }

    #[Computed]
    public function currentVehicleId(): int
    {
        return Vehicle::current()->id;
    }

    public function addVehicle(): void
    {
        $this->navigate('/vehicles/add');
    }

    public function openVehicle(int $vehicleId): void
    {
        $this->navigate("/vehicles/{$vehicleId}");
    }

    public function render(): View
    {
        return view('native.vehicle-list');
    }
}
