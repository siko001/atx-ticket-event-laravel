<?php

namespace AtxDigital\Ticketing\Filament\Resources\OrderResource\RelationManagers;

use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendeesRelationManager extends RelationManager
{
    protected static string $relationship = 'attendees';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('orderItem.ticketType.name')->label('Ticket'),
                TextColumn::make('checked_in_at')
                    ->dateTime()
                    ->placeholder('Not checked in'),
            ])
            ->recordActions([
                Action::make('downloadTicket')
                    ->label('Ticket PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->visible(fn ($record): bool => filled($record->ticket_pdf_path))
                    ->action(function ($record): StreamedResponse {
                        return Storage::disk((string) config('ticketing.storage.disk', 'local'))
                            ->download($record->ticket_pdf_path, "ticket-{$record->getKey()}.pdf");
                    }),
            ]);
    }
}
