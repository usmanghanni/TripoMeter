<?php

namespace App\NativeComponents;

use App\Models\FuelFill;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Facades\Dialog;

/**
 * Adds a fill-up (/fuel/add) or edits and deletes one (/fuel/{id}).
 */
class FuelFillForm extends NativeComponent
{
    public ?int $fillId = null;

    public string $filledOn = '';

    public string $odometer = '';

    public string $liters = '';

    public string $totalCost = '';

    public bool $isFullTank = true;

    /**
     * @var array<string, string>
     */
    public array $fieldErrors = [];

    public function mount(): void
    {
        $this->filledOn = today()->toDateString();

        $fill = $this->param('id') ? FuelFill::find($this->param('id')) : null;

        if ($fill) {
            $this->fillId = $fill->id;
            $this->filledOn = $fill->filled_on->toDateString();
            $this->odometer = self::formatNumber($fill->odometer_km);
            $this->liters = self::formatNumber($fill->liters);
            $this->totalCost = self::formatNumber($fill->total_cost);
            $this->isFullTank = $fill->is_full_tank;
        }
    }

    public function navTitle(): string
    {
        return $this->fillId ? 'Edit fill-up' : 'Add fill-up';
    }

    public function save(): void
    {
        $validator = Validator::make([
            'filled_on' => $this->filledOn,
            'odometer_km' => $this->odometer,
            'liters' => $this->liters,
            'total_cost' => $this->totalCost === '' ? '0' : $this->totalCost,
        ], [
            'filled_on' => ['required', 'date', 'before_or_equal:today'],
            'odometer_km' => ['required', 'numeric', 'gt:0'],
            'liters' => ['required', 'numeric', 'gt:0', 'max:500'],
            'total_cost' => ['numeric', 'min:0'],
        ], [], [
            'odometer_km' => 'odometer',
            'total_cost' => 'total cost',
        ]);

        if ($validator->fails()) {
            $this->fieldErrors = array_map(fn (array $messages): string => $messages[0], $validator->errors()->toArray());

            return;
        }

        FuelFill::updateOrCreate(['id' => $this->fillId], [...$validator->validated(), 'is_full_tank' => $this->isFullTank]);

        $this->back();
    }

    public function confirmDelete(): void
    {
        Dialog::alert('Delete fill-up?', 'This removes it from your fuel history and average.', ['Cancel', 'Delete'])
            ->id('delete-fuel-fill')
            ->buttonPressed(function (object $event): void {
                if (($event->label ?? null) === 'Delete') {
                    FuelFill::whereKey($this->fillId)->delete();
                    $this->back();
                }
            });
    }

    private static function formatNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    public function render(): View
    {
        return view('native.fuel-fill-form', [
            'lastOdometer' => $this->fillId ? null : FuelFill::max('odometer_km'),
            'pricePerLiter' => is_numeric($this->liters) && is_numeric($this->totalCost) && (float) $this->liters > 0 && (float) $this->totalCost > 0
                ? (float) $this->totalCost / (float) $this->liters
                : null,
        ]);
    }
}
