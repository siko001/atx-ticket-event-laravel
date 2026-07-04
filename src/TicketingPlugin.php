<?php

namespace AtxDigital\Ticketing;

use AtxDigital\Ticketing\Filament\Pages\CheckInScanner;
use AtxDigital\Ticketing\Filament\Pages\Reports;
use AtxDigital\Ticketing\Filament\Resources\ConnectionResource;
use AtxDigital\Ticketing\Filament\Resources\DiscountCodeResource;
use AtxDigital\Ticketing\Filament\Resources\EventCategoryResource;
use AtxDigital\Ticketing\Filament\Resources\EventResource;
use AtxDigital\Ticketing\Filament\Resources\LogResource;
use AtxDigital\Ticketing\Filament\Resources\OrderResource;
use AtxDigital\Ticketing\Filament\Resources\SpeakerResource;
use AtxDigital\Ticketing\Filament\Resources\SponsorResource;
use AtxDigital\Ticketing\Filament\Widgets\TicketingStatsOverview;
use Filament\Contracts\Plugin;
use Filament\Panel;
use Filament\View\PanelsRenderHook;

class TicketingPlugin implements Plugin
{
    protected bool $checkInEnabled = true;

    protected bool $reportsEnabled = true;

    protected string $navigationGroup = 'Ticketing';

    /**
     * @var array<int, class-string>|null
     */
    protected ?array $resources = null;

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static */
        return filament(app(static::class)->getId());
    }

    public function getId(): string
    {
        return 'atx-ticketing';
    }

    public function checkInEnabled(bool $enabled = true): static
    {
        $this->checkInEnabled = $enabled;

        return $this;
    }

    public function reportsEnabled(bool $enabled = true): static
    {
        $this->reportsEnabled = $enabled;

        return $this;
    }

    public function navigationGroup(string $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    /**
     * Replace the default resource list — e.g. to swap in app-level
     * subclasses of the package resources.
     *
     * @param  array<int, class-string>  $resources
     */
    public function resources(array $resources): static
    {
        $this->resources = $resources;

        return $this;
    }

    public function getNavigationGroup(): string
    {
        return $this->navigationGroup;
    }

    public function hasCheckIn(): bool
    {
        return $this->checkInEnabled && (bool) config('ticketing.features.check_in', true);
    }

    public function register(Panel $panel): void
    {
        $panel->resources($this->resources ?? [
            EventResource::class,
            OrderResource::class,
            DiscountCodeResource::class,
            EventCategoryResource::class,
            SpeakerResource::class,
            SponsorResource::class,
            ConnectionResource::class,
            LogResource::class,
        ]);

        $pages = [];

        if ($this->reportsEnabled) {
            $pages[] = Reports::class;
        }

        if ($this->hasCheckIn()) {
            $pages[] = CheckInScanner::class;

            // Floating quick-access scanner button on every panel page.
            $panel->renderHook(
                PanelsRenderHook::BODY_END,
                function (): string {
                    if (! $this->hasCheckIn() || ! CheckInScanner::canAccess()) {
                        return '';
                    }

                    return view('ticketing::filament.scanner-fab')->render();
                },
            );
        }

        $panel->pages($pages);

        if ((bool) config('ticketing.features.dashboard_metrics', true)) {
            $panel->widgets([TicketingStatsOverview::class]);
        }
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
