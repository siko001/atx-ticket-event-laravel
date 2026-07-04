<?php

namespace AtxDigital\Ticketing\Filament\Resources\EventResource\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SponsorsRelationManager extends RelationManager
{
    protected static string $relationship = 'sponsors';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                ImageColumn::make('logo')
                    ->disk((string) config('ticketing.storage.media_disk', 'public')),
                TextColumn::make('name')->searchable(),
                TextColumn::make('tier')->badge()->placeholder('—'),
            ])
            ->headerActions([
                AttachAction::make()->preloadRecordSelect(),
            ])
            ->recordActions([
                DetachAction::make(),
            ]);
    }
}
