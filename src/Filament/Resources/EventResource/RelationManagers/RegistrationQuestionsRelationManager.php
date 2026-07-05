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
                ->visible(fn (Get $get): bool => in_array($get('type'), ['select', 'radio', 'checkboxes'], true))
                ->helperText('The choices attendees pick from.')
                ->placeholder('Add an option and press Enter'),
            Select::make('ticket_type_ids')
                ->label('Only for ticket types')
                ->multiple()
                ->options(function (): array {
                    /** @var Event $event */
                    $event = $this->getOwnerRecord();

                    return $event->ticketTypes()->pluck('name', 'id')->all();
                })
                ->placeholder('All ticket types')
                ->native(false)
                ->helperText('Leave empty to ask every attendee. Pick one or more types to ask only those buyers (e.g. a meal choice for VIP and Standard).'),
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
                TextColumn::make('ticket_type_ids')
                    ->label('Ticket types')
                    ->state(function ($record): string {
                        $ids = array_map('intval', (array) ($record->ticket_type_ids ?? []));

                        if ($ids === []) {
                            return 'All';
                        }

                        return ticketing_model('ticket_type')::query()
                            ->whereIn('id', $ids)->pluck('name')->implode(', ');
                    }),
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
