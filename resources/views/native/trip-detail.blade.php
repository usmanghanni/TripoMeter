<scroll-view fill class="bg-theme-background">
    @if ($this->trip)
        <column class="gap-4 p-6">
            <text class="text-sm text-theme-on-surface-variant">
                {{ $this->trip->started_at->format('D j M Y, H:i') }}{{ $this->trip->ended_at ? ' – '.$this->trip->ended_at->format('H:i') : ' · recording' }}
            </text>

            <row class="w-full gap-3">
                <column class="flex-1 rounded-2xl bg-theme-surface p-4">
                    <text class="text-xs text-theme-on-surface-variant">Distance</text>
                    <text class="text-xl font-bold text-theme-on-surface">{{ number_format($this->settings->distance($this->trip->distance_m), 2) }} {{ $this->settings->distanceUnit() }}</text>
                </column>
                <column class="flex-1 rounded-2xl bg-theme-surface p-4">
                    <text class="text-xs text-theme-on-surface-variant">Duration</text>
                    <text class="text-xl font-bold text-theme-on-surface">{{ $this->trip->duration_label }}</text>
                </column>
            </row>
            <row class="w-full gap-3">
                <column class="flex-1 rounded-2xl bg-theme-surface p-4">
                    <text class="text-xs text-theme-on-surface-variant">Average</text>
                    <text class="text-xl font-bold text-theme-on-surface">{{ number_format($this->settings->speed($this->trip->average_speed_mps), 0) }} {{ $this->settings->speedUnit() }}</text>
                </column>
                <column class="flex-1 rounded-2xl bg-theme-surface p-4">
                    <text class="text-xs text-theme-on-surface-variant">Top speed</text>
                    <text class="text-xl font-bold text-theme-on-surface">{{ number_format($this->settings->speed($this->trip->max_speed_mps), 0) }} {{ $this->settings->speedUnit() }}</text>
                </column>
            </row>

            <outlined-text-input label="Title" native:model="title" placeholder="e.g. Office commute" />
            <outlined-text-input label="Notes" native:model="notes" multiline :minLines="3" />

            <button variant="primary" @press="save" class="w-full">Save</button>
            <button variant="destructive" @press="confirmDelete" class="w-full">Delete trip</button>
        </column>
    @endif
</scroll-view>
