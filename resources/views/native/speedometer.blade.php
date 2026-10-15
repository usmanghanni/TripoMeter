@use('App\Icons\Ios')
@use('App\Icons\Android')

<column fill class="items-center justify-center gap-6 bg-theme-background px-6">
    <column class="items-center">
        <text
            class="text-8xl font-bold {{ $isOverLimit ? 'text-theme-destructive' : 'text-theme-on-surface' }}"
            a11y-label="{{ number_format($this->settings->speed($speedMps), 0) }} {{ $this->settings->speedUnit() }}{{ $isOverLimit ? ', over limit' : '' }}"
        >
            {{ number_format($this->settings->speed($speedMps), 0) }}
        </text>
        <text class="text-lg text-theme-on-surface-variant">{{ $this->settings->speedUnit() }}</text>
    </column>

    @if ($this->trip)
        <row class="items-center gap-3">
            <column
                class="h-16 w-16 items-center justify-center rounded-full border-4 border-red-600 bg-white"
                a11y-label="{{ $roadLimitKmh ? 'Road speed limit '.$this->settings->fromKmh($roadLimitKmh).' '.$this->settings->speedUnit() : 'Road speed limit unknown' }}"
            >
                <text class="text-xl font-bold text-black">{{ $roadLimitKmh ? $this->settings->fromKmh($roadLimitKmh) : '--' }}</text>
            </column>
            <text class="text-sm text-theme-on-surface-variant">Road limit</text>
        </row>

        <row class="w-full justify-between rounded-2xl bg-theme-surface p-4">
            <column class="items-center flex-1">
                <text class="text-xl font-bold text-theme-on-surface">{{ number_format($this->settings->distance($this->trip->distance_m), 2) }}</text>
                <text class="text-xs text-theme-on-surface-variant">{{ $this->settings->distanceUnit() }}</text>
            </column>
            <column class="items-center flex-1">
                <text class="text-xl font-bold text-theme-on-surface">{{ $this->trip->duration_label }}</text>
                <text class="text-xs text-theme-on-surface-variant">time</text>
            </column>
            <column class="items-center flex-1">
                <text class="text-xl font-bold text-theme-on-surface">{{ number_format($this->settings->speed($this->trip->max_speed_mps), 0) }}</text>
                <text class="text-xs text-theme-on-surface-variant">max {{ $this->settings->speedUnit() }}</text>
            </column>
        </row>
    @endif

    @if ($error)
        <row class="items-center gap-2">
            <icon :ios="Ios::ExclamationmarkTriangle" :android="Android::Warning" :size="18" class="text-theme-destructive" />
            <text class="text-sm text-theme-destructive">{{ $error === 'permission_required' ? 'Allow location access, then tap Start again.' : $error }}</text>
        </row>
    @endif

    @if ($this->trip)
        <button variant="destructive" :ios-icon="Ios::StopFill" :android-icon="Android::Stop" @press="stopTrip" class="w-full">Stop trip</button>
    @else
        <button variant="primary" :ios-icon="Ios::PlayFill" :android-icon="Android::PlayArrow" @press="startTrip" class="w-full">Start trip</button>
    @endif
</column>
