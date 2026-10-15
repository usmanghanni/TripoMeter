<?php

namespace App\Models;

use Database\Factories\FuelFillFactory;
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
     * Fuel economy using the full-tank-to-full-tank method: the fuel added from one
     * full fill up to and including the next full fill was burned over the
     * odometer distance between them. Fills before the first full tank are ignored.
     *
     * @return array{km_per_liter: float|null, liters_per_100km: float|null, cost_per_km: float|null, total_cost: float, total_liters: float, by_fill: array<int, float>}
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
            'by_fill' => $byFill,
        ];
    }
}
