<?php

use AtxDigital\Ticketing\Filament\Widgets\TicketingStatsOverview;
use AtxDigital\Ticketing\Tests\Support\TestUser;
use AtxDigital\Ticketing\TicketingPlugin;
use Illuminate\Support\Facades\Gate;

it('allows dashboard metrics by default', function () {
    expect(TicketingStatsOverview::canView())->toBeTrue();
});

it('uses the host dashboard gate when one is defined', function () {
    $this->actingAs(new TestUser);
    Gate::define('ticketing.dashboard', fn (TestUser $user): bool => false);

    expect(TicketingStatsOverview::canView())->toBeFalse();
});

it('hides dashboard metrics when the feature is disabled', function () {
    config()->set('ticketing.features.dashboard_metrics', false);

    expect(TicketingStatsOverview::canView())->toBeFalse();
});

it('can keep metrics off the main dashboard for a dedicated dashboard page', function () {
    $plugin = TicketingPlugin::make();

    expect($plugin->hasDashboardMetricsOnMainDashboard())->toBeTrue()
        ->and($plugin->dashboardMetricsOnMainDashboard(false))->toBe($plugin)
        ->and($plugin->hasDashboardMetricsOnMainDashboard())->toBeFalse();
});
