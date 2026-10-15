{{--
    Native bar chart built from columns.
    @param list<array{label: string, height: int, a11y: string}> $bars  heights in points, 0 = empty
    @param int $chartHeight
    @param string $a11yLabel
--}}
<column class="w-full rounded-2xl bg-theme-surface p-4 gap-2" a11y-label="{{ $a11yLabel }}">
    <row class="w-full h-[{{ $chartHeight }}] items-end gap-1">
        @foreach ($bars as $bar)
            <column
                class="flex-1 h-[{{ max($bar['height'], 1) }}] rounded-t-md {{ $bar['height'] > 0 ? 'bg-theme-primary' : 'bg-theme-outline' }}"
                a11y-label="{{ $bar['a11y'] }}"
            />
        @endforeach
    </row>
    <row class="w-full gap-1">
        @foreach ($bars as $bar)
            <text class="flex-1 text-center text-[9] text-theme-on-surface-variant">{{ $bar['label'] }}</text>
        @endforeach
    </row>
</column>
