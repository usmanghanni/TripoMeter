<?php

namespace App\Models;

use App\Models\Concerns\BelongsToVehicle;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A car expense other than fuel: oil changes, services, tyres, insurance...
 */
class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use BelongsToVehicle, HasFactory;

    /**
     * @var array<string, string>
     */
    public const CATEGORIES = [
        'oil_change' => 'Oil change',
        'service' => 'Service',
        'tyres' => 'Tyres',
        'repair' => 'Repair',
        'insurance' => 'Insurance',
        'tax' => 'Tax & fees',
        'wash' => 'Wash',
        'parking' => 'Parking & tolls',
        'other' => 'Other',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = ['vehicle_id', 'spent_on', 'category', 'title', 'amount', 'odometer_km', 'next_due_km', 'notes'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'spent_on' => 'date',
            'amount' => 'float',
            'odometer_km' => 'float',
            'next_due_km' => 'float',
        ];
    }

    protected function categoryLabel(): Attribute
    {
        return Attribute::get(fn (): string => self::CATEGORIES[$this->category] ?? self::CATEGORIES['other']);
    }

    /**
     * Total spent per category since a date, largest first.
     *
     * @return array<string, float>
     */
    public static function totalsByCategory(\DateTimeInterface $since): array
    {
        return static::forVehicle()->whereDate('spent_on', '>=', $since)
            ->get(['category', 'amount'])
            ->groupBy('category')
            ->map(fn ($expenses): float => (float) $expenses->sum('amount'))
            ->sortDesc()
            ->all();
    }
}
