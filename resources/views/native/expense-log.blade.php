@use('App\Icons\Ios')
@use('App\Icons\Android')

<column fill class="bg-theme-background">
    <column class="gap-3 p-4">
        @if ($this->oilChange)
            <column class="w-full gap-2 rounded-2xl p-4 {{ $this->oilChange['is_due_soon'] ? 'bg-amber-500/20' : 'bg-theme-surface' }}">
                <row class="w-full items-center justify-between">
                    <text class="text-xs text-theme-on-surface-variant">Next oil change</text>
                    @if ($this->oilChange['is_overdue'])
                        <text class="text-xs font-bold text-theme-destructive">Overdue</text>
                    @elseif ($this->oilChange['is_due_soon'])
                        <text class="text-xs font-bold text-amber-500">Due soon</text>
                    @endif
                </row>
                <text class="text-lg font-bold text-theme-on-surface">{{ \App\Support\OilChange::summary($this->oilChange) }}</text>
                <progress-bar :value="$this->oilChange['fraction_left']" a11y-label="Oil life about {{ round($this->oilChange['fraction_left'] * 100) }} percent" />
                <text class="text-xs text-theme-on-surface-variant">Last changed {{ $this->oilChange['last_on']->format('j M Y') }}{{ $this->oilChange['last_km'] ? ' at '.number_format($this->oilChange['last_km']).' km' : '' }}{{ $this->odometer ? ' · odometer now ~'.number_format($this->odometer['km']).' km' : '' }}</text>
                <button variant="ghost" @press="addOilChange" class="w-full">Log oil change</button>
            </column>
        @else
            <column class="w-full gap-2 rounded-2xl bg-theme-surface p-4">
                <text class="text-sm font-bold text-theme-on-surface">Track oil changes</text>
                <text class="text-xs text-theme-on-surface-variant">Log your last oil change with the odometer reading to see how far until the next one.</text>
                <button variant="ghost" @press="addOilChange" class="w-full">Log oil change</button>
            </column>
        @endif

        @if ($this->yearTotals !== [])
            <column class="w-full gap-1 rounded-2xl bg-theme-surface p-4">
                <row class="w-full items-center justify-between">
                    <text class="text-xs text-theme-on-surface-variant">Spent in {{ today()->year }}</text>
                    <text class="text-lg font-bold text-theme-on-surface">{{ number_format(array_sum($this->yearTotals), 2) }}</text>
                </row>
                @foreach ($this->yearTotals as $category => $total)
                    <row class="w-full justify-between">
                        <text class="text-sm text-theme-on-surface-variant">{{ \App\Models\Expense::CATEGORIES[$category] ?? 'Other' }}</text>
                        <text class="text-sm text-theme-on-surface">{{ number_format($total, 2) }}</text>
                    </row>
                @endforeach
            </column>
        @endif

        <button variant="primary" :ios-icon="Ios::Plus" :android-icon="Android::Add" @press="addExpense" class="w-full">Add expense</button>
    </column>

    @if ($this->expenses->isEmpty())
        <column fill class="items-center justify-center p-6">
            <text class="text-center text-theme-on-surface-variant">No expenses yet. Add services, oil changes, tyres, insurance and more.</text>
        </column>
    @else
        <list class="w-full">
            @foreach ($this->expenses as $expense)
                <list-item
                    :key="$expense->id"
                    headline="{{ $expense->category_label }}{{ $expense->title ? ' · '.$expense->title : '' }}"
                    supporting="{{ $expense->spent_on->format('D j M Y') }}{{ $expense->odometer_km ? ' · '.number_format($expense->odometer_km).' km' : '' }}"
                    trailingText="{{ number_format($expense->amount, 2) }}"
                    on-swipe-delete="deleteExpense({{ $expense->id }})"
                    @press="openExpense({{ $expense->id }})"
                    a11y-hint="Opens to edit. Swipe left to delete"
                />
            @endforeach
        </list>
    @endif
</column>
