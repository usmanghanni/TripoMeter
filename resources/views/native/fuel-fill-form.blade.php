<scroll-view fill class="bg-theme-background">
    <column class="gap-4 p-6">
        <date-picker mode="date" label="Date" native:model="filledOn" :max="today()->toDateString()" />

        <column class="gap-1">
            <outlined-text-input label="Odometer (km)" keyboard="decimal" native:model="odometer"
                placeholder="{{ $lastOdometer ? 'Last: '.number_format($lastOdometer, 0, '.', '') : '' }}" />
            @isset($fieldErrors['odometer_km'])
                <text class="text-xs text-theme-destructive">{{ $fieldErrors['odometer_km'] }}</text>
            @endisset
        </column>

        <column class="gap-1">
            <outlined-text-input label="Fuel (litres)" keyboard="decimal" native:model="liters" />
            @isset($fieldErrors['liters'])
                <text class="text-xs text-theme-destructive">{{ $fieldErrors['liters'] }}</text>
            @endisset
        </column>

        <column class="gap-1">
            <outlined-text-input label="Total cost" keyboard="decimal" native:model="totalCost" />
            @isset($fieldErrors['total_cost'])
                <text class="text-xs text-theme-destructive">{{ $fieldErrors['total_cost'] }}</text>
            @endisset
        </column>

        @if ($pricePerLiter)
            <text class="text-sm text-theme-on-surface-variant">Price per litre: {{ number_format($pricePerLiter, 2) }}</text>
        @endif

        <toggle label="Filled to full tank" native:model="isFullTank" />
        <text class="text-xs text-theme-on-surface-variant">Fuel average is calculated between full-tank fill-ups. Partial fills are counted toward the next full one.</text>

        @isset($fieldErrors['filled_on'])
            <text class="text-xs text-theme-destructive">{{ $fieldErrors['filled_on'] }}</text>
        @endisset

        <button variant="primary" @press="save" class="w-full">Save</button>

        @if ($fillId)
            <button variant="destructive" @press="confirmDelete" class="w-full">Delete fill-up</button>
        @endif
    </column>
</scroll-view>
