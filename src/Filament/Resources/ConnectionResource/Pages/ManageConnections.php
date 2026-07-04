<?php

namespace AtxDigital\Ticketing\Filament\Resources\ConnectionResource\Pages;

use AtxDigital\Ticketing\Filament\Resources\ConnectionResource;
use AtxDigital\Ticketing\Models\Connection;
use AtxDigital\Ticketing\Support\Settings;
use Filament\Resources\Pages\ManageRecords;
use Throwable;

class ManageConnections extends ManageRecords
{
    protected static string $resource = ConnectionResource::class;

    /**
     * One-time upgrade: materialise the legacy single connection (settings
     * or .env) as an editable row the first time the screen is opened.
     */
    public function mount(): void
    {
        parent::mount();

        try {
            if (Connection::query()->exists()) {
                return;
            }

            $url = (string) Settings::get('wp.webhook_url', '') ?: (string) config('ticketing.wp_webhook_url');
            $secret = (string) Settings::get('wp.webhook_secret', '') ?: (string) config('ticketing.wp_webhook_secret');

            if ($url === '' || $secret === '') {
                return;
            }

            Connection::query()->create([
                'name' => 'Default',
                'webhook_url' => $url,
                'webhook_secret' => $secret,
                'is_active' => true,
            ]);
        } catch (Throwable) {
            // Table missing (migration not run yet) — the resource will error visibly anyway.
        }
    }
}
