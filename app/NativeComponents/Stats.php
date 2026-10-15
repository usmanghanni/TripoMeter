<?php

namespace App\NativeComponents;

use App\Models\Setting;
use App\Models\Trip;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Edge\NativeComponent;

class Stats extends NativeComponent
{
    /**
     * Chart height in points.
     */
    public const CHART_HEIGHT = 160;

    /**
     * 0 = daily, 1 = monthly.
     */
    public int $periodIndex = 0;

    public function navTitle(): string
    {
        return 'Stats';
    }

    #[Computed]
    public function settings(): Setting
    {
        return Setting::current();
    }

    /**
     * @return list<array{label: string, distance: float, height: int}>
     */
    #[Computed]
    public function bars(): array
    {
        $periods = Trip::distanceByPeriod($this->periodIndex === 1 ? 'monthly' : 'daily');
        $maxMeters = max(1, ...array_column($periods, 'meters'));

        return array_map(fn (array $period): array => [
            'label' => $period['label'],
            'distance' => $this->settings->distance($period['meters']),
            'height' => $period['meters'] > 0 ? max(2, (int) round($period['meters'] / $maxMeters * self::CHART_HEIGHT)) : 0,
        ], $periods);
    }

    public function render(): View
    {
        $distances = array_column($this->bars, 'distance');
        $activePeriods = count(array_filter($distances));

        return view('native.stats', [
            'total' => array_sum($distances),
            'average' => $activePeriods > 0 ? array_sum($distances) / $activePeriods : 0,
            'best' => max(0, ...$distances),
            'chartHeight' => self::CHART_HEIGHT,
        ]);
    }
}
