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

        <row class="w-full gap-3">
            <column class="flex-1 rounded-2xl bg-theme-surface p-4">
                <text class="text-xs text-theme-on-surface-variant">Latest price</text>
                <text class="text-lg font-bold text-theme-on-surface">{{ $this->report['latest_price'] ? number_format($this->report['latest_price'], 2) : '--' }}</text>
                <text class="text-xs text-theme-on-surface-variant">per litre</text>
            </column>
            <column class="flex-1 rounded-2xl bg-theme-surface p-4">
                <text class="text-xs text-theme-on-surface-variant">Average price</text>
                <text class="text-lg font-bold text-theme-on-surface">{{ $this->report['average_price'] ? number_format($this->report['average_price'], 2) : '--' }}</text>
                <text class="text-xs text-theme-on-surface-variant">per litre</text>
            </column>
        </row>

        @if ($this->report['average_price'])
            <text class="text-sm font-bold text-theme-on-surface">Price per litre, last 12 months</text>
            @include('native.partials.bar-chart', [
                'bars' => $this->priceBars,
                'chartHeight' => \App\NativeComponents\FuelLog::PRICE_CHART_HEIGHT,
                'a11yLabel' => 'Fuel price per litre by month bar chart',
            ])
        @endif

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
                    supporting="{{ number_format($fill->liters, 2) }} L · {{ number_format($fill->total_cost, 2) }}{{ $fill->price_per_liter ? ' @ '.number_format($fill->price_per_liter, 2).'/L' : '' }}{{ $fill->is_full_tank ? '' : ' · partial' }}"
                    trailingText="{{ isset($this->report['by_fill'][$fill->id]) ? number_format($this->report['by_fill'][$fill->id], 1).' km/L' : '' }}"
                    on-swipe-delete="deleteFill({{ $fill->id }})"
                    @press="openFill({{ $fill->id }})"
                    a11y-hint="Opens to edit. Swipe left to delete"
                />
            @endforeach
        </list>
    @endif
</column>
