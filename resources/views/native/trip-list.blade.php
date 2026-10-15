<column fill class="bg-theme-background">
    @if ($this->trips->isEmpty())
        <column fill class="items-center justify-center p-6">
            <text class="text-center text-theme-on-surface-variant">No trips yet. Start one from the Speed tab.</text>
        </column>
    @else
        <list class="w-full">
            @foreach ($this->trips as $trip)
                <list-item
                    :key="$trip->id"
                    headline="{{ $trip->started_at->format('D j M, H:i') }}"
                    supporting="{{ number_format($this->settings->distance($trip->distance_m), 2) }} {{ $this->settings->distanceUnit() }} · {{ $trip->duration_label }} · avg {{ number_format($this->settings->speed($trip->average_speed_mps), 0) }} {{ $this->settings->speedUnit() }}"
                    trailingText="max {{ number_format($this->settings->speed($trip->max_speed_mps), 0) }}"
                    on-swipe-delete="deleteTrip({{ $trip->id }})"
                    a11y-hint="Swipe left to delete"
                />
            @endforeach
        </list>
    @endif
</column>
