{{--
    Vehicle chips for screens using App\Support\SwitchesVehicle; hidden with
    a single vehicle.
--}}
@if ($vehicles->count() > 1)
    <column class="w-full gap-2">
        @foreach ($vehicles->chunk(3) as $row)
            <row class="w-full gap-2">
                @foreach ($row as $vehicle)
                    <button
                        variant="{{ $vehicle->is($currentVehicle) ? 'primary' : 'ghost' }}"
                        @press="switchVehicle({{ $vehicle->id }})"
                        a11y-label="{{ $vehicle->name }}{{ $vehicle->is($currentVehicle) ? ', selected' : '' }}"
                        class="flex-1"
                    >{{ $vehicle->name }}</button>
                @endforeach
            </row>
        @endforeach
    </column>
@endif
