@use('App\Icons\Ios')
@use('App\Icons\Android')

@php
    $speed = number_format($this->settings->speed($speedMps), 0);
    $speedUnit = $this->settings->speedUnit();
    $distanceUnit = $this->settings->distanceUnit();
    $roadLimit = $roadLimitKmh ? $this->settings->fromKmh($roadLimitKmh) : null;
    $distance = number_format($this->settings->distance($this->trip ? $this->trip->distance_m : $this->tripMeter['meters']), $this->trip ? 2 : 1);
    $maxSpeed = number_format($this->settings->speed($this->trip ? $this->trip->max_speed_mps : $this->tripMeter['max_mps']), 0);
    $speedA11y = "{$speed} {$speedUnit}".($isOverLimit ? ', over limit' : '');
    $roadLimitA11y = $roadLimit ? "Road speed limit {$roadLimit} {$speedUnit}" : 'Road speed limit unknown';
@endphp

@if ($isHudMode)
    {{-- HUD: everything huge, tap anywhere to leave. --}}
    <column fill class="items-center justify-center gap-8 bg-black px-6 safe-area" @press="toggleHud" a11y-label="Head-up display" a11y-hint="Tap to exit">
        <column class="items-center">
            <text class="text-[160] font-bold {{ $isOverLimit ? 'text-red-500' : 'text-white' }}" a11y-label="{{ $speedA11y }}">{{ $speed }}</text>
            <text class="text-3xl text-zinc-400">{{ $speedUnit }}</text>
        </column>

        <row class="w-full items-center justify-center gap-8">
            <column class="h-28 w-28 items-center justify-center rounded-full border-8 border-red-600 bg-white" a11y-label="{{ $roadLimitA11y }}">
                <text class="text-4xl font-bold text-black">{{ $roadLimit ?? '--' }}</text>
            </column>

            <column class="items-center">
                <text class="text-5xl font-bold text-white">{{ $distance }}</text>
                <text class="text-lg text-zinc-400">{{ $distanceUnit }}{{ $this->trip ? '' : ' (meter)' }}</text>
            </column>
        </row>

        @if ($this->trip)
            <column class="items-center">
                <text class="text-5xl font-bold text-white">{{ $this->trip->duration_label }}</text>
                <text class="text-lg text-zinc-400">trip time · max {{ $maxSpeed }} {{ $speedUnit }}</text>
            </column>
        @endif

        <text class="text-sm text-zinc-500">Tap anywhere to exit</text>
    </column>
@else
    <column fill class="items-center justify-center gap-6 bg-theme-background px-6">
        <row class="w-full justify-end">
            <button variant="ghost" :ios-icon="Ios::ArrowUpLeftAndArrowDownRight" :android-icon="Android::Fullscreen" @press="toggleHud" a11y-label="HUD mode" a11y-hint="Shows speed full screen in large text">HUD</button>
        </row>

        <column class="items-center">
            <text class="text-8xl font-bold {{ $isOverLimit ? 'text-theme-destructive' : 'text-theme-on-surface' }}" a11y-label="{{ $speedA11y }}">{{ $speed }}</text>
            <text class="text-lg text-theme-on-surface-variant">{{ $speedUnit }}</text>
        </column>

        <row class="items-center gap-3">
            <column class="h-16 w-16 items-center justify-center rounded-full border-4 border-red-600 bg-white" a11y-label="{{ $roadLimitA11y }}">
                <text class="text-xl font-bold text-black">{{ $roadLimit ?? '--' }}</text>
            </column>
            <text class="text-sm text-theme-on-surface-variant">Road limit</text>
        </row>

        @if ($this->trip)
            <row class="w-full justify-between rounded-2xl bg-theme-surface p-4">
                <column class="items-center flex-1">
                    <text class="text-xl font-bold text-theme-on-surface">{{ $distance }}</text>
                    <text class="text-xs text-theme-on-surface-variant">{{ $distanceUnit }}</text>
                </column>
                <column class="items-center flex-1">
                    <text class="text-xl font-bold text-theme-on-surface">{{ $this->trip->duration_label }}</text>
                    <text class="text-xs text-theme-on-surface-variant">time</text>
                </column>
                <column class="items-center flex-1">
                    <text class="text-xl font-bold text-theme-on-surface">{{ $maxSpeed }}</text>
                    <text class="text-xs text-theme-on-surface-variant">max {{ $speedUnit }}</text>
                </column>
            </row>
        @else
            <column class="w-full gap-2 rounded-2xl bg-theme-surface p-4">
                <row class="w-full items-center justify-between">
                    <text class="text-sm font-bold text-theme-on-surface">Trip meter</text>
                    <button variant="ghost" :ios-icon="Ios::ArrowCounterclockwise" :android-icon="Android::RestartAlt" @press="resetTripMeter" a11y-label="Reset trip meter">Reset</button>
                </row>
                <row class="w-full justify-between">
                    <column class="items-center flex-1">
                        <text class="text-xl font-bold text-theme-on-surface">{{ $distance }}</text>
                        <text class="text-xs text-theme-on-surface-variant">{{ $distanceUnit }}</text>
                    </column>
                    <column class="items-center flex-1">
                        <text class="text-xl font-bold text-theme-on-surface">{{ $maxSpeed }}</text>
                        <text class="text-xs text-theme-on-surface-variant">max {{ $speedUnit }}</text>
                    </column>
                </row>
                <text class="text-xs text-theme-on-surface-variant">Since {{ \Illuminate\Support\Carbon::createFromTimestamp($this->tripMeter['since'])->format('j M, H:i') }}</text>
            </column>
        @endif

        @if ($error)
            <row class="items-center gap-2">
                <icon :ios="Ios::ExclamationmarkTriangle" :android="Android::Warning" :size="18" class="text-theme-destructive" />
                <text class="text-sm text-theme-destructive">{{ $error === 'permission_required' ? 'Allow location access to see your speed.' : $error }}</text>
            </row>
        @endif

        @if ($this->trip)
            <button variant="destructive" :ios-icon="Ios::StopFill" :android-icon="Android::Stop" @press="stopTrip" class="w-full">Stop trip</button>
        @else
            <button variant="primary" :ios-icon="Ios::PlayFill" :android-icon="Android::PlayArrow" @press="startTrip" class="w-full">Start trip</button>
        @endif
    </column>
@endif
