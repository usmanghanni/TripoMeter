@use('App\Icons\Ios')
@use('App\Icons\Android')

{{-- Fuel dashboard; the full fill-up list lives on /fuel/history. --}}
<scroll-view fill class="bg-theme-background">
    <column class="gap-3 p-4">
        @include('native.partials.vehicle-switcher', ['vehicles' => $this->vehicles, 'currentVehicle' => $this->currentVehicle])

        @if ($this->forecast)
            <column class="w-full gap-2 rounded-2xl p-4 {{ $this->forecast['is_low'] ? 'bg-amber-500/20' : 'bg-theme-surface' }}">
                <row class="w-full items-center justify-between">
                    <text class="text-xs text-theme-on-surface-variant">Estimated range left</text>
                    @if ($this->forecast['is_low'])
                        <text class="text-xs font-bold text-amber-500">Refuel soon</text>
                    @endif
                </row>
                <text class="text-2xl font-bold text-theme-on-surface">~{{ number_format($this->forecast['remaining_km']) }} km</text>
                <progress-bar :value="$this->forecast['fraction_left']" a11y-label="Tank about {{ round($this->forecast['fraction_left'] * 100) }} percent full" />
                <text class="text-xs text-theme-on-surface-variant">{{ number_format($this->forecast['driven_km']) }} km recorded since the last full tank on {{ $this->forecast['since']->format('j M') }}.</text>
            </column>
        @endif

        <row class="w-full gap-2">
            <button variant="primary" :ios-icon="Ios::Plus" :android-icon="Android::Add" @press="addFill" class="flex-1">Add fill-up</button>
            <button variant="ghost" :ios-icon="Ios::WrenchAndScrewdriver" :android-icon="Android::Build" @press="openExpenses" class="flex-1" a11y-hint="Services, oil changes and other car expenses">Expenses</button>
        </row>

        @if ($this->oilChange && $this->oilChange['is_due_soon'])
            <row class="w-full items-center gap-2 rounded-2xl bg-theme-surface p-3" @press="openExpenses" a11y-hint="Opens expenses">
                <icon :ios="Ios::ExclamationmarkTriangle" :android="Android::Warning" :size="18" class="{{ $this->oilChange['is_overdue'] ? 'text-theme-destructive' : 'text-amber-500' }}" />
                <text class="flex-1 text-sm text-theme-on-surface">Oil change {{ lcfirst(\App\Support\OilChange::summary($this->oilChange)) }}</text>
            </row>
        @endif

        <row class="w-full gap-3">
            <column class="flex-1 rounded-2xl bg-theme-surface p-3">
                <text class="text-xs text-theme-on-surface-variant">Fuel average</text>
                <text class="text-xl font-bold text-theme-on-surface">{{ $this->report['km_per_liter'] ? number_format($this->report['km_per_liter'], 1) : '--' }}</text>
                <text class="text-xs text-theme-on-surface-variant">km/L</text>
            </column>
            <column class="flex-1 rounded-2xl bg-theme-surface p-3">
                <text class="text-xs text-theme-on-surface-variant">Consumption</text>
                <text class="text-xl font-bold text-theme-on-surface">{{ $this->report['liters_per_100km'] ? number_format($this->report['liters_per_100km'], 1) : '--' }}</text>
                <text class="text-xs text-theme-on-surface-variant">L/100 km</text>
            </column>
            <column class="flex-1 rounded-2xl bg-theme-surface p-3">
                <text class="text-xs text-theme-on-surface-variant">Cost per km</text>
                <text class="text-xl font-bold text-theme-on-surface">{{ $this->report['cost_per_km'] ? number_format($this->report['cost_per_km'], 2) : '--' }}</text>
            </column>
        </row>

        <row class="w-full gap-3">
            <column class="flex-1 rounded-2xl bg-theme-surface p-3">
                <text class="text-xs text-theme-on-surface-variant">Latest price</text>
                <text class="text-lg font-bold text-theme-on-surface">{{ $this->report['latest_price'] ? number_format($this->report['latest_price'], 2) : '--' }}</text>
                <text class="text-xs text-theme-on-surface-variant">per litre</text>
            </column>
            <column class="flex-1 rounded-2xl bg-theme-surface p-3">
                <text class="text-xs text-theme-on-surface-variant">Average price</text>
                <text class="text-lg font-bold text-theme-on-surface">{{ $this->report['average_price'] ? number_format($this->report['average_price'], 2) : '--' }}</text>
                <text class="text-xs text-theme-on-surface-variant">per litre</text>
            </column>
            <column class="flex-1 rounded-2xl bg-theme-surface p-3">
                <text class="text-xs text-theme-on-surface-variant">Total spent</text>
                <text class="text-lg font-bold text-theme-on-surface">{{ number_format($this->report['total_cost'], 2) }}</text>
                <text class="text-xs text-theme-on-surface-variant">on fuel</text>
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

        <column class="w-full gap-1 rounded-2xl bg-theme-surface p-4">
            <row class="w-full items-center justify-between">
                <text class="text-sm font-bold text-theme-on-surface">Recent fill-ups</text>
                @if ($this->fillCount > 0)
                    <button variant="ghost" @press="openHistory" a11y-hint="Shows every fill-up">See all {{ $this->fillCount }}</button>
                @endif
            </row>

            @forelse ($this->recentFills as $fill)
                <row class="w-full items-center gap-3 py-2" @press="openFill({{ $fill->id }})" a11y-hint="Opens to edit">
                    <column class="flex-1">
                        <text class="text-sm text-theme-on-surface">{{ $fill->filled_on->format('D j M') }} · {{ number_format($fill->odometer_km) }} km</text>
                        <text class="text-xs text-theme-on-surface-variant">{{ $fill->summary }}</text>
                    </column>
                    @isset($this->report['by_fill'][$fill->id])
                        <text class="text-sm font-bold text-theme-on-surface">{{ number_format($this->report['by_fill'][$fill->id], 1) }} km/L</text>
                    @endisset
                </row>
            @empty
                <text class="text-sm text-theme-on-surface-variant">No fill-ups yet. Fill the tank completely and log it to start tracking your average.</text>
            @endforelse
        </column>
    </column>
</scroll-view>
