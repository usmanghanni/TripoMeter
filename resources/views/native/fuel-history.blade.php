@use('App\Icons\Ios')
@use('App\Icons\Android')

<column fill class="bg-theme-background">
    <column class="p-4">
        <button variant="primary" :ios-icon="Ios::Plus" :android-icon="Android::Add" @press="addFill" class="w-full">Add fill-up</button>
    </column>

    @if ($this->fills->isEmpty())
        <column fill class="items-center justify-center p-6">
            <text class="text-center text-theme-on-surface-variant">No fill-ups yet. Fill the tank completely and log it to start tracking your average.</text>
        </column>
    @else
        <list class="w-full">
            @foreach ($this->fills as $fill)
                <list-item
                    :key="$fill->id"
                    headline="{{ $fill->filled_on->format('D j M Y') }} · {{ number_format($fill->odometer_km) }} km"
                    supporting="{{ $fill->summary }}"
                    trailingText="{{ isset($this->economyByFill[$fill->id]) ? number_format($this->economyByFill[$fill->id], 1).' km/L' : '' }}"
                    on-swipe-delete="deleteFill({{ $fill->id }})"
                    @press="openFill({{ $fill->id }})"
                    a11y-hint="Opens to edit. Swipe left to delete"
                />
            @endforeach
        </list>
    @endif
</column>
