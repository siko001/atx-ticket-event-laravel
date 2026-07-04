<?php

namespace AtxDigital\Ticketing\Filament\Resources\EventResource\RelationManagers;

use AtxDigital\Ticketing\Models\Event;
use AtxDigital\Ticketing\Registration\RegistrationFormBuilder;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RegistrationQuestionsRelationManager extends RelationManager
{
    protected static string $relationship = 'registrationQuestions';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('label')
                ->required()
                ->maxLength(255),
            Select::make('type')
                ->options(RegistrationFormBuilder::typeOptions())
                ->default('text')
                ->required()
                ->live()
                ->native(false),
            TagsInput::make('options')
                ->visible(fn (Get $get): bool => in_array($get('type'), ['select', 'radio'], true))
                ->placeholder('Add an option and press Enter'),
            Select::make('ticket_type_id')
                ->label('Only for ticket type')
                ->options(function (): array {
                    /** @var Event $event */
                    $event = $this->getOwnerRecord();

                    return $event->ticketTypes()->pluck('name', 'id')->all();
                })
                ->placeholder('All ticket types')
                ->native(false)
                ->helperText('Leave empty to ask every attendee. Pick a type to ask only buyers of that ticket (e.g. a meal choice just for VIP).'),
            Toggle::make('is_required'),
            TextInput::make('sort_order')
                ->numeric()
                ->default(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')->searchable(),
                TextColumn::make('type')->badge(),
                TextColumn::make('ticketType.name')
                    ->label('Ticket type')
                    ->placeholder('All'),
                IconColumn::make('is_required')->boolean(),
                TextColumn::make('sort_order')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
