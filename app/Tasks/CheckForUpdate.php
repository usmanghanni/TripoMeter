<?php

namespace App\Tasks;

use App\Support\UpdateCheck;
use Native\Mobile\AsyncTask;

/**
 * Fetches the latest GitHub release on a background PHP thread so a slow
 * network never holds up the screen.
 */
class CheckForUpdate extends AsyncTask
{
    public function handle(): bool
    {
        return UpdateCheck::fetchLatest() !== null;
    }
}
