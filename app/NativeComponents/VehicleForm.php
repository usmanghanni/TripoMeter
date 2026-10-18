<?php

namespace App\NativeComponents;

use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Facades\Dialog;

/**
 * Adds a vehicle (/vehicles/add) or edits, selects and deletes one
 * (/vehicles/{id}).
 */
class VehicleForm extends NativeComponent
{
    public ?int $vehicleId = null;

    public string $name = '';

    /**
     * Tank size in litres as typed; empty when unknown.
     */
    public string $tankCapacity = '';

    /**
     * Oil change interval as typed; 0 or empty turns that limit off.
     */
    public string $oilIntervalKm = '5000';

    public string $oilIntervalMonths = '6';

    /**
     * @var array<string, string>
     */
    public array $fieldErrors = [];

    public function mount(): void
    {
        $vehicle = $this->param('id') ? Vehicle::find($this->param('id')) : null;

        if ($vehicle) {
            $this->vehicleId = $vehicle->id;
            $this->name = $vehicle->name;
            $this->tankCapacity = $vehicle->tank_capacity_l ? rtrim(rtrim(number_format($vehicle->tank_capacity_l, 1, '.', ''), '0'), '.') : '';
            $this->oilIntervalKm = (string) $vehicle->oil_change_interval_km;
            $this->oilIntervalMonths = (string) $vehicle->oil_change_interval_months;
        }
    }

    public function navTitle(): string
    {
        return $this->vehicleId ? 'Edit vehicle' : 'Add vehicle';
    }

    public function save(): void
    {
        $validator = Validator::make([
            'name' => trim($this->name),
            'tank_capacity_l' => trim($this->tankCapacity) === '' ? null : trim($this->tankCapacity),
            'oil_change_interval_km' => trim($this->oilIntervalKm) === '' ? '0' : trim($this->oilIntervalKm),
            'oil_change_interval_months' => trim($this->oilIntervalMonths) === '' ? '0' : trim($this->oilIntervalMonths),
        ], [
            'name' => ['required', 'string', 'max:40'],
            'tank_capacity_l' => ['nullable', 'numeric', 'gt:0', 'max:500'],
            'oil_change_interval_km' => ['integer', 'min:0', 'max:100000'],
            'oil_change_interval_months' => ['integer', 'min:0', 'max:60'],
        ], [], [
            'tank_capacity_l' => 'tank size',
            'oil_change_interval_km' => 'oil change distance',
            'oil_change_interval_months' => 'oil change months',
        ]);

        if ($validator->fails()) {
            $this->fieldErrors = array_map(fn (array $messages): string => $messages[0], $validator->errors()->toArray());

            return;
        }

        $vehicle = Vehicle::updateOrCreate(['id' => $this->vehicleId], $validator->validated());

        // A new vehicle is usually added to start using it.
        if ($this->vehicleId === null) {
            $vehicle->makeCurrent();
        }

        $this->back();
    }

    public function makeCurrent(): void
    {
        Vehicle::find($this->vehicleId)?->makeCurrent();
        Dialog::toast('Now recording for '.$this->name);
    }

    public function confirmDelete(): void
    {
        $vehicle = Vehicle::withCount(['trips', 'fuelFills', 'expenses'])->find($this->vehicleId);

        if (! $vehicle) {
            return;
        }

        if (Vehicle::count() === 1) {
            Dialog::alert('Can\'t delete', 'You need at least one vehicle. Rename this one instead.');

            return;
        }

        Dialog::alert(
            "Delete {$vehicle->name}?",
            "This also deletes its {$vehicle->trips_count} trips, {$vehicle->fuel_fills_count} fill-ups and {$vehicle->expenses_count} expenses.",
            ['Cancel', 'Delete'],
        )
            ->id('delete-vehicle')
            ->buttonPressed(function (object $event) use ($vehicle): void {
                if (($event->label ?? null) !== 'Delete') {
                    return;
                }

                DB::transaction(function () use ($vehicle): void {
                    $vehicle->trips()->delete();
                    $vehicle->fuelFills()->delete();
                    $vehicle->expenses()->delete();
                    $vehicle->delete();
                });

                $this->back();
            });
    }

    public function render(): View
    {
        return view('native.vehicle-form', [
            'isCurrent' => $this->vehicleId !== null && Vehicle::current()->id === $this->vehicleId,
        ]);
    }
}
