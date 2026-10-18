<?php

namespace App\SpeedTracker\Commands;

use Native\Mobile\Plugins\Commands\NativePluginHookCommand;

/**
 * Build hook: installs the home screen widget into the Android project. The
 * plugin manifest can't declare res/layout + res/xml resources or a
 * receiver's <meta-data>, which an app widget needs, so this copies the
 * resources and adds the receiver itself. Safe to run more than once.
 */
class InstallAndroidWidget extends NativePluginHookCommand
{
    protected $signature = 'nativephp:speed-tracker:install-widget';

    protected $description = 'Install the Tripometer home screen widget into the Android project';

    private const RECEIVER = <<<'XML'
        <receiver
            android:name="com.app.plugins.speedtracker.SpeedTrackerWidget"
            android:exported="true"
            android:label="Tripometer">
            <intent-filter>
                <action android:name="android.appwidget.action.APPWIDGET_UPDATE" />
            </intent-filter>
            <meta-data
                android:name="android.appwidget.provider"
                android:resource="@xml/speedtracker_widget_info" />
        </receiver>
    XML;

    public function handle(): int
    {
        if (! $this->isAndroid()) {
            return self::SUCCESS;
        }

        $mainPath = $this->androidMainPath();

        if ($mainPath === null) {
            $this->warn('Tripometer widget: AndroidManifest.xml not found, widget not installed.');

            return self::SUCCESS;
        }

        foreach (glob($this->pluginPath().'/resources/widget/*/*.xml') ?: [] as $source) {
            $target = $mainPath.'/res/'.basename(dirname($source)).'/'.basename($source);

            if (! is_dir(dirname($target))) {
                mkdir(dirname($target), 0755, true);
            }

            copy($source, $target);
        }

        $manifestPath = $mainPath.'/AndroidManifest.xml';
        $manifest = (string) file_get_contents($manifestPath);

        if (! str_contains($manifest, 'com.app.plugins.speedtracker.SpeedTrackerWidget')) {
            $manifest = preg_replace('~</application>~', self::RECEIVER."\n    </application>", $manifest, 1);
            file_put_contents($manifestPath, $manifest);
            $this->info('Tripometer widget installed.');
        }

        return self::SUCCESS;
    }

    private function androidMainPath(): ?string
    {
        foreach ([$this->buildPath().'/app/src/main', base_path('nativephp/android/app/src/main')] as $path) {
            if (is_file($path.'/AndroidManifest.xml')) {
                return $path;
            }
        }

        return null;
    }
}
