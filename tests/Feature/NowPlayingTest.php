<?php

use App\NativeComponents\Speedometer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Native\Mobile\Testing\Native;

uses(RefreshDatabase::class);

it('asks for media access before showing music', function () {
    $bridge = Native::fakeBridge()->respondTo('SpeedTracker.NowPlaying', ['supported' => true, 'access' => false, 'active' => false]);

    Native::test(Speedometer::class)
        ->assertSee("Show what's playing")
        ->tap('Allow access');

    $bridge->assertCalled('SpeedTracker.OpenMediaAccessSettings');
});

it('shows the playing track and controls the music app', function () {
    $bridge = Native::fakeBridge()->respondTo('SpeedTracker.NowPlaying', [
        'supported' => true, 'access' => true, 'active' => true, 'playing' => true,
        'title' => 'Blinding Lights', 'artist' => 'The Weeknd', 'app' => 'Spotify',
    ]);

    $screen = Native::test(Speedometer::class)
        ->assertSee('Blinding Lights')
        ->assertSee('The Weeknd · Spotify');

    $screen->call('mediaCommand', 'next')->call('mediaCommand', 'toggle')->call('mediaCommand', 'rm -rf');

    $bridge->assertCalled('SpeedTracker.MediaCommand', fn (array $params) => $params['command'] === 'next');
    $bridge->assertCalled('SpeedTracker.MediaCommand', fn (array $params) => $params['command'] === 'toggle');
    $bridge->assertCalledTimes('SpeedTracker.MediaCommand', 2);
});

it('shows music controls in the HUD too', function () {
    Native::fakeBridge()->respondTo('SpeedTracker.NowPlaying', [
        'supported' => true, 'access' => true, 'active' => true, 'playing' => false,
        'title' => 'Blinding Lights', 'artist' => 'The Weeknd', 'app' => 'Spotify',
    ]);

    Native::test(Speedometer::class)
        ->call('toggleHud')
        ->assertSee('Blinding Lights')
        ->assertSee('Tap anywhere to exit');
});

it('hides the music card where media info is unsupported', function () {
    Native::fakeBridge()->respondTo('SpeedTracker.NowPlaying', ['supported' => false, 'access' => false, 'active' => false]);

    Native::test(Speedometer::class)
        ->assertDontSee("Show what's playing")
        ->assertSee('Start trip');
});

it('explains when this build lacks the music plugin code', function () {
    Native::fakeBridge()->withoutCapability('SpeedTracker.NowPlaying');

    Native::test(Speedometer::class)
        ->assertSee('Music controls unavailable')
        ->assertSee('Start trip');
});

it('shows the native error instead of hiding the card', function () {
    Native::fakeBridge()->respondTo('SpeedTracker.NowPlaying', ['status' => 'error', 'code' => 'UNKNOWN_ERROR', 'message' => 'Unexpected error: boom']);

    Native::test(Speedometer::class)
        ->assertSee('Music controls error')
        ->assertSee('Unexpected error: boom');
});

it('accepts a response wrapped in data', function () {
    Native::fakeBridge()->respondTo('SpeedTracker.NowPlaying', ['data' => [
        'supported' => true, 'access' => true, 'active' => true, 'playing' => true,
        'title' => 'Levitating', 'artist' => 'Dua Lipa', 'app' => 'YouTube Music',
    ]]);

    Native::test(Speedometer::class)->assertSee('Levitating');
});
