@use('App\Icons\Ios')
@use('App\Icons\Android')

<column fill class="bg-theme-background">
    <column class="gap-3 p-4">
        <text class="text-sm text-theme-on-surface-variant">Saves a backup file with each trip's full route and speed timeline. Keep it in Drive, Files or Downloads and import it on any phone.</text>

        <toggle label="Include fuel log ({{ $this->fuelFillCount }} fill-ups)" native:model="includeFuel" :disabled="$this->fuelFillCount === 0" />

        <row class="w-full items-center justify-between">
            <text class="text-sm font-bold text-theme-on-surface">Trips · {{ count($selectedTripIds) }} of {{ $this->trips->count() }} selected</text>
            <row class="gap-1">
                <button variant="ghost" @press="selectAll">All</button>
                <button variant="ghost" @press="selectNone">None</button>
            </row>
        </row>

        <button
            variant="primary"
            :ios-icon="Ios::SquareAndArrowUp"
            :android-icon="Android::FileDownload"
            @press="export"
            :disabled="$isSaving"
            class="w-full"
        >{{ $isSaving ? 'Saving…' : 'Export' }}</button>
    </column>

    @if ($this->trips->isEmpty())
        <column fill class="items-center justify-center p-6">
            <text class="text-center text-theme-on-surface-variant">No finished trips to export yet.</text>
        </column>
    @else
        <list class="w-full">
            @foreach ($this->trips as $trip)
                <list-item
                    :key="$trip->id"
                    headline="{{ $trip->title ?: $trip->started_at->format('D j M Y, H:i') }}"
                    supporting="{{ number_format($this->settings->distance($trip->distance_m), 2) }} {{ $this->settings->distanceUnit() }} · {{ $trip->duration_label }}{{ $trip->title ? ' · '.$trip->started_at->format('j M Y') : '' }}"
                    :leadingCheckbox="in_array($trip->id, $selectedTripIds, true)"
                    on-leading-change="toggleTrip({{ $trip->id }})"
                    @press="toggleTrip({{ $trip->id }})"
                    a11y-hint="Toggles whether this trip is exported"
                />
            @endforeach
        </list>
    @endif
</column>
