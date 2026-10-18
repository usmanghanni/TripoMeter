@use('App\Icons\Ios')
@use('App\Icons\Android')

<column fill class="bg-theme-background">
    <column class="gap-2 p-4">
        <text class="text-sm text-theme-on-surface-variant">Trips, fill-ups and expenses are kept per vehicle. New trips are recorded for the current one; switch it from the Speed, Trips or Fuel screens.</text>
        <button variant="primary" :ios-icon="Ios::Plus" :android-icon="Android::Add" @press="addVehicle" class="w-full">Add vehicle</button>
    </column>

    <list class="w-full">
        @foreach ($this->vehicles as $vehicle)
            <list-item
                :key="$vehicle->id"
                headline="{{ $vehicle->name }}"
                supporting="{{ $vehicle->trips_count }} {{ str('trip')->plural($vehicle->trips_count) }} · {{ $vehicle->fuel_fills_count }} {{ str('fill-up')->plural($vehicle->fuel_fills_count) }} · {{ $vehicle->expenses_count }} {{ str('expense')->plural($vehicle->expenses_count) }}"
                trailingText="{{ $vehicle->id === $this->currentVehicleId ? 'Current' : '' }}"
                @press="openVehicle({{ $vehicle->id }})"
                a11y-hint="Opens to edit"
            />
        @endforeach
    </list>
</column>
