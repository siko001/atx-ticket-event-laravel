<?php

namespace AtxDigital\Ticketing\Filament\Resources;

use AtxDigital\Ticketing\TicketingPlugin;
use Filament\Resources\Resource;
use Throwable;

abstract class TicketingResource extends Resource
{
    public static function getNavigationGroup(): ?string
    {
        try {
            return TicketingPlugin::get()->getNavigationGroup();
        } catch (Throwable) {
            return 'Ticketing';
        }
    }
}
