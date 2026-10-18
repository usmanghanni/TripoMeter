<?php

namespace App\NativeComponents;

use App\Models\FuelFill;
use App\Support\FuelForecast;
use App\Support\OilChange;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Edge\NativeComponent;

class FuelLog extends NativeComponent
{
    public const PRICE_CHART_HEIGHT = 100;

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
     * @return array{km_per_liter: float|null, liters_per_100km: float|null, cost_per_km: float|null, total_cost: float, total_liters: float, average_price: float|null, latest_price: float|null, by_fill: array<int, float>}
     */
    #[Computed]
    public function report(): array
    {
        return FuelFill::economyReport();
    }

    /**
     * @return array{since: Carbon, driven_km: float, full_range_km: float, remaining_km: float, fraction_left: float, is_low: bool}|null
     */
    #[Computed]
    public function forecast(): ?array
    {
        return FuelForecast::current();
    }

    /**
     * @return array{last_on: Carbon, last_km: float|null, due_km: float|null, remaining_km: float|null, due_on: Carbon|null, days_left: int|null, fraction_left: float, is_due_soon: bool, is_overdue: bool}|null
     */
    #[Computed]
    public function oilChange(): ?array
    {
        return OilChange::status();
    }

    public function openExpenses(): void
    {
        $this->navigate('/expenses');
    }

    public function addFill(): void
    {
        $this->navigate('/fuel/add');
    }

    /**
     * Price-per-litre bars for the last 12 months, scaled to the most expensive month.
     *
     * @return list<array{label: string, height: int, a11y: string}>
     */
    #[Computed]
    public function priceBars(): array
    {
        $months = FuelFill::monthlyPrices();
        $maxPrice = max(1, ...array_map(fn (array $month): float => $month['price'] ?? 0, $months));

        return array_map(fn (array $month): array => [
            'label' => $month['label'],
            'height' => $month['price'] ? max(2, (int) round($month['price'] / $maxPrice * self::PRICE_CHART_HEIGHT)) : 0,
            'a11y' => $month['label'].': '.($month['price'] ? number_format($month['price'], 2).' per litre' : 'no fill-ups'),
        ], $months);
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
        return view('native.fuel-log');
    }
}
