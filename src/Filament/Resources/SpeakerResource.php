<?php

namespace AtxDigital\Ticketing\Filament\Resources;

use AtxDigital\Ticketing\Filament\Resources\SpeakerResource\Pages;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SpeakerResource extends TicketingResource
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?int $navigationSort = 5;

    public static function getModel(): string
    {
        return ticketing_model('speaker');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255),
            TextInput::make('organisation')
                ->maxLength(255),
            FileUpload::make('photo')
                ->image()
                ->disk((string) config('ticketing.storage.media_disk', 'public'))
                ->directory('ticketing/speakers'),
            Textarea::make('bio')
                ->rows(4),
            KeyValue::make('social_links')
                ->keyLabel('Network')
                ->valueLabel('URL')
                ->helperText('e.g. linkedin → https://linkedin.com/in/…'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('photo')
                    ->disk((string) config('ticketing.storage.media_disk', 'public'))
                    ->circular(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('organisation')->searchable(),
                TextColumn::make('events_count')->counts('events')->label('Events'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageSpeakers::route('/'),
        ];
    }
}
