<?php

use App\Tasks\LookUpSpeedLimit;
use Illuminate\Support\Facades\Http;

it('parses OSM maxspeed tags to km/h', function (?string $tag, ?int $expected) {
    expect(LookUpSpeedLimit::parseMaxSpeed($tag))->toBe($expected);
})->with([
    ['50', 50],
    ['30 mph', 48],
    ['100 km/h', 100],
    ['none', null],
    ['DE:urban', null],
    [null, null],
]);

it('returns the limit of the nearest tagged road', function () {
    Http::fake([LookUpSpeedLimit::ENDPOINT => Http::response(['elements' => [['tags' => ['highway' => 'primary', 'maxspeed' => '60']]]])]);

    expect((new LookUpSpeedLimit)->handle(51.5, -0.12))->toBe(60);

    Http::assertSent(fn ($request) => str_contains($request['data'], 'around:20,51.500000,-0.120000'));
});

it('returns null when no road or the API fails', function () {
    Http::fake([LookUpSpeedLimit::ENDPOINT => Http::sequence()->push(['elements' => []])->pushStatus(429)]);

    expect((new LookUpSpeedLimit)->handle(0, 0))->toBeNull()
        ->and((new LookUpSpeedLimit)->handle(0, 0))->toBeNull();
});
