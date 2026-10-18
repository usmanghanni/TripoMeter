@use('App\Icons\Ios')
@use('App\Icons\Android')

<column fill class="bg-theme-background">
    <column class="gap-3 p-4">
        @if ($filePath === null)
            <text class="text-sm text-theme-on-surface-variant">Open a Tripometer backup (.json) to choose which trips and fill-ups to bring in. Anything already on this phone is skipped.</text>
        @else
            <text class="text-sm text-theme-on-surface-variant">{{ $fileName ?: 'Backup' }} · {{ count($fileTrips) }} {{ str('trip')->plural(count($fileTrips)) }}, {{ $carLogCount }} fuel and expense {{ str('entry')->plural($carLogCount) }}</text>
        @endif

        <button variant="{{ $filePath === null ? 'primary' : 'ghost' }}" :ios-icon="Ios::ArrowDownDoc" :android-icon="Android::FileUpload" @press="chooseFile" class="w-full">
            {{ $filePath === null ? 'Choose backup file' : 'Choose another file' }}
        </button>

        @if ($error)
            <row class="items-center gap-2">
                <icon :ios="Ios::ExclamationmarkTriangle" :android="Android::Warning" :size="18" class="text-theme-destructive" />
                <text class="flex-1 text-sm text-theme-destructive">{{ $error }}</text>
            </row>
        @endif

        @if ($filePath !== null)
            @if ($carLogCount > 0)
                <toggle
                    label="{{ $newCarLogCount > 0 ? "Import fuel log and expenses ({$newCarLogCount} new)" : 'Fuel log and expenses already imported' }}"
                    native:model="includeFuel"
                    :disabled="$newCarLogCount === 0"
                />
            @endif

            @if ($fileTrips !== [])
                <row class="w-full items-center justify-between">
                    <text class="text-sm font-bold text-theme-on-surface">Trips · {{ count($selectedIndexes) }} selected</text>
                    <row class="gap-1">
                        <button variant="ghost" @press="selectAll">All</button>
                        <button variant="ghost" @press="selectNone">None</button>
                    </row>
                </row>
            @endif

            <button variant="primary" @press="import" class="w-full">Import</button>
        @endif
    </column>

    @if ($fileTrips !== [])
        <list class="w-full">
            @foreach ($fileTrips as $trip)
                <list-item
                    :key="$trip['index']"
                    headline="{{ $trip['title'] ?: \Illuminate\Support\Carbon::parse($trip['started_at'])->format('D j M Y, H:i') }}"
                    supporting="{{ number_format($this->settings->distance($trip['distance_m']), 2) }} {{ $this->settings->distanceUnit() }} · {{ $trip['points'] }} points{{ $trip['exists'] ? ' · already on this phone' : '' }}"
                    :leadingCheckbox="$trip['exists'] || in_array($trip['index'], $selectedIndexes, true)"
                    on-leading-change="toggleTrip({{ $trip['index'] }})"
                    @press="toggleTrip({{ $trip['index'] }})"
                    :disabled="$trip['exists']"
                    a11y-hint="{{ $trip['exists'] ? 'Already imported' : 'Toggles whether this trip is imported' }}"
                />
            @endforeach
        </list>
    @endif
</column>
