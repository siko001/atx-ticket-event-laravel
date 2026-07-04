<?php

namespace AtxDigital\Ticketing\Filament\Resources;

use AtxDigital\Ticketing\Filament\Resources\SponsorResource\Pages;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SponsorResource extends TicketingResource
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office';

    protected static ?int $navigationSort = 6;

    public static function getModel(): string
    {
        return ticketing_model('sponsor');
    }

    public static function form(Schema $schema): Schema
    {
        $tiers = (array) config('ticketing.sponsor_tiers', []);

        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255),
            TextInput::make('url')
                ->url()
                ->maxLength(255),
            $tiers === []
                ? TextInput::make('tier')->maxLength(255)
                : Select::make('tier')->options(array_combine($tiers, $tiers))->native(false),
            FileUpload::make('logo')
                ->image()
                ->disk((string) config('ticketing.storage.media_disk', 'public'))
                ->directory('ticketing/sponsors'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('logo')
                    ->disk((string) config('ticketing.storage.media_disk', 'public')),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('tier')->badge()->placeholder('—'),
                TextColumn::make('events_count')->counts('events')->label('Events'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageSponsors::route('/'),
        ];
    }
}
