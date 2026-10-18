<?php

namespace App\NativeComponents;

use App\Models\FuelFill;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Edge\NativeComponent;

/**
 * Every fill-up, newest odometer first, with the economy each full tank gave.
 */
class FuelHistory extends NativeComponent
{
    public function navTitle(): string
    {
        return 'Fill-ups';
    }

    /**
     * @return Collection<int, FuelFill>
     */
    #[Computed]
    public function fills(): Collection
    {
        return FuelFill::orderByDesc('odometer_km')->get();
    }

    /**
     * Fuel economy (km/L) per full fill-up, keyed by fill id.
     *
     * @return array<int, float>
     */
    #[Computed]
    public function economyByFill(): array
    {
        return FuelFill::economyReport()['by_fill'];
    }

    public function addFill(): void
    {
        $this->navigate('/fuel/add');
    }

    public function openFill(int $fillId): void
    {
        $this->navigate("/fuel/{$fillId}");
    }

    public function deleteFill(int $fillId): void
    {
        FuelFill::whereKey($fillId)->delete();
    }

    public function render(): View
    {
        return view('native.fuel-history');
    }
}
