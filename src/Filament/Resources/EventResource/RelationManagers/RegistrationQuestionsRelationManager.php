<?php

namespace AtxDigital\Ticketing\Filament\Resources\EventResource\RelationManagers;

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
