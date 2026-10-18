<?php

namespace App\SpeedTracker\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * The open dialog from SpeedTracker::pickFile() closed. On success, `path` is
 * a private copy of the chosen file that PHP can read.
 */
class FilePicked
{
    use Dispatchable;

    public function __construct(
        public ?string $path = null,
        public ?string $name = null,
        public bool $cancelled = false,
        public ?string $error = null,
    ) {}
}
