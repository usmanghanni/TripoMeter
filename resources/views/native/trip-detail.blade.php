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

            @if ($this->timeline->hasRoute())
                @php
                    $route = $this->timeline->route();
                    $speedBars = $this->timeline->speedBars();
                    $secondsByBand = $this->timeline->secondsByBand();
                    $movingSeconds = max(1, array_sum($secondsByBand));
                    $speedUnit = $this->settings->speedUnit();
                    $distanceUnit = $this->settings->distanceUnit();
                @endphp

                <column class="w-full items-center gap-3 rounded-2xl bg-theme-surface p-4">
                    <text class="w-full text-sm font-bold text-theme-on-surface">Route by speed</text>
                    <stack class="w-[{{ \App\Support\TripTimeline::ROUTE_WIDTH }}] h-[{{ $route['height'] }}]" a11y-label="Route map coloured by speed. Larger dots mark the start and the end">
                        @foreach ($route['dots'] as $dot)
                            <circle :width="$dot['size']" :height="$dot['size']" class="absolute top-[{{ $dot['y'] }}] left-[{{ $dot['x'] }}] {{ $dot['color'] }}" />
                        @endforeach
                    </stack>
                    <button variant="ghost" @press="openRouteInMaps" class="w-full" a11y-hint="Opens the route in Google Maps">Open in Google Maps</button>
                </column>

                <column class="w-full gap-2 rounded-2xl bg-theme-surface p-4" a11y-label="Average speed over time bar chart">
                    <text class="text-sm font-bold text-theme-on-surface">Speed over time</text>
                    <row class="w-full h-[{{ \App\Support\TripTimeline::CHART_HEIGHT }}] items-end gap-[2]">
                        @foreach ($speedBars as $bar)
                            <column class="flex-1 h-[{{ max($bar['height'], 1) }}] rounded-t-sm {{ $bar['height'] > 0 ? $bar['color'] : 'bg-theme-outline' }}" a11y-label="{{ $bar['a11y'] }}" />
                        @endforeach
                    </row>
                    <row class="w-full justify-between">
                        <text class="text-[10] text-theme-on-surface-variant">{{ $speedBars[0]['label'] }}</text>
                        <text class="text-[10] text-theme-on-surface-variant">{{ $speedBars[array_key_last($speedBars)]['label'] }}</text>
                    </row>
                </column>

                <column class="w-full gap-2 rounded-2xl bg-theme-surface p-4">
                    <text class="text-sm font-bold text-theme-on-surface">Time at each speed</text>
                    @foreach ($this->timeline->legend() as $entry)
                        <row class="w-full items-center gap-3">
                            <circle :width="12" :height="12" class="{{ $entry['color'] }}" />
                            <column class="flex-1">
                                <text class="text-sm text-theme-on-surface">{{ $entry['label'] }}</text>
                                <text class="text-xs text-theme-on-surface-variant">{{ $entry['range'] }}</text>
                            </column>
                            <text class="text-sm text-theme-on-surface">{{ \App\Support\TripTimeline::shortDuration($secondsByBand[$entry['band']]) }} · {{ round($secondsByBand[$entry['band']] / $movingSeconds * 100) }}%</text>
                        </row>
                    @endforeach
                </column>

                <column class="w-full gap-1 rounded-2xl bg-theme-surface p-4">
                    <text class="text-sm font-bold text-theme-on-surface">Timeline</text>
                    <text class="text-xs text-theme-on-surface-variant">Tap a stretch to see where it was on a map.</text>
                    @foreach ($this->timeline->segments() as $index => $segment)
                        <row
                            class="w-full items-center gap-3 py-2"
                            @press="openSegment({{ $index }})"
                            a11y-label="{{ $segment['from'] }} to {{ $segment['to'] }}, {{ \App\Support\TripTimeline::BAND_LABELS[$segment['band']] }}, average {{ number_format($segment['average']) }} {{ $speedUnit }}"
                            a11y-hint="Opens this spot in Google Maps"
                        >
                            <column class="w-[6] h-[36] rounded-full {{ $segment['color'] }}" />
                            <column class="flex-1">
                                <text class="text-sm font-bold text-theme-on-surface">{{ $segment['from'] }} – {{ $segment['to'] }}</text>
                                <text class="text-xs text-theme-on-surface-variant">{{ number_format($segment['distance'], 2) }} {{ $distanceUnit }} · max {{ number_format($segment['max']) }} {{ $speedUnit }}</text>
                            </column>
                            <text class="text-base font-bold text-theme-on-surface">{{ number_format($segment['average']) }}</text>
                            <text class="text-xs text-theme-on-surface-variant">{{ $speedUnit }}</text>
                        </row>
                    @endforeach
                </column>
            @endif

            <outlined-text-input label="Title" native:model="title" placeholder="e.g. Office commute" />
            <outlined-text-input label="Notes" native:model="notes" multiline :minLines="3" />

            <button variant="primary" @press="save" class="w-full">Save</button>
            <button variant="destructive" @press="confirmDelete" class="w-full">Delete trip</button>
        </column>
    @endif
</scroll-view>
