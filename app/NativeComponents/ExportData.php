<?php

namespace App\NativeComponents;

use App\Models\FuelFill;
use App\Models\Setting;
use App\Models\Trip;
use App\SpeedTracker\Events\FileSaved;
use App\SpeedTracker\SpeedTracker;
use App\Support\TripBackup;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Attributes\On;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Facades\Dialog;

/**
 * Pick trips (all by default) and whether to include the fuel log, then save
 * them as a JSON backup wherever the user chooses.
 */
class ExportData extends NativeComponent
{
    /**
     * @var list<int>
     */
    public array $selectedTripIds = [];

    public bool $includeFuel = true;

    public bool $isSaving = false;

    public function mount(): void
    {
        $this->selectedTripIds = $this->trips->modelKeys();
    }

    public function navTitle(): string
    {
        return 'Export';
    }

    /**
     * @return Collection<int, Trip>
     */
    #[Computed]
    public function trips(): Collection
    {
        return Trip::whereNotNull('ended_at')->latest('started_at')->get();
    }

    #[Computed]
    public function fuelFillCount(): int
    {
        return FuelFill::count();
    }

    #[Computed]
    public function settings(): Setting
    {
        return Setting::current();
    }

    public function toggleTrip(int $tripId): void
    {
        $this->selectedTripIds = in_array($tripId, $this->selectedTripIds, true)
            ? array_values(array_diff($this->selectedTripIds, [$tripId]))
            : [...$this->selectedTripIds, $tripId];
    }

    public function selectAll(): void
    {
        $this->selectedTripIds = $this->trips->modelKeys();
    }

    public function selectNone(): void
    {
        $this->selectedTripIds = [];
    }

    public function export(): void
    {
        $includeFuel = $this->includeFuel && $this->fuelFillCount > 0;

        if ($this->selectedTripIds === [] && ! $includeFuel) {
            Dialog::toast('Choose at least one trip or the fuel log');

            return;
        }

        $path = TripBackup::write(TripBackup::build($this->selectedTripIds, $includeFuel));

        if (! SpeedTracker::saveFile($path, basename($path))) {
            Dialog::alert('Update Tripometer', 'This build of the app cannot save files yet. Install the latest release and try again.');

            return;
        }

        $this->isSaving = true;
    }

    #[On(FileSaved::class)]
    public function fileSaved(bool $saved = false, bool $cancelled = false, ?string $error = null): void
    {
        $this->isSaving = false;

        if ($saved) {
            Dialog::toast('Backup saved');
        } elseif (! $cancelled) {
            Dialog::alert('Export failed', $error ?? 'The backup could not be saved.');
        }
    }

    public function render(): View
    {
        return view('native.export-data');
    }
}
