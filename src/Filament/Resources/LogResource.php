<?php

namespace AtxDigital\Ticketing\Filament\Resources;

use AtxDigital\Ticketing\Filament\Resources\LogResource\Pages;
use AtxDigital\Ticketing\Models\ActivityLog;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * System → Logs: read-only sync ("WordPress traffic") and order ("buy")
 * activity, mirrored by the Logs tab in the WP plugin settings.
 */
class LogResource extends TicketingResource
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $modelLabel = 'log entry';

    protected static ?string $pluralModelLabel = 'logs';

    protected static ?int $navigationSort = 90;

    public static function getModel(): string
    {
        return ActivityLog::class;
    }

    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime('d M Y, H:i:s')
                    ->sortable(),
                TextColumn::make('channel')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'sync' ? 'info' : 'success'),
                TextColumn::make('level')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'error' => 'danger',
                        'warning' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('message')
                    ->searchable()
                    ->wrap()
                    ->description(fn (ActivityLog $record): ?string => filled($record->context['purchaser_email'] ?? null)
                        ? (string) $record->context['purchaser_email']
                        : null),
            ])
            ->filters([
                SelectFilter::make('channel')
                    ->options(['sync' => 'Sync (WordPress)', 'order' => 'Orders (buys)']),
                SelectFilter::make('level')
                    ->options(['info' => 'Info', 'warning' => 'Warning', 'error' => 'Error']),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([])
            ->toolbarActions([])
            ->poll('30s');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(mixed $record): bool
    {
        return false;
    }

    public static function canDelete(mixed $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageLogs::route('/'),
        ];
    }
}
