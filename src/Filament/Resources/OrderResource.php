<?php

namespace AtxDigital\Ticketing\Filament\Resources;

use AtxDigital\Ticketing\Enums\OrderStatus;
use AtxDigital\Ticketing\Filament\Resources\OrderResource\Pages;
use AtxDigital\Ticketing\Filament\Resources\OrderResource\RelationManagers;
use BackedEnum;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrderResource extends TicketingResource
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?int $navigationSort = 2;

    public static function getModel(): string
    {
        return ticketing_model('order');
    }

    public static function canCreate(): bool
    {
        return false; // Orders are only created through checkout.
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Order')
                ->columns(3)
                ->schema([
                    TextInput::make('order_number')->disabled(),
                    Select::make('status')->options(OrderStatus::class)->disabled(),
                    TextInput::make('currency')->disabled(),
                    TextInput::make('subtotal')->disabled()->formatStateUsing(fn (?int $state) => number_format(($state ?? 0) / 100, 2)),
                    TextInput::make('discount_total')->disabled()->formatStateUsing(fn (?int $state) => number_format(($state ?? 0) / 100, 2)),
                    TextInput::make('vat_total')->disabled()->label('VAT')->formatStateUsing(fn (?int $state) => number_format(($state ?? 0) / 100, 2)),
                    TextInput::make('total')->disabled()->formatStateUsing(fn (?int $state) => number_format(($state ?? 0) / 100, 2)),
                    DateTimePicker::make('paid_at')->disabled(),
                    TextInput::make('stripe_payment_intent_id')->disabled()->label('Payment intent'),
                ]),
            Section::make('Purchaser')
                ->columns(3)
                ->schema([
                    TextInput::make('purchaser_name')->disabled(),
                    TextInput::make('purchaser_email')->disabled(),
                    TextInput::make('purchaser_phone')->disabled(),
                    TextInput::make('purchaser_organisation')->disabled(),
                    TextInput::make('purchaser_country')->disabled(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')->searchable()->sortable(),
                TextColumn::make('event.title')->limit(30)->searchable(),
                TextColumn::make('purchaser_email')->searchable()->toggleable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('total')
                    ->money(fn ($record) => $record->currency, divideBy: 100)
                    ->sortable(),
                TextColumn::make('paid_at')->dateTime()->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(OrderStatus::class),
                SelectFilter::make('event')->relationship('event', 'title'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ItemsRelationManager::class,
            RelationManagers\AttendeesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'view' => Pages\ViewOrder::route('/{record}'),
        ];
    }
}
