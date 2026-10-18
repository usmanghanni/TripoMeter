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
    $personalLimit = $this->settings->speed_limit_kmh > 0 ? $this->settings->fromKmh($this->settings->speed_limit_kmh) : null;
    $alertLabel = collect([
        $personalLimit ? "above {$personalLimit} {$speedUnit}" : null,
        $this->settings->road_limit_alerts ? 'above road limit' : null,
    ])->filter()->implode(' or ');
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

            @if ($personalLimit)
                <column class="h-28 w-28 items-center justify-center rounded-full border-8 border-amber-500 bg-black" a11y-label="Your alert speed {{ $personalLimit }} {{ $speedUnit }}">
                    <text class="text-4xl font-bold text-white">{{ $personalLimit }}</text>
                </column>
            @endif

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

        @include('native.partials.now-playing', ['nowPlaying' => $nowPlaying, 'isHud' => true, 'isAndroid' => ! $this->isIos])

        <text class="text-sm text-zinc-500">Tap anywhere to exit</text>
    </column>
@else
    <scroll-view fill class="bg-theme-background">
        <column class="w-full items-center gap-4 px-6 py-4">
            <row class="w-full justify-end">
                <button variant="ghost" :ios-icon="Ios::ArrowUpLeftAndArrowDownRight" :android-icon="Android::Fullscreen" @press="toggleHud" a11y-label="HUD mode" a11y-hint="Shows speed full screen in large text">HUD</button>
            </row>

            <column class="items-center">
                <text class="text-8xl font-bold {{ $isOverLimit ? 'text-theme-destructive' : 'text-theme-on-surface' }}" a11y-label="{{ $speedA11y }}">{{ $speed }}</text>
                <text class="text-lg text-theme-on-surface-variant">{{ $speedUnit }}</text>
            </column>

            <row class="items-center gap-6">
                <column class="items-center gap-1">
                    <column class="h-16 w-16 items-center justify-center rounded-full border-4 border-red-600 bg-white" a11y-label="{{ $roadLimitA11y }}">
                        <text class="text-xl font-bold text-black">{{ $roadLimit ?? '--' }}</text>
                    </column>
                    <text class="text-xs text-theme-on-surface-variant">Road limit</text>
                </column>
                <column class="items-center gap-1" a11y-label="{{ $personalLimit ? "Your alert speed {$personalLimit} {$speedUnit}" : 'Your alert speed is off' }}">
                    <column class="h-16 w-16 items-center justify-center rounded-full border-4 border-amber-500 bg-theme-surface">
                        <text class="text-xl font-bold text-theme-on-surface">{{ $personalLimit ?? 'Off' }}</text>
                    </column>
                    <text class="text-xs text-theme-on-surface-variant">My alert</text>
                </column>
            </row>
            <text class="text-xs text-theme-on-surface-variant">{{ $alertLabel !== '' ? 'Alerts '.$alertLabel : 'Speed alerts are off. Turn them on in Settings.' }}</text>

            @if ($fuelForecast && $fuelForecast['is_low'])
                <row class="w-full items-center gap-3 rounded-2xl bg-amber-500/20 p-4" a11y-label="Fuel likely low">
                    <icon :ios="Ios::FuelpumpExclamationmarkFill" :android="Android::LocalGasStation" :size="24" class="text-amber-500" />
                    <column class="flex-1">
                        <text class="text-sm font-bold text-theme-on-surface">Time to refuel</text>
                        <text class="text-xs text-theme-on-surface-variant">About {{ number_format($this->settings->distance($fuelForecast['remaining_km'] * 1000)) }} {{ $distanceUnit }} left on this tank, from your fuel history.</text>
                    </column>
                </row>
            @endif

            @if ($oilChangeNotice)
                <row class="w-full items-center gap-3 rounded-2xl bg-amber-500/20 p-4" @navigate="/expenses" a11y-hint="Opens expenses">
                    <icon :ios="Ios::WrenchAndScrewdriverFill" :android="Android::OilBarrel" :size="24" class="text-amber-500" />
                    <text class="flex-1 text-sm font-bold text-theme-on-surface">{{ $oilChangeNotice }}</text>
                </row>
            @endif

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

            @include('native.partials.now-playing', ['nowPlaying' => $nowPlaying, 'isHud' => false, 'isAndroid' => ! $this->isIos])

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
    </scroll-view>
@endif
