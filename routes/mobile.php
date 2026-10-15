<?php

use App\Layouts\TabsLayout;
use App\NativeComponents\AddFuelFill;
use App\NativeComponents\FuelLog;
use App\NativeComponents\Settings;
use App\NativeComponents\Speedometer;
use App\NativeComponents\Stats;
use App\NativeComponents\TripList;
use Illuminate\Support\Facades\Route;

Route::nativeGroup(TabsLayout::class, function () {
    Route::native('/', Speedometer::class);
    Route::native('/trips', TripList::class);
    Route::native('/stats', Stats::class);
    Route::native('/fuel', FuelLog::class);
    Route::native('/fuel/add', AddFuelFill::class);
    Route::native('/settings', Settings::class);
});
