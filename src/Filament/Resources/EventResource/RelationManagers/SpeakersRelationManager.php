<?php

namespace AtxDigital\Ticketing\Filament\Resources\EventResource\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SpeakersRelationManager extends RelationManager
{
    protected static string $relationship = 'speakers';

    /**
     * Speakers themselves are managed in the Speaker resource; here they are
     * attached to the event with an optional free-text role (keynote,
     * panellist, …) stored on the pivot.
     */
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('role')
                ->maxLength(255)
                ->placeholder('e.g. Keynote'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('organisation'),
                TextColumn::make('role')->placeholder('—'),
            ])
            ->headerActions([
                AttachAction::make()->preloadRecordSelect(),
            ])
            ->recordActions([
                EditAction::make()->label('Edit role'),
                DetachAction::make(),
            ]);
    }
}
