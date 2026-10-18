<?php

namespace App\NativeComponents;

use App\Models\Setting;
use App\Models\Trip;
use App\Support\NativeTrips;
use App\Support\SwitchesVehicle;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Edge\NativeComponent;

class TripList extends NativeComponent
{
    use SwitchesVehicle;

    /**
     * Pick up trips recorded from the widget or by driving detection.
     */
    public function mount(): void
    {
        NativeTrips::sync();
    }

    public function navTitle(): string
    {
        return 'Trips';
    }

    /**
     * @return Collection<int, Trip>
     */
    #[Computed]
    public function trips(): Collection
    {
        return Trip::forVehicle()->whereNotNull('ended_at')->latest('started_at')->get();
    }

    #[Computed]
    public function settings(): Setting
    {
        return Setting::current();
    }

    public function openTrip(int $tripId): void
    {
        $this->navigate("/trips/{$tripId}");
    }

    public function deleteTrip(int $tripId): void
    {
        Trip::whereKey($tripId)->whereNotNull('ended_at')->delete();
    }

    public function render(): View
    {
        return view('native.trip-list');
    }
}
