<?php

namespace App\Models;

use Database\Factories\FuelFillFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FuelFill extends Model
{
    /** @use HasFactory<FuelFillFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = ['filled_on', 'odometer_km', 'liters', 'total_cost', 'is_full_tank'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'filled_on' => 'date',
            'odometer_km' => 'float',
            'liters' => 'float',
            'total_cost' => 'float',
            'is_full_tank' => 'boolean',
        ];
    }

    /**
     * Price paid per litre on this fill, null when cost or volume is missing.
     */
    protected function pricePerLiter(): Attribute
    {
        return Attribute::get(fn (): ?float => $this->liters > 0 && $this->total_cost > 0 ? $this->total_cost / $this->liters : null);
    }

    /**
     * Average price per litre (volume-weighted) for each of the last 12 months, oldest first.
     *
     * @return list<array{label: string, price: float|null}>
     */
    public static function monthlyPrices(): array
    {
        $start = now()->startOfMonth()->subMonths(11);

        $fillsByMonth = static::where('filled_on', '>=', $start->toDateString())
            ->where('total_cost', '>', 0)
            ->get(['filled_on', 'liters', 'total_cost'])
            ->groupBy(fn (FuelFill $fill): string => $fill->filled_on->format('Y-m'));

        return collect(range(0, 11))
            ->map(function (int $offset) use ($start, $fillsByMonth): array {
                $month = $start->copy()->addMonths($offset);
                $fills = $fillsByMonth[$month->format('Y-m')] ?? collect();
                $liters = (float) $fills->sum('liters');

                return [
                    'label' => $month->format('M'),
                    'price' => $liters > 0 ? (float) $fills->sum('total_cost') / $liters : null,
                ];
            })
            ->all();
    }

    /**
     * Fuel economy using the full-tank-to-full-tank method: the fuel added from one
     * full fill up to and including the next full fill was burned over the
     * odometer distance between them. Fills before the first full tank are ignored.
     *
     * @return array{km_per_liter: float|null, liters_per_100km: float|null, cost_per_km: float|null, total_cost: float, total_liters: float, average_price: float|null, latest_price: float|null, by_fill: array<int, float>}
     */
    public static function economyReport(): array
    {
        $fills = static::orderBy('odometer_km')->get();

        $distance = $liters = $cost = 0.0;
        $byFill = [];
        $previousFull = null;
        $pendingLiters = $pendingCost = 0.0;

        foreach ($fills as $fill) {
            $pendingLiters += $fill->liters;
            $pendingCost += $fill->total_cost;

            if (! $fill->is_full_tank) {
                continue;
            }

            $segmentDistance = $previousFull ? $fill->odometer_km - $previousFull->odometer_km : 0;

            if ($previousFull && $segmentDistance > 0 && $pendingLiters > 0) {
                $byFill[$fill->id] = $segmentDistance / $pendingLiters;
                $distance += $segmentDistance;
                $liters += $pendingLiters;
                $cost += $pendingCost;
            }

            $previousFull = $fill;
            $pendingLiters = $pendingCost = 0.0;
        }

        return [
            'km_per_liter' => $liters > 0 ? $distance / $liters : null,
            'liters_per_100km' => $distance > 0 ? $liters / $distance * 100 : null,
            'cost_per_km' => $distance > 0 ? $cost / $distance : null,
            'total_cost' => (float) $fills->sum('total_cost'),
            'total_liters' => (float) $fills->sum('liters'),
            'average_price' => $fills->sum('liters') > 0 && $fills->sum('total_cost') > 0 ? $fills->sum('total_cost') / $fills->sum('liters') : null,
            'latest_price' => $fills->sortBy([['filled_on', 'desc'], ['odometer_km', 'desc']])->first(fn (FuelFill $fill): bool => $fill->price_per_liter !== null)?->price_per_liter,
            'by_fill' => $byFill,
        ];
    }
}
