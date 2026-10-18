<?php

use App\Models\Expense;
use App\Models\FuelFill;
use App\Models\Trip;
use App\NativeComponents\ExportData;
use App\NativeComponents\ImportData;
use App\SpeedTracker\Events\FilePicked;
use App\SpeedTracker\Events\FileSaved;
use App\Support\TripBackup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Native\Mobile\Testing\Native;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->bridge = Native::fakeBridge();

    $this->makeTrip = function (string $startedAt, string $title): Trip {
        $trip = Trip::factory()->create(['started_at' => $startedAt, 'title' => $title, 'notes' => 'Notes for '.$title]);
        $start = $trip->started_at->getTimestampMs();

        $trip->recordFixes([
            ['latitude' => 0.0, 'longitude' => 0.0, 'speed' => 10.0, 'accuracy' => 5, 'heading' => 90.0, 'altitude' => 12.5, 'timestamp' => $start],
            ['latitude' => 0.001, 'longitude' => 0.0, 'speed' => 12.0, 'accuracy' => 5, 'heading' => 92.0, 'altitude' => 13.0, 'timestamp' => $start + 10_000],
        ]);
        $trip->update(['ended_at' => $trip->started_at->copy()->addMinutes(5)]);

        return $trip->fresh();
    };

    $this->backupFile = function (array $backup): string {
        $path = tempnam(sys_get_temp_dir(), 'backup');
        file_put_contents($path, json_encode($backup));

        return $path;
    };
});

it('round-trips selected trips with their points and the fuel log', function () {
    $commute = ($this->makeTrip)('2026-10-01 08:00:00', 'Commute');
    ($this->makeTrip)('2026-10-02 08:00:00', 'Not exported');
    FuelFill::factory()->create(['filled_on' => '2026-10-01', 'odometer_km' => 1000, 'liters' => 30, 'total_cost' => 3000]);
    Expense::factory()->oilChange()->create(['spent_on' => '2026-10-01', 'amount' => 4500, 'odometer_km' => 990, 'title' => '5W-30']);

    $path = TripBackup::write(TripBackup::build([$commute->id], includeFuel: true));
    $backup = TripBackup::read($path);

    expect($backup['trips'])->toHaveCount(1)
        ->and($backup['trips'][0]['points'])->toHaveCount(2)
        ->and($backup['fuel_fills'])->toHaveCount(1)
        ->and($backup['expenses'])->toHaveCount(1);

    Trip::query()->delete();
    FuelFill::query()->delete();
    Expense::query()->delete();

    $result = TripBackup::import($backup, [0], includeFuel: true);

    $imported = Trip::with('points')->sole();

    expect($result)->toBe(['trips' => 1, 'fuel_fills' => 1, 'expenses' => 1, 'skipped' => 0])
        ->and(Expense::sole())->category->toBe('oil_change')->title->toBe('5W-30')->odometer_km->toBe(990.0)
        ->and($imported->title)->toBe('Commute')
        ->and($imported->notes)->toBe('Notes for Commute')
        ->and($imported->started_at->toDateTimeString())->toBe('2026-10-01 08:00:00')
        ->and($imported->distance_m)->toEqualWithDelta($commute->distance_m, 0.001)
        ->and($imported->points)->toHaveCount(2)
        ->and($imported->points[1]->speed_mps)->toEqualWithDelta(12.0, 0.001)
        ->and($imported->points[1]->heading)->toEqualWithDelta(92.0, 0.001)
        ->and($imported->points[1]->altitude)->toEqualWithDelta(13.0, 0.001)
        ->and(FuelFill::sole()->odometer_km)->toBe(1000.0);
});

it('skips trips and fill-ups already on the phone', function () {
    $trip = ($this->makeTrip)('2026-10-01 08:00:00', 'Commute');
    FuelFill::factory()->create(['filled_on' => '2026-10-01', 'odometer_km' => 1000]);

    $backup = TripBackup::build([$trip->id], includeFuel: true);

    $summary = TripBackup::summarize($backup);

    expect($summary['trips'][0]['exists'])->toBeTrue()
        ->and($summary['new_fuel_fills'])->toBe(0);

    expect(TripBackup::import($backup, [0], includeFuel: true))->toBe(['trips' => 0, 'fuel_fills' => 0, 'expenses' => 0, 'skipped' => 2])
        ->and(Trip::count())->toBe(1)
        ->and(FuelFill::count())->toBe(1);
});

it('rejects files that are not backups', function (string $contents, string $message) {
    $path = tempnam(sys_get_temp_dir(), 'backup');
    file_put_contents($path, $contents);

    expect(fn () => TripBackup::read($path))->toThrow(InvalidArgumentException::class, $message);
})->with([
    'not json' => ['hello', 'not a Tripometer backup'],
    'other json' => ['{"foo": 1}', 'not a Tripometer backup'],
    'newer version' => ['{"format": "tripometer-backup", "version": 99, "trips": [], "fuel_fills": []}', 'newer version'],
    'trip without start' => ['{"format": "tripometer-backup", "version": 1, "trips": [{"title": "x"}], "fuel_fills": []}', 'started_at field is required'],
]);

it('exports the selected trips through the save dialog', function () {
    $kept = ($this->makeTrip)('2026-10-01 08:00:00', 'Kept');
    $dropped = ($this->makeTrip)('2026-10-02 08:00:00', 'Dropped');

    $screen = Native::test(ExportData::class)
        ->assertSee('2 of 2 selected')
        ->call('toggleTrip', $dropped->id)
        ->assertSee('1 of 2 selected')
        ->tap('Export')
        ->assertSet('isSaving', true);

    $this->bridge->assertCalled('SpeedTracker.SaveFile', function (array $params) use ($kept) {
        $backup = TripBackup::read($params['path']);

        return str_starts_with($params['name'], 'tripometer-')
            && count($backup['trips']) === 1
            && $backup['trips'][0]['title'] === $kept->title;
    });

    $screen->emitNative(FileSaved::class, ['saved' => true])->assertSet('isSaving', false);

    $this->bridge->assertCalled('Dialog.Toast');
});

it('needs something selected to export', function () {
    ($this->makeTrip)('2026-10-01 08:00:00', 'Commute');

    Native::test(ExportData::class)->call('selectNone')->set('includeFuel', false)->tap('Export')->assertSet('isSaving', false);

    $this->bridge->assertCalledTimes('SpeedTracker.SaveFile', 0);
});

it('explains when the build cannot save files', function () {
    ($this->makeTrip)('2026-10-01 08:00:00', 'Commute');
    $this->bridge->withoutCapability('SpeedTracker.SaveFile');

    Native::test(ExportData::class)->tap('Export')->assertSet('isSaving', false);

    $this->bridge->assertCalledTimes('SpeedTracker.SaveFile', 0);
});

it('imports only the trips chosen from a picked file', function () {
    $first = ($this->makeTrip)('2026-10-01 08:00:00', 'First');
    $second = ($this->makeTrip)('2026-10-02 08:00:00', 'Second');
    FuelFill::factory()->create(['filled_on' => '2026-10-01', 'odometer_km' => 1000]);
    $path = ($this->backupFile)(TripBackup::build([$first->id, $second->id], includeFuel: true));
    Trip::query()->delete();
    FuelFill::query()->delete();

    $screen = Native::test(ImportData::class)->tap('Choose backup file');

    $this->bridge->assertCalled('SpeedTracker.PickFile');

    $screen->emitNative(FilePicked::class, ['path' => $path, 'name' => 'backup.json'])
        ->assertSee('backup.json')
        ->assertSee('First')
        ->assertSee('Second')
        ->assertSet('selectedIndexes', [0, 1])
        ->call('toggleTrip', 1)
        ->set('includeFuel', false)
        ->tap('Import');

    expect(Trip::pluck('title')->all())->toBe(['First'])
        ->and(FuelFill::count())->toBe(0)
        ->and(Trip::sole()->points()->count())->toBe(2);

    $screen->assertSet('filePath', null);
});

it('shows why a picked file cannot be imported', function () {
    $path = ($this->backupFile)(['foo' => 'bar']);

    Native::test(ImportData::class)
        ->emitNative(FilePicked::class, ['path' => $path])
        ->assertSee('This file is not a Tripometer backup.')
        ->assertSet('filePath', null);
});

it('opens export and import from settings', function () {
    Native::visit('/settings')->call('openExport')->assertNavigatedTo('/backup/export');
    Native::visit('/settings')->call('openImport')->assertNavigatedTo('/backup/import');
});
