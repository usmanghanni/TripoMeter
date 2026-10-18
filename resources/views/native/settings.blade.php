@use('App\Icons\Ios')
@use('App\Icons\Android')

<scroll-view fill class="bg-theme-background">
    <column class="gap-6 p-6">
        <column class="gap-2">
            <text class="text-sm font-bold text-theme-on-surface">Units</text>
            <row class="w-full gap-2">
                @foreach ($units as $value => $label)
                    <button
                        variant="{{ $unit === $value ? 'primary' : 'ghost' }}"
                        @press="setUnit('{{ $value }}')"
                        a11y-label="Speed unit {{ $label }}{{ $unit === $value ? ', selected' : '' }}"
                        class="flex-1"
                    >{{ $label }}</button>
                @endforeach
            </row>
        </column>

        <column class="gap-2">
            <text class="text-sm font-bold text-theme-on-surface">Alert</text>
            <row class="w-full gap-2">
                @foreach ($alertModes as $value => $label)
                    <button
                        variant="{{ $alertMode === $value ? 'primary' : 'ghost' }}"
                        @press="setAlertMode('{{ $value }}')"
                        a11y-label="Alert {{ $label }}{{ $alertMode === $value ? ', selected' : '' }}"
                        class="flex-1"
                    >{{ $label }}</button>
                @endforeach
            </row>
        </column>

        @if ($alertMode !== 'vibrate')
            <column class="gap-2">
                <row class="w-full items-center justify-between">
                    <text class="text-sm font-bold text-theme-on-surface">Alert volume · {{ (int) round($alertVolume) }}%</text>
                    <button variant="ghost" @press="testAlert" a11y-hint="Plays the alert sound at this volume">Test</button>
                </row>
                <slider min="0" max="100" step="10" native:model="alertVolume" a11y-label="Alert volume" />
                <text class="text-xs text-theme-on-surface-variant">Relative to your phone's notification volume. Music volume is on the Speed screen, or use the phone's volume buttons.</text>
            </column>
        @endif

        <column class="gap-2">
            <toggle label="Alert above the road's speed limit" native:model="roadLimitAlerts" />
            <text class="text-xs text-theme-on-surface-variant">Road limits come from OpenStreetMap and need an internet connection. They may be missing or out of date.</text>
        </column>

        <column class="gap-2">
            <text class="text-sm font-bold text-theme-on-surface">Personal speed limit alert</text>
            <text class="text-theme-on-surface-variant">
                {{ $limitLabel > 0 ? "Alert above {$limitLabel} {$unitLabel}" : 'Off' }}
            </text>
            <slider min="0" :max="$sliderMax" step="5" native:model="speedLimit" a11y-label="Speed limit" />
        </column>

        <column class="gap-2">
            <text class="text-sm font-bold text-theme-on-surface">Automatic recording</text>
            <toggle label="Record trips automatically when driving" native:model="autoRecord" />
            <text class="text-xs text-theme-on-surface-variant">Starts a trip when your phone detects you're in a vehicle and stops it when you get out, even with Tripometer closed. Needs location "Allow all the time" and Physical activity access. Very short trips are discarded. You can also add the Tripometer widget to your home screen to start and stop trips.</text>
        </column>

        <column class="gap-2">
            <text class="text-sm font-bold text-theme-on-surface">Refuel reminders</text>
            <toggle label="Remind me when fuel is likely low" native:model="refuelReminders" />
            <outlined-text-input label="Tank size (litres, optional)" keyboard="decimal" native:model="tankCapacity" />
            @if ($tankCapacityError)
                <text class="text-xs text-theme-destructive">{{ $tankCapacityError }}</text>
            @endif
            <text class="text-xs text-theme-on-surface-variant">Estimated from your fuel log and the trips recorded since the last full tank. With the tank size the estimate is more accurate.</text>
        </column>

        @if ($hasUnsavedChanges)
            <text class="text-sm text-theme-accent">Unsaved changes</text>
        @endif

        <button variant="primary" @press="save" class="w-full">Save</button>

        <column class="gap-2">
            <text class="text-sm font-bold text-theme-on-surface">Backup</text>
            <row class="w-full gap-2">
                <button variant="ghost" :ios-icon="Ios::SquareAndArrowUp" :android-icon="Android::FileDownload" @press="openExport" class="flex-1">Export</button>
                <button variant="ghost" :ios-icon="Ios::ArrowDownDoc" :android-icon="Android::FileUpload" @press="openImport" class="flex-1">Import</button>
            </row>
        </column>
    </column>
</scroll-view>
