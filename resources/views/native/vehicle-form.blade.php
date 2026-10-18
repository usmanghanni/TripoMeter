<scroll-view fill class="bg-theme-background">
    <column class="gap-4 p-6">
        <column class="gap-1">
            <outlined-text-input label="Name" native:model="name" placeholder="e.g. Corolla, CD 70" />
            @isset($fieldErrors['name'])
                <text class="text-xs text-theme-destructive">{{ $fieldErrors['name'] }}</text>
            @endisset
        </column>

        <column class="gap-1">
            <outlined-text-input label="Tank size (litres, optional)" keyboard="decimal" native:model="tankCapacity" />
            @isset($fieldErrors['tank_capacity_l'])
                <text class="text-xs text-theme-destructive">{{ $fieldErrors['tank_capacity_l'] }}</text>
            @endisset
            <text class="text-xs text-theme-on-surface-variant">Makes the "range left" estimate more accurate.</text>
        </column>

        <column class="gap-1">
            <text class="text-sm font-bold text-theme-on-surface">Default oil change interval</text>
            <row class="w-full gap-2">
                <outlined-text-input label="Every (km)" keyboard="number" native:model="oilIntervalKm" class="flex-1" />
                <outlined-text-input label="Or months" keyboard="number" native:model="oilIntervalMonths" class="flex-1" />
            </row>
            @foreach (['oil_change_interval_km', 'oil_change_interval_months'] as $field)
                @isset($fieldErrors[$field])
                    <text class="text-xs text-theme-destructive">{{ $fieldErrors[$field] }}</text>
                @endisset
            @endforeach
            <text class="text-xs text-theme-on-surface-variant">Used when an oil change has no next-change reading. The month limit always applies, whichever comes first. 0 ignores one.</text>
        </column>

        <button variant="primary" @press="save" class="w-full">Save</button>

        @if ($vehicleId)
            @if ($isCurrent)
                <text class="text-center text-sm text-theme-on-surface-variant">This is the current vehicle.</text>
            @else
                <button variant="ghost" @press="makeCurrent" class="w-full">Use this vehicle</button>
            @endif
            <button variant="destructive" @press="confirmDelete" class="w-full">Delete vehicle</button>
        @endif
    </column>
</scroll-view>
