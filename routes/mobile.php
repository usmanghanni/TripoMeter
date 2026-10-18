<?php

use App\Layouts\TabsLayout;
use App\NativeComponents\ExpenseForm;
use App\NativeComponents\ExpenseLog;
use App\NativeComponents\ExportData;
use App\NativeComponents\FuelFillForm;
use App\NativeComponents\FuelHistory;
use App\NativeComponents\FuelLog;
use App\NativeComponents\ImportData;
use App\NativeComponents\Settings;
use App\NativeComponents\Speedometer;
use App\NativeComponents\Stats;
use App\NativeComponents\TripDetail;
use App\NativeComponents\TripList;
use Illuminate\Support\Facades\Route;

Route::nativeGroup(TabsLayout::class, function () {
    Route::native('/', Speedometer::class);
    Route::native('/trips', TripList::class);
    Route::native('/trips/{id}', TripDetail::class);
    Route::native('/stats', Stats::class);
    Route::native('/fuel', FuelLog::class);
    Route::native('/fuel/add', FuelFillForm::class);
    Route::native('/fuel/history', FuelHistory::class);
    Route::native('/fuel/{id}', FuelFillForm::class);
    Route::native('/expenses', ExpenseLog::class);
    Route::native('/expenses/add', ExpenseForm::class);
    Route::native('/expenses/add/{category}', ExpenseForm::class);
    Route::native('/expenses/{id}', ExpenseForm::class);
    Route::native('/settings', Settings::class);
    Route::native('/backup/export', ExportData::class);
    Route::native('/backup/import', ImportData::class);
});
