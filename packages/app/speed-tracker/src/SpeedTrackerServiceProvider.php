<?php

namespace App\SpeedTracker;

use App\SpeedTracker\Commands\InstallAndroidWidget;
use Illuminate\Support\ServiceProvider;
use Native\Mobile\Plugins\Commands\NativePluginHookCommand;

/**
 * Registers the plugin so its native code is compiled in. The PHP API is core's
 * Native\Mobile\Facades\Geolocation plus App\SpeedTracker\SpeedTracker; this
 * plugin supplies the native side and the widget build hook.
 */
class SpeedTrackerServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole() && class_exists(NativePluginHookCommand::class)) {
            $this->commands([InstallAndroidWidget::class]);
        }
    }
}
