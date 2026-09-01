<?php

namespace AtxDigital\Ticketing\Contracts;

use AtxDigital\Ticketing\Models\Connection;
use Illuminate\Support\Collection;

interface WordPressConnectionProvider
{
    /** @return Collection<int, Connection> */
    public function targets(): Collection;
}
