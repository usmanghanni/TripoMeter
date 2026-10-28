<?php

use App\Support\OpenSourceLicenses;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('licenses:generate', function () {
    $packages = OpenSourceLicenses::fromComposerLock(base_path('composer.lock'));

    file_put_contents(
        base_path(OpenSourceLicenses::PATH),
        json_encode($packages, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n",
    );

    $this->info(count($packages).' packages written to '.OpenSourceLicenses::PATH);
})->purpose('Refresh the open source licences shown in the app from composer.lock');
