<?php

namespace AtxDigital\Ticketing\Filament\Resources\EventResource\RelationManagers;

use AtxDigital\Ticketing\Enums\OccurrenceStatus;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OccurrencesRelationManager extends RelationManager
{
    protected static string $relationship = 'occurrences';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            DateTimePicker::make('starts_at')
                ->required()
                ->seconds(false),
            DateTimePicker::make('ends_at')
                ->seconds(false)
                ->after('starts_at'),
            TextInput::make('capacity')
                ->numeric()
                ->minValue(1)
                ->helperText('Overrides the event capacity for this date. Empty = event default.'),
            Select::make('status')
                ->options(OccurrenceStatus::class)
                ->default(OccurrenceStatus::Scheduled)
                ->required()
                ->native(false),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('starts_at')->dateTime()->sortable(),
                TextColumn::make('ends_at')->dateTime(),
                TextColumn::make('capacity')->placeholder('Event default'),
                TextColumn::make('status')
                    ->badge()
                    ->state(fn ($record): string => $record->displayStatus())
                    ->color(fn (string $state): string => match ($state) {
                        'Cancelled' => 'danger',
                        'Past' => 'gray',
                        default => 'success',
                    }),
            ])
            ->defaultSort('starts_at')
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
