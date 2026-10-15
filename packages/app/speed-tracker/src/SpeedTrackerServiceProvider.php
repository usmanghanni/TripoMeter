<?php

namespace App\SpeedTracker;

use Illuminate\Support\ServiceProvider;

/**
 * Registers the plugin so its native code is compiled in. The PHP API is core's
 * Native\Mobile\Facades\Geolocation; this plugin only supplies the native side.
 */
class SpeedTrackerServiceProvider extends ServiceProvider {}
