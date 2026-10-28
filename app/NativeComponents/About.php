<?php

namespace App\NativeComponents;

use App\Models\Setting;
use App\SpeedTracker\SpeedTracker;
use App\Support\OpenSourceLicenses;
use App\Support\UpdateCheck;
use App\Tasks\CheckForUpdate;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Facades\Browser;

/**
 * Version, update check, licence, links and open source credits.
 */
class About extends NativeComponent
{
    public const WEBSITE = 'https://usmanghanni.github.io/TripoMeter/';

    public const PRIVACY_POLICY = self::WEBSITE.'privacy.html';

    public const SOURCE = 'https://github.com/usmanghanni/TripoMeter';

    public const LICENSE = self::SOURCE.'/blob/main/LICENSE.md';

    public bool $updateChecks = true;

    public bool $isChecking = false;

    /**
     * Result of the last "Check now".
     */
    public ?string $checkMessage = null;

    /**
     * @var array{version: string, installed: string, url: string, page: string, notes: string}|null
     */
    public ?array $update = null;

    public function mount(): void
    {
        $this->updateChecks = (bool) (Setting::current()->update_checks ?? true);
        $this->update = UpdateCheck::available(includeDismissed: true);
    }

    public function navTitle(): string
    {
        return 'About';
    }

    /**
     * @return array{version: string, code: int, debug: bool}|null
     */
    #[Computed(persist: true)]
    public function appInfo(): ?array
    {
        return SpeedTracker::appInfo();
    }

    /**
     * @return list<array{name: string, version?: string, license: string}>
     */
    #[Computed(persist: true)]
    public function licenses(): array
    {
        return OpenSourceLicenses::all();
    }

    public function updatedUpdateChecks(): void
    {
        Setting::current()->update(['update_checks' => $this->updateChecks]);
    }

    public function checkNow(): void
    {
        $this->isChecking = true;
        $this->checkMessage = null;

        CheckForUpdate::dispatch()
            ->timeout(20)
            ->finished(function (bool $reachedGitHub): void {
                $this->isChecking = false;
                $this->update = UpdateCheck::available(includeDismissed: true);
                $this->checkMessage = match (true) {
                    $this->update !== null => null,
                    ! $reachedGitHub => 'Couldn\'t reach GitHub. Check your connection and try again.',
                    $this->appInfo === null => 'This build can\'t tell its version, so it can\'t compare.',
                    default => 'You have the latest version.',
                };
            })
            ->failed(function (): void {
                $this->isChecking = false;
                $this->checkMessage = 'Couldn\'t reach GitHub. Check your connection and try again.';
            });
    }

    public function downloadUpdate(): void
    {
        if ($this->update) {
            Browser::open($this->update['url']);
        }
    }

    public function openLink(string $link): void
    {
        $url = match ($link) {
            'website' => self::WEBSITE,
            'privacy' => self::PRIVACY_POLICY,
            'source' => self::SOURCE,
            'license' => self::LICENSE,
            'releases' => UpdateCheck::RELEASES_PAGE,
            default => null,
        };

        if ($url) {
            Browser::open($url);
        }
    }

    public function render(): View
    {
        return view('native.about');
    }
}
