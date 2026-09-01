<?php

namespace AtxDigital\Ticketing\WordPress;

use AtxDigital\Ticketing\Contracts\WordPressConnectionProvider;
use AtxDigital\Ticketing\Models\Connection;
use AtxDigital\Ticketing\Support\Settings;
use Illuminate\Support\Collection;
use Throwable;

class DatabaseWordPressConnectionProvider implements WordPressConnectionProvider
{
    /** @return Collection<int, Connection> */
    public function targets(): Collection
    {
        try {
            /** @var Collection<int, Connection> $connections */
            $connections = Connection::query()->where('is_active', true)->orderBy('id')->get();
        } catch (Throwable) {
            $connections = new Collection;
        }

        if ($connections->isNotEmpty()) {
            return $connections;
        }

        $legacy = $this->legacy();

        return $legacy === null ? new Collection : new Collection([$legacy]);
    }

    protected function legacy(): ?Connection
    {
        $url = (string) Settings::get('wp.webhook_url', '');
        $secret = (string) Settings::get('wp.webhook_secret', '');

        if ($url === '') {
            $url = (string) config('ticketing.wp_webhook_url');
            $secret = (string) config('ticketing.wp_webhook_secret');
        }

        if ($url === '' && $secret === '') {
            return null;
        }

        $connection = new Connection;
        $connection->name = 'Default';
        $connection->webhook_url = $url;
        $connection->webhook_secret = $secret;
        $connection->is_active = true;

        return $connection;
    }
}
