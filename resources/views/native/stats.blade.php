<scroll-view fill class="bg-theme-background">
    <column class="gap-6 p-6">
        @include('native.partials.vehicle-switcher', ['vehicles' => $this->vehicles, 'currentVehicle' => $this->currentVehicle])

        <row class="w-full gap-2">
            @foreach (['daily' => 'Daily', 'monthly' => 'Monthly'] as $value => $label)
                <button
                    variant="{{ $period === $value ? 'primary' : 'ghost' }}"
                    @press="setPeriod('{{ $value }}')"
                    a11y-label="{{ $label }} chart{{ $period === $value ? ', selected' : '' }}"
                    class="flex-1"
                >{{ $label }}</button>
            @endforeach
        </row>

        <column class="gap-1">
            <text class="text-sm text-theme-on-surface-variant">{{ $period === 'monthly' ? 'Last 12 months' : 'Last 14 days' }}</text>
            <text class="text-3xl font-bold text-theme-on-surface">{{ number_format($total, 1) }} {{ $this->settings->distanceUnit() }}</text>
        </column>

        @include('native.partials.bar-chart', [
            'bars' => array_map(fn (array $bar): array => [
                'label' => $bar['label'],
                'height' => $bar['height'],
                'a11y' => $bar['label'].': '.number_format($bar['distance'], 1).' '.$this->settings->distanceUnit(),
            ], $this->bars),
            'chartHeight' => $chartHeight,
            'a11yLabel' => 'Distance per '.($period === 'monthly' ? 'month' : 'day').' bar chart',
        ])

        <row class="w-full gap-3">
            <column class="flex-1 rounded-2xl bg-theme-surface p-4">
                <text class="text-xs text-theme-on-surface-variant">Avg per active {{ $period === 'monthly' ? 'month' : 'day' }}</text>
                <text class="text-xl font-bold text-theme-on-surface">{{ number_format($average, 1) }} {{ $this->settings->distanceUnit() }}</text>
            </column>
            <column class="flex-1 rounded-2xl bg-theme-surface p-4">
                <text class="text-xs text-theme-on-surface-variant">Best {{ $period === 'monthly' ? 'month' : 'day' }}</text>
                <text class="text-xl font-bold text-theme-on-surface">{{ number_format($best, 1) }} {{ $this->settings->distanceUnit() }}</text>
            </column>
        </row>
    </column>
</scroll-view>
