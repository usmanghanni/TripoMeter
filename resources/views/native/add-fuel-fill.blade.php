<scroll-view fill class="bg-theme-background">
    <column class="gap-4 p-6">
        <date-picker mode="date" label="Date" native:model="filledOn" :max="today()->toDateString()" />

        <column class="gap-1">
            <outlined-text-input label="Odometer (km)" keyboard="decimal" native:model.blur="odometer"
                placeholder="{{ $lastOdometer ? 'Last: '.number_format($lastOdometer, 0, '.', '') : '' }}" />
            @isset($fieldErrors['odometer_km'])
                <text class="text-xs text-theme-destructive">{{ $fieldErrors['odometer_km'] }}</text>
            @endisset
        </column>

        <column class="gap-1">
            <outlined-text-input label="Fuel (litres)" keyboard="decimal" native:model.blur="liters" />
            @isset($fieldErrors['liters'])
                <text class="text-xs text-theme-destructive">{{ $fieldErrors['liters'] }}</text>
            @endisset
        </column>

        <column class="gap-1">
            <outlined-text-input label="Total cost" keyboard="decimal" native:model.blur="totalCost" />
            @isset($fieldErrors['total_cost'])
                <text class="text-xs text-theme-destructive">{{ $fieldErrors['total_cost'] }}</text>
            @endisset
        </column>

        <toggle label="Filled to full tank" native:model="isFullTank" />
        <text class="text-xs text-theme-on-surface-variant">Fuel average is calculated between full-tank fill-ups. Partial fills are counted toward the next full one.</text>

        @isset($fieldErrors['filled_on'])
            <text class="text-xs text-theme-destructive">{{ $fieldErrors['filled_on'] }}</text>
        @endisset

        <button variant="primary" @press="save" class="w-full">Save</button>
    </column>
</scroll-view>
