<scroll-view fill class="bg-theme-background">
    <column class="gap-4 p-6">
        <column class="gap-2">
            <text class="text-sm font-bold text-theme-on-surface">Type</text>
            @foreach (array_chunk($categories, 3, true) as $row)
                <row class="w-full gap-2">
                    @foreach ($row as $value => $label)
                        <button
                            variant="{{ $category === $value ? 'primary' : 'ghost' }}"
                            @press="setCategory('{{ $value }}')"
                            a11y-label="{{ $label }}{{ $category === $value ? ', selected' : '' }}"
                            class="flex-1"
                        >{{ $label }}</button>
                    @endforeach
                </row>
            @endforeach
        </column>

        <date-picker mode="date" label="Date" native:model="spentOn" :max="today()->toDateString()" />
        @isset($fieldErrors['spent_on'])
            <text class="text-xs text-theme-destructive">{{ $fieldErrors['spent_on'] }}</text>
        @endisset

        <outlined-text-input label="Description (optional)" native:model="title" placeholder="e.g. 10,000 km service" />

        <column class="gap-1">
            <outlined-text-input label="Amount" keyboard="decimal" native:model="amount" />
            @isset($fieldErrors['amount'])
                <text class="text-xs text-theme-destructive">{{ $fieldErrors['amount'] }}</text>
            @endisset
        </column>

        <column class="gap-1">
            <outlined-text-input label="Odometer (km){{ $category === 'oil_change' ? '' : ', optional' }}" keyboard="decimal" native:model="odometer"
                placeholder="{{ $estimatedOdometer ? 'About '.number_format($estimatedOdometer, 0, '.', '') : '' }}" />
            @isset($fieldErrors['odometer_km'])
                <text class="text-xs text-theme-destructive">{{ $fieldErrors['odometer_km'] }}</text>
            @endisset
        </column>

        @if ($category === 'oil_change')
            <column class="gap-1">
                <outlined-text-input label="Next change at (km), optional" keyboard="decimal" native:model="nextDueKm"
                    placeholder="{{ $suggestedNextDueKm ? 'e.g. '.number_format($suggestedNextDueKm, 0, '.', '') : '' }}" />
                @isset($fieldErrors['next_due_km'])
                    <text class="text-xs text-theme-destructive">{{ $fieldErrors['next_due_km'] }}</text>
                @endisset
                <text class="text-xs text-theme-on-surface-variant">The reading on the service sticker. Leave empty to use the interval from Settings.</text>
            </column>
        @endif

        <outlined-text-input label="Notes" native:model="notes" multiline :minLines="2" />

        <button variant="primary" @press="save" class="w-full">Save</button>

        @if ($expenseId)
            <button variant="destructive" @press="confirmDelete" class="w-full">Delete expense</button>
        @endif
    </column>
</scroll-view>
