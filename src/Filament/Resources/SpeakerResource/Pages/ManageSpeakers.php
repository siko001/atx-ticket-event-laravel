<?php

namespace AtxDigital\Ticketing\Filament\Resources\SpeakerResource\Pages;

use AtxDigital\Ticketing\Filament\Resources\SpeakerResource;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Tables\Table;

class ManageSpeakers extends ManageRecords
{
    protected static string $resource = SpeakerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function table(Table $table): Table
    {
        return parent::table($table)->recordActions([
            EditAction::make(),
            DeleteAction::make(),
        ]);
    }
}
