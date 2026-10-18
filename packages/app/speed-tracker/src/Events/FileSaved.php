<?php

namespace App\SpeedTracker\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * The save dialog opened by SpeedTracker::saveFile() closed.
 */
class FileSaved
{
    use Dispatchable;

    public function __construct(
        public bool $saved = false,
        public bool $cancelled = false,
        public ?string $error = null,
    ) {}
}
