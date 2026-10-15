@use('App\Icons\Ios')
@use('App\Icons\Android')

<column fill class="bg-theme-background">
    <column class="gap-3 p-4">
        <row class="w-full gap-3">
            <column class="flex-1 rounded-2xl bg-theme-surface p-4">
                <text class="text-xs text-theme-on-surface-variant">Fuel average</text>
                <text class="text-2xl font-bold text-theme-on-surface">{{ $this->report['km_per_liter'] ? number_format($this->report['km_per_liter'], 1) : '--' }}</text>
                <text class="text-xs text-theme-on-surface-variant">km/L</text>
            </column>
            <column class="flex-1 rounded-2xl bg-theme-surface p-4">
                <text class="text-xs text-theme-on-surface-variant">Consumption</text>
                <text class="text-2xl font-bold text-theme-on-surface">{{ $this->report['liters_per_100km'] ? number_format($this->report['liters_per_100km'], 1) : '--' }}</text>
                <text class="text-xs text-theme-on-surface-variant">L/100 km</text>
            </column>
        </row>
        <row class="w-full gap-3">
            <column class="flex-1 rounded-2xl bg-theme-surface p-4">
                <text class="text-xs text-theme-on-surface-variant">Cost per km</text>
                <text class="text-lg font-bold text-theme-on-surface">{{ $this->report['cost_per_km'] ? number_format($this->report['cost_per_km'], 2) : '--' }}</text>
            </column>
            <column class="flex-1 rounded-2xl bg-theme-surface p-4">
                <text class="text-xs text-theme-on-surface-variant">Total spent</text>
                <text class="text-lg font-bold text-theme-on-surface">{{ number_format($this->report['total_cost'], 2) }}</text>
            </column>
        </row>

        <button variant="primary" :ios-icon="Ios::Plus" :android-icon="Android::Add" @press="addFill" class="w-full">Add fill-up</button>
    </column>

    @if ($this->fills->isEmpty())
        <column fill class="items-center justify-center p-6">
            <text class="text-center text-theme-on-surface-variant">No fill-ups yet. Fill the tank completely and log it to start tracking your average.</text>
        </column>
    @else
        <list class="w-full">
            @foreach ($this->fills as $fill)
                <list-item
                    :key="$fill->id"
                    headline="{{ $fill->filled_on->format('D j M Y') }} · {{ number_format($fill->odometer_km) }} km"
                    supporting="{{ number_format($fill->liters, 2) }} L · {{ number_format($fill->total_cost, 2) }}{{ $fill->is_full_tank ? '' : ' · partial' }}"
                    trailingText="{{ isset($this->report['by_fill'][$fill->id]) ? number_format($this->report['by_fill'][$fill->id], 1).' km/L' : '' }}"
                    on-swipe-delete="deleteFill({{ $fill->id }})"
                    a11y-hint="Swipe left to delete"
                />
            @endforeach
        </list>
    @endif
</column>
