<?php

namespace AtxDigital\Ticketing\Filament\Resources\OrderResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ticketType.name')->label('Ticket'),
                TextColumn::make('quantity'),
                TextColumn::make('unit_price')
                    ->money(fn ($record) => $record->order->currency ?? config('ticketing.currency'), divideBy: 100),
                TextColumn::make('line_total')
                    ->label('Total')
                    ->state(fn ($record): int => $record->lineTotal())
                    ->money(fn ($record) => $record->order->currency ?? config('ticketing.currency'), divideBy: 100),
            ]);
    }
}
