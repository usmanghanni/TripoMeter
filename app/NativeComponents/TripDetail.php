<?php

namespace App\NativeComponents;

use App\Models\Setting;
use App\Models\Trip;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Facades\Dialog;

/**
 * Trip stats with an editable title and notes, and delete.
 */
class TripDetail extends NativeComponent
{
    public int $tripId = 0;

    public string $title = '';

    public string $notes = '';

    public function mount(): void
    {
        $trip = Trip::find($this->param('id'));

        if (! $trip) {
            $this->back();

            return;
        }

        $this->tripId = $trip->id;
        $this->title = (string) $trip->title;
        $this->notes = (string) $trip->notes;
    }

    public function navTitle(): string
    {
        return 'Trip';
    }

    #[Computed]
    public function trip(): ?Trip
    {
        return Trip::find($this->tripId);
    }

    #[Computed]
    public function settings(): Setting
    {
        return Setting::current();
    }

    public function save(): void
    {
        $this->trip?->update([
            'title' => trim($this->title) ?: null,
            'notes' => trim($this->notes) ?: null,
        ]);

        Dialog::toast('Trip saved');
    }

    public function confirmDelete(): void
    {
        Dialog::alert('Delete trip?', 'This permanently removes the trip and its recorded route.', ['Cancel', 'Delete'])
            ->id('delete-trip')
            ->buttonPressed(function (object $event): void {
                if (($event->label ?? null) === 'Delete') {
                    Trip::whereKey($this->tripId)->delete();
                    $this->back();
                }
            });
    }

    public function render(): View
    {
        return view('native.trip-detail');
    }
}
