<?php

namespace App\NativeComponents;

use App\Models\FuelFill;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

class AddFuelFill extends NativeComponent
{
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
    }

    public function navTitle(): string
    {
        return 'Add fill-up';
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

        FuelFill::create([...$validator->validated(), 'is_full_tank' => $this->isFullTank]);

        $this->back();
    }

    public function render(): View
    {
        return view('native.add-fuel-fill', [
            'lastOdometer' => FuelFill::max('odometer_km'),
        ]);
    }
}
