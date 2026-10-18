<?php

namespace App\Models;

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
    use HasFactory;

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
    protected $fillable = ['spent_on', 'category', 'title', 'amount', 'odometer_km', 'notes'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'spent_on' => 'date',
            'amount' => 'float',
            'odometer_km' => 'float',
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
        return static::whereDate('spent_on', '>=', $since)
            ->get(['category', 'amount'])
            ->groupBy('category')
            ->map(fn ($expenses): float => (float) $expenses->sum('amount'))
            ->sortDesc()
            ->all();
    }
}
