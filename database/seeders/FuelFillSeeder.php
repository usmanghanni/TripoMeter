<?php

namespace Database\Seeders;

use App\Models\FuelFill;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Historical fuel log, imported once via the seed_fuel_fills migration.
 */
class FuelFillSeeder extends Seeder
{
    /**
     * Rows: [odometer km, date, total cost, litres]. Every fill is treated as a full tank.
     *
     * @var list<array{0: int, 1: string, 2: float, 3: float}>
     */
    public const FILLS = [
        [47895, '2026-01-12', 6749.85, 26.47, true],
        [48046, '2026-01-16', 2514.6, 9.9, true],
        [48554, '2026-01-18', 3149.85, 12.45, false],
        [48747, '2026-01-19', 3498.87, 13.66, false],
        [49094, '2026-01-20', 2001.75, 7.85, false],
        [49266, '2026-01-27', 8153.4, 32.1, true],
        [49638, '2026-02-09', 5713.9, 22.47, true],
        [50000, '2026-02-17', 1999.2, 7.84, false],
        [50014, '2026-02-17', 3846.15, 14.85, true],
        [50264, '2026-02-25', 4003.75, 15.44, true],
        [50356, '2026-02-27', 1277.64, 4.68, true],
        [50515, '2026-02-27', 1827.76, 6.7, true],
        [50564, '2026-10-09', 5658.08, 14.13, true],
        [50803, '2026-03-02', 4280.9, 15.41, true],
        [51010, '2026-03-03', 3205.17, 11.99, true],
        [51255, '2026-03-11', 4544.85, 14.1, true],
        [51411, '2026-03-18', 1980.3, 6.15, true],
        [51570, '2026-03-18', 3001.81, 9.27, true],
        [51938, '2026-03-24', 5000.09, 15.42, false],
        [51966, '2026-03-24', 2998.88, 9.19, false],
        [52049, '2026-03-24', 1819.87, 5.62, true],
        [52163, '2026-03-24', 2506.35, 7.75, true],
        [52274, '2026-03-26', 2066.84, 6.41, true],
        [52467, '2026-03-28', 2775.67, 8.56, true],
        [52829, '2026-03-29', 6016.99, 18.61, false],
        [53249, '2026-03-30', 2499.38, 7.75, false],
        [53323, '2026-04-01', 6680.96, 20.72, true],
        [53648, '2026-04-14', 7010.72, 19.04, true],
        [53982, '2026-04-27', 1997.59, 5.06, false],
        [54020, '2026-04-28', 7754.87, 19.65, true],
        [54273, '2026-05-11', 6321.62, 15.19, true],
        [54390, '2026-05-15', 3484.35, 8.37, true],
        [54552, '2026-05-21', 3639.12, 8.85, true],
        [54723, '2026-05-22', 3941.16, 9.65, true],
        [55002, '2026-05-28', 8800.79, 21.56, true],
        [55252, '2026-05-30', 6170.25, 16.22, true],
        [55366, '2026-05-30', 2560.41, 6.77, true],
        [55559, '2026-06-04', 5126.95, 13.38, true],
        [55714, '2026-06-11', 4140.21, 10.92, true],
        [56008, '2026-06-22', 4997.12, 16.61, false],
        [56276, '2026-06-24', 4999.83, 16.5, true],
        [56621, '2026-06-30', 4999.06, 16.51, false],
        [56799, '2026-07-01', 2998.6, 10, false],
        [56906, '2026-07-01', 4299.85, 14.19, true],
        [57221, '2026-07-08', 5730.02, 19.18, true],
        [57504, '2026-07-18', 6073.83, 19.53, true],
        [57816, '2026-07-24', 2000, 6.25, false],
        [57930, '2026-07-25', 1794.78, 5.31, false],
        [58041, '2026-07-25', 6499.53, 19.18, true],
        [58381, '2026-08-03', 7165.53, 21.24, true],
        [58736, '2026-08-11', 6652.18, 20.22, true],
        [59055, '2026-08-17', 4996.77, 15.29, false],
        [59309, '2026-08-22', 1498.47, 4.37, false],
        [59366, '2026-08-25', 1373.6, 4, false],
        [59445, '2026-08-30', 7555.76, 21.99, true],
        [59797, '2026-09-11', 8547.14, 22.97, true],
        [60146, '2026-09-23', 999.49, 2.54, false],
        [60216, '2026-09-25', 996.57, 2.55, false],
        [60251, '2026-09-28', 1048.86, 2.67, false],
        [60314, '2026-09-30', 9118.39, 23.44, true],
        [60564, '2026-10-09', 5653.84, 14.13, true],
        [60731, '2026-10-09', 3774.2, 9.04, true],
        [61077, '2026-10-12', 8698.3, 21.73, true],
        [61286, '2026-10-14', 5300.45, 13.36, true],
        [61400, '2026-10-14', 2100.61, 5.33, true],
    ];

    /**
     * Insert fills whose odometer reading isn't stored yet, so re-running or
     * upgrading never duplicates entries the user already has.
     */
    public function run(): void
    {
        $existingOdometers = FuelFill::pluck('odometer_km')->map(fn (float $odometer): int => (int) round($odometer))->all();
        $now = now();

        // Run from its 2026_10_15 migration, vehicles don't exist yet (the
        // vehicles migration assigns these rows later); run after that, they
        // go to the current vehicle.
        $vehicle = Schema::hasColumn('fuel_fills', 'vehicle_id') ? ['vehicle_id' => Vehicle::current()->id] : [];

        $rows = collect(self::FILLS)
            ->reject(fn (array $fill): bool => in_array($fill[0], $existingOdometers, true))
            ->map(fn (array $fill): array => [
                'odometer_km' => $fill[0],
                'filled_on' => $fill[1],
                'total_cost' => $fill[2],
                'liters' => $fill[3],
                'is_full_tank' => $fill[4],
                'created_at' => $now,
                'updated_at' => $now,
                ...$vehicle,
            ]);

        FuelFill::insert($rows->all());
    }
}
