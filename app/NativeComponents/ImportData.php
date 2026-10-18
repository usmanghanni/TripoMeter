<?php

namespace App\NativeComponents;

use App\Models\Setting;
use App\SpeedTracker\Events\FilePicked;
use App\SpeedTracker\SpeedTracker;
use App\Support\TripBackup;
use Illuminate\View\View;
use InvalidArgumentException;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Attributes\On;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Facades\Dialog;

/**
 * Open a backup file, choose which of its trips (and whether its fuel log)
 * to import. Trips and fill-ups already on the phone are skipped.
 */
class ImportData extends NativeComponent
{
    public ?string $filePath = null;

    public ?string $fileName = null;

    /**
     * @var list<array{index: int, title: string|null, started_at: string, distance_m: float, points: int, exists: bool}>
     */
    public array $fileTrips = [];

    /**
     * @var list<int>
     */
    public array $selectedIndexes = [];

    public int $carLogCount = 0;

    public int $newCarLogCount = 0;

    public bool $includeFuel = true;

    public ?string $error = null;

    public function navTitle(): string
    {
        return 'Import';
    }

    #[Computed]
    public function settings(): Setting
    {
        return Setting::current();
    }

    public function chooseFile(): void
    {
        $this->error = null;

        if (! SpeedTracker::pickFile()) {
            Dialog::alert('Update Tripometer', 'This build of the app cannot open files yet. Install the latest release and try again.');
        }
    }

    #[On(FilePicked::class)]
    public function filePicked(?string $path = null, ?string $name = null, bool $cancelled = false, ?string $error = null): void
    {
        if ($cancelled) {
            return;
        }

        if ($path === null) {
            $this->error = $error ?? 'The file could not be opened.';

            return;
        }

        try {
            $summary = TripBackup::summarize(TripBackup::read($path));
        } catch (InvalidArgumentException $exception) {
            $this->clearFile();
            $this->error = $exception->getMessage();

            return;
        }

        $this->error = null;
        $this->filePath = $path;
        $this->fileName = $name;
        $this->fileTrips = $summary['trips'];
        $this->carLogCount = $summary['fuel_fills'] + $summary['expenses'];
        $this->newCarLogCount = $summary['new_fuel_fills'] + $summary['new_expenses'];
        $this->includeFuel = $this->newCarLogCount > 0;
        $this->selectedIndexes = array_column(array_filter($summary['trips'], fn (array $trip): bool => ! $trip['exists']), 'index');
    }

    public function toggleTrip(int $index): void
    {
        $trip = collect($this->fileTrips)->firstWhere('index', $index);

        if (! $trip || $trip['exists']) {
            return;
        }

        $this->selectedIndexes = in_array($index, $this->selectedIndexes, true)
            ? array_values(array_diff($this->selectedIndexes, [$index]))
            : [...$this->selectedIndexes, $index];
    }

    public function selectAll(): void
    {
        $this->selectedIndexes = array_column(array_filter($this->fileTrips, fn (array $trip): bool => ! $trip['exists']), 'index');
    }

    public function selectNone(): void
    {
        $this->selectedIndexes = [];
    }

    public function import(): void
    {
        if ($this->filePath === null) {
            return;
        }

        $includeFuel = $this->includeFuel && $this->newCarLogCount > 0;

        if ($this->selectedIndexes === [] && ! $includeFuel) {
            Dialog::toast('Choose at least one trip or the fuel log and expenses');

            return;
        }

        try {
            $result = TripBackup::import(TripBackup::read($this->filePath), $this->selectedIndexes, $includeFuel);
        } catch (InvalidArgumentException $exception) {
            $this->error = $exception->getMessage();

            return;
        }

        @unlink($this->filePath);
        $this->clearFile();

        Dialog::alert('Import complete', collect([
            "{$result['trips']} ".str('trip')->plural($result['trips']),
            $includeFuel ? "{$result['fuel_fills']} ".str('fill-up')->plural($result['fuel_fills']) : null,
            $includeFuel ? "{$result['expenses']} ".str('expense')->plural($result['expenses']) : null,
        ])->filter()->join(', ', ' and ').' imported.'.($result['skipped'] ? " {$result['skipped']} already on this phone were skipped." : ''));
    }

    private function clearFile(): void
    {
        $this->filePath = $this->fileName = null;
        $this->fileTrips = $this->selectedIndexes = [];
        $this->carLogCount = $this->newCarLogCount = 0;
        $this->includeFuel = true;
    }

    public function render(): View
    {
        return view('native.import-data');
    }
}
