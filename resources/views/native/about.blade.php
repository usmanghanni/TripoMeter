@use('App\Icons\Ios')
@use('App\Icons\Android')

<scroll-view fill class="bg-theme-background">
    <column class="gap-4 p-6">
        <column class="w-full items-center gap-1 rounded-2xl bg-theme-surface p-6">
            <icon :ios="Ios::GaugeWithNeedle" :android="Android::Speed" :size="44" class="text-theme-primary" />
            <text class="text-2xl font-bold text-theme-on-surface">Tripometer</text>
            <text class="text-sm text-theme-on-surface-variant">
                {{ $this->appInfo ? 'Version '.$this->appInfo['version'].($this->appInfo['debug'] ? ' (debug build)' : '') : 'Version unknown' }}
            </text>
        </column>

        <column class="w-full gap-2 rounded-2xl bg-theme-surface p-4">
            <text class="text-sm font-bold text-theme-on-surface">Updates</text>

            @if ($update)
                <text class="text-sm text-theme-on-surface">Version {{ $update['version'] }} is available (you have {{ $update['installed'] }}).</text>
                @if ($update['notes'] !== '')
                    <text class="text-xs text-theme-on-surface-variant">{{ $update['notes'] }}</text>
                @endif
                <button variant="primary" :ios-icon="Ios::SquareAndArrowDown" :android-icon="Android::FileDownload" @press="downloadUpdate" class="w-full">Download {{ $update['version'] }}</button>
                <text class="text-xs text-theme-on-surface-variant">Opens the APK download. Install it over this version; your data stays.</text>
            @else
                <button variant="ghost" @press="checkNow" :disabled="$isChecking" class="w-full">{{ $isChecking ? 'Checking…' : 'Check for updates' }}</button>
            @endif

            @if ($checkMessage)
                <text class="text-sm text-theme-on-surface-variant">{{ $checkMessage }}</text>
            @endif

            <toggle label="Check automatically once a day" native:model="updateChecks" />
        </column>

        <column class="w-full gap-1 rounded-2xl bg-theme-surface p-4">
            <list-item headline="Website" @press="openLink('website')" />
            <list-item headline="Privacy policy" @press="openLink('privacy')" />
            <list-item headline="Source code" supporting="Open source under the MIT licence" @press="openLink('source')" />
            <list-item headline="Licence" supporting="MIT" @press="openLink('license')" />
            <list-item headline="All releases" @press="openLink('releases')" />
        </column>

        <column class="w-full gap-2 rounded-2xl bg-theme-surface p-4">
            <text class="text-sm font-bold text-theme-on-surface">Open source licences</text>
            <text class="text-xs text-theme-on-surface-variant">Tripometer is built with these projects. Thank you to their authors.</text>
            @foreach ($this->licenses as $license)
                <row class="w-full items-center justify-between gap-3 py-1">
                    <text class="flex-1 text-sm text-theme-on-surface">{{ $license['name'] }}{{ isset($license['version']) && $license['version'] !== '' ? ' '.$license['version'] : '' }}</text>
                    <text class="text-xs text-theme-on-surface-variant">{{ $license['license'] }}</text>
                </row>
            @endforeach
        </column>
    </column>
</scroll-view>
