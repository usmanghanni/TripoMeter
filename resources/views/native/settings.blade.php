<column fill class="gap-6 bg-theme-background p-6">
    <column class="gap-2">
        <text class="text-sm font-bold text-theme-on-surface">Units</text>
        <button-group :options="['km/h', 'mph']" native:model="unitIndex" a11y-label="Speed unit" />
    </column>

    <column class="gap-2">
        <text class="text-sm font-bold text-theme-on-surface">Alert</text>
        <button-group :options="['Vibrate', 'Sound', 'Both']" native:model="alertModeIndex" a11y-label="Alert type" />
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
        <slider min="0" :max="$sliderMax" step="5" native:model.debounce.300ms="speedLimit" a11y-label="Speed limit" />
    </column>
</column>
