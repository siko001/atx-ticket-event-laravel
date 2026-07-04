<?php

namespace AtxDigital\Ticketing\Filament\Resources\SponsorResource\Pages;

use AtxDigital\Ticketing\Filament\Resources\SponsorResource;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Tables\Table;

class ManageSponsors extends ManageRecords
{
    protected static string $resource = SponsorResource::class;

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
