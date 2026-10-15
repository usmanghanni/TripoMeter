<scroll-view fill class="bg-theme-background">
    <column class="gap-6 p-6">
        <button-group :options="['Daily', 'Monthly']" native:model="periodIndex" a11y-label="Chart period" />

        <column class="gap-1">
            <text class="text-sm text-theme-on-surface-variant">{{ $periodIndex === 1 ? 'Last 12 months' : 'Last 14 days' }}</text>
            <text class="text-3xl font-bold text-theme-on-surface">{{ number_format($total, 1) }} {{ $this->settings->distanceUnit() }}</text>
        </column>

        <column class="w-full rounded-2xl bg-theme-surface p-4 gap-2" a11y-label="Distance per {{ $periodIndex === 1 ? 'month' : 'day' }} bar chart">
            <row class="w-full h-[{{ $chartHeight }}] items-end gap-1">
                @foreach ($this->bars as $bar)
                    <column
                        class="flex-1 h-[{{ max($bar['height'], 1) }}] rounded-t-md {{ $bar['height'] > 0 ? 'bg-theme-primary' : 'bg-theme-outline' }}"
                        a11y-label="{{ $bar['label'] }}: {{ number_format($bar['distance'], 1) }} {{ $this->settings->distanceUnit() }}"
                    />
                @endforeach
            </row>
            <row class="w-full gap-1">
                @foreach ($this->bars as $bar)
                    <text class="flex-1 text-center text-[9] text-theme-on-surface-variant">{{ $bar['label'] }}</text>
                @endforeach
            </row>
        </column>

        <row class="w-full gap-3">
            <column class="flex-1 rounded-2xl bg-theme-surface p-4">
                <text class="text-xs text-theme-on-surface-variant">Avg per active {{ $periodIndex === 1 ? 'month' : 'day' }}</text>
                <text class="text-xl font-bold text-theme-on-surface">{{ number_format($average, 1) }} {{ $this->settings->distanceUnit() }}</text>
            </column>
            <column class="flex-1 rounded-2xl bg-theme-surface p-4">
                <text class="text-xs text-theme-on-surface-variant">Best {{ $periodIndex === 1 ? 'month' : 'day' }}</text>
                <text class="text-xl font-bold text-theme-on-surface">{{ number_format($best, 1) }} {{ $this->settings->distanceUnit() }}</text>
            </column>
        </row>
    </column>
</scroll-view>
