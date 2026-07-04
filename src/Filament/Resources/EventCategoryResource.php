<?php

namespace AtxDigital\Ticketing\Filament\Resources;

use AtxDigital\Ticketing\Filament\Resources\EventCategoryResource\Pages;
use BackedEnum;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class EventCategoryResource extends TicketingResource
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'category';

    public static function getModel(): string
    {
        return ticketing_model('event_category');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(fn (?string $state, Set $set) => $set('slug', Str::slug((string) $state))),
            TextInput::make('slug')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),
            ColorPicker::make('colour'),
            Select::make('parent_id')
                ->label('Parent category')
                ->relationship('parent', 'name', ignoreRecord: true)
                ->preload(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('slug'),
                ColorColumn::make('colour'),
                TextColumn::make('parent.name')->placeholder('—'),
                TextColumn::make('events_count')->counts('events')->label('Events'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageEventCategories::route('/'),
        ];
    }
}
