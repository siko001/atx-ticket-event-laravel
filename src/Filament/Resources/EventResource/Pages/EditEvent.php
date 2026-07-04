<?php

namespace AtxDigital\Ticketing\Filament\Resources\EventResource\Pages;

use AtxDigital\Ticketing\Filament\Resources\EventResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEvent extends EditRecord
{
    protected static string $resource = EventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
