<?php

use App\Models\Setting;
use App\NativeComponents\About;
use App\NativeComponents\Speedometer;
use App\Support\OpenSourceLicenses;
use App\Support\UpdateCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Native\Mobile\AsyncTask;
use Native\Mobile\Testing\Native;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->release = [
        'tag_name' => 'v1.1.12',
        'html_url' => 'https://github.com/usmanghanni/TripoMeter/releases/tag/v1.1.12',
        'body' => 'Fixes walking being recorded as trips.',
        'assets' => [
            ['name' => 'Tripometer-1.1.12.apk', 'browser_download_url' => 'https://example.test/Tripometer-1.1.12.apk'],
            ['name' => 'Tripometer-1.1.12-debug.apk', 'browser_download_url' => 'https://example.test/Tripometer-1.1.12-debug.apk'],
            ['name' => 'checksums.txt', 'browser_download_url' => 'https://example.test/checksums.txt'],
        ],
    ];

    $this->github = Http::response($this->release);
    Http::fake([UpdateCheck::ENDPOINT => fn () => $this->github]);

    $this->installed = fn (string $version, bool $debug = false) => Native::fakeBridge()
        ->respondTo('SpeedTracker.AppInfo', ['version' => $version, 'code' => 10, 'debug' => $debug]);
});

it('compares only versioned release builds', function (string $latest, string $installed, bool $expected) {
    expect(UpdateCheck::isNewer($latest, $installed))->toBe($expected);
})->with([
    'newer build' => ['1.1.12', '1.1.10', true],
    'newer minor' => ['1.2.0', '1.1.99', true],
    'same' => ['1.1.12', '1.1.12', false],
    'older' => ['1.1.9', '1.1.12', false],
    'debug placeholder' => ['1.1.12', 'DEBUG', false],
    'manual build' => ['1.1.12', '0.0.0-abc1234', false],
]);

it('offers the release APK to release builds and the debug APK to debug builds', function () {
    UpdateCheck::fetchLatest();

    ($this->installed)('1.1.10');
    expect(UpdateCheck::available())
        ->version->toBe('1.1.12')
        ->installed->toBe('1.1.10')
        ->url->toBe('https://example.test/Tripometer-1.1.12.apk');

    ($this->installed)('1.1.10-debug', debug: true);
    expect(UpdateCheck::available())
        ->installed->toBe('1.1.10')
        ->url->toBe('https://example.test/Tripometer-1.1.12-debug.apk');
});

it('offers nothing when already up to date or when GitHub is unreachable', function () {
    ($this->installed)('1.1.12');
    UpdateCheck::fetchLatest();

    expect(UpdateCheck::available())->toBeNull();

    $this->github = Http::response(['message' => 'Not Found'], 404); // private repo
    ($this->installed)('1.1.1');

    expect(UpdateCheck::fetchLatest())->toBeNull()
        ->and(UpdateCheck::available())->toBeNull()
        ->and(UpdateCheck::isDue())->toBeFalse(); // failures wait a day too
});

it('checks in the background once a day and shows the update on the speed screen', function () {
    ($this->installed)('1.1.10');
    $tasks = AsyncTask::fake();

    Native::test(Speedometer::class)
        ->assertSee('Update available: 1.1.12')
        ->assertSee('You have 1.1.10')
        ->tap('Download');

    Native::test(Speedometer::class); // checked already today

    $tasks->assertDispatchedTimes(1);
    Http::assertSentCount(1);
});

it('hides an update after Later, and while recording', function () {
    ($this->installed)('1.1.10');
    AsyncTask::fake();

    Native::test(Speedometer::class)
        ->assertSee('Update available: 1.1.12')
        ->tap('Start trip')
        ->assertDontSee('Update available')
        ->tap('Stop trip')
        ->assertSee('Update available: 1.1.12')
        ->tap('Later')
        ->assertDontSee('Update available');

    Native::test(Speedometer::class)->assertDontSee('Update available');
});

it('skips update checks when turned off or the build has no version', function () {
    $tasks = AsyncTask::fake();
    Native::fakeBridge()->withoutCapability('SpeedTracker.AppInfo');

    Native::test(Speedometer::class)->assertDontSee('Update available');

    ($this->installed)('1.1.10');
    Setting::current()->update(['update_checks' => false]);

    Native::test(Speedometer::class)->assertDontSee('Update available');

    $tasks->assertDispatchedTimes(0);
});

it('checks for updates from the about screen', function () {
    ($this->installed)('1.1.12');
    AsyncTask::fake();

    Native::visit('/about')
        ->assertSee('Version 1.1.12')
        ->tap('Check for updates')
        ->assertSee('You have the latest version.')
        ->toggle("__syncProperty('updateChecks')", false);

    expect(Setting::current()->update_checks)->toBeFalse();

    // An older build: the release it already fetched is offered straight away.
    ($this->installed)('1.1.10');

    Native::test(About::class)
        ->assertSee('Version 1.1.12 is available (you have 1.1.10).')
        ->assertSee('Fixes walking being recorded as trips.')
        ->assertSee('Download 1.1.12');
});

it('credits open source software with up-to-date licences', function () {
    $listed = json_decode(file_get_contents(base_path(OpenSourceLicenses::PATH)), true);

    expect($listed)->toBe(OpenSourceLicenses::fromComposerLock(base_path('composer.lock')))
        ->and(collect($listed)->firstWhere('name', 'laravel/framework')['license'])->toBe('MIT');

    Native::visit('/about')
        ->assertSee('Open source licences')
        ->assertSee('laravel/framework')
        ->assertSee('Google Play services Location');
})->note('If this fails after changing dependencies, run php artisan licenses:generate.');

it('opens about from settings', function () {
    Native::visit('/settings')->call('openAbout')->assertNavigatedTo('/about');
});
