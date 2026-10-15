<?php

namespace App\NativeComponents;

use App\Models\FuelFill;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Edge\NativeComponent;

class FuelLog extends NativeComponent
{
    public function navTitle(): string
    {
        return 'Fuel';
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
     * @return array{km_per_liter: float|null, liters_per_100km: float|null, cost_per_km: float|null, total_cost: float, total_liters: float, by_fill: array<int, float>}
     */
    #[Computed]
    public function report(): array
    {
        return FuelFill::economyReport();
    }

    public function addFill(): void
    {
        $this->navigate('/fuel/add');
    }

    public function deleteFill(int $fillId): void
    {
        FuelFill::whereKey($fillId)->delete();
    }

    public function render(): View
    {
        return view('native.fuel-log');
    }
}
