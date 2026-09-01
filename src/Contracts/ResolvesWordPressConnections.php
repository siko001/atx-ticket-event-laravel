<?php

namespace AtxDigital\Ticketing\Contracts;

use AtxDigital\Ticketing\Models\Connection;

/**
 * Optional capability for host-provided WordPress connections that need to
 * resolve historical orders after a connection is no longer an active target.
 */
interface ResolvesWordPressConnections
{
    public function resolve(string $reference): ?Connection;
}
