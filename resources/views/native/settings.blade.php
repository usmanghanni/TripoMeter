<scroll-view fill class="bg-theme-background">
    <column class="gap-6 p-6">
        <column class="gap-2">
            <text class="text-sm font-bold text-theme-on-surface">Units</text>
            <row class="w-full gap-2">
                @foreach ($units as $value => $label)
                    <button
                        variant="{{ $unit === $value ? 'primary' : 'ghost' }}"
                        @press="setUnit('{{ $value }}')"
                        a11y-label="Speed unit {{ $label }}{{ $unit === $value ? ', selected' : '' }}"
                        class="flex-1"
                    >{{ $label }}</button>
                @endforeach
            </row>
        </column>

        <column class="gap-2">
            <text class="text-sm font-bold text-theme-on-surface">Alert</text>
            <row class="w-full gap-2">
                @foreach ($alertModes as $value => $label)
                    <button
                        variant="{{ $alertMode === $value ? 'primary' : 'ghost' }}"
                        @press="setAlertMode('{{ $value }}')"
                        a11y-label="Alert {{ $label }}{{ $alertMode === $value ? ', selected' : '' }}"
                        class="flex-1"
                    >{{ $label }}</button>
                @endforeach
            </row>
        </column>

        <column class="gap-2">
            <toggle label="Alert above the road's speed limit" native:model="roadLimitAlerts" />
            <text class="text-xs text-theme-on-surface-variant">Road limits come from OpenStreetMap and need an internet connection. They may be missing or out of date.</text>
        </column>

        <column class="gap-2">
            <text class="text-sm font-bold text-theme-on-surface">Personal speed limit alert</text>
            <text class="text-theme-on-surface-variant">
                {{ $limitLabel > 0 ? "Alert above {$limitLabel} {$unitLabel}" : 'Off' }}
            </text>
            <slider min="0" :max="$sliderMax" step="5" native:model="speedLimit" a11y-label="Speed limit" />
        </column>

        @if ($hasUnsavedChanges)
            <text class="text-sm text-theme-accent">Unsaved changes</text>
        @endif

        <button variant="primary" @press="save" class="w-full">Save</button>
    </column>
</scroll-view>
