<?php

namespace AtxDigital\Ticketing\Filament\Resources;

use AtxDigital\Ticketing\Enums\EventStatus;
use AtxDigital\Ticketing\Filament\Resources\ConnectionResource\Pages;
use AtxDigital\Ticketing\Jobs\PushEventToWordPress;
use AtxDigital\Ticketing\Models\Connection;
use AtxDigital\Ticketing\Models\Event;
use AtxDigital\Ticketing\WordPress\ConnectionTester;
use AtxDigital\Ticketing\WordPress\EventPayloadBuilder;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;

/**
 * Ticketing → Connections: the WordPress sites this app pushes events to.
 * Publishing pushes to every active connection; the Active toggle swaps
 * sites in and out without deleting them.
 */
class ConnectionResource extends TicketingResource
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-link';

    protected static ?int $navigationSort = 10;

    public static function getModel(): string
    {
        return Connection::class;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->placeholder('Main website')
                ->helperText('A label so you know which site this is.'),
            TextInput::make('webhook_url')
                ->label('WordPress webhook URL')
                ->url()
                ->required()
                ->placeholder('https://your-site.com/wp-json/atx-ticketing/v1/webhook')
                ->helperText('Shown in that site\'s WP admin under Events → Settings → Connection.'),
            TextInput::make('webhook_secret')
                ->label('Shared secret')
                ->password()
                ->revealable()
                ->required()
                ->helperText(new HtmlString('Must match the secret saved in that site\'s plugin settings. Give <strong>each site its own secret</strong>.'))
                ->suffixAction(
                    Action::make('generateSecret')
                        ->label('Generate')
                        ->icon('heroicon-m-sparkles')
                        ->action(fn (Set $set) => $set('webhook_secret', bin2hex(random_bytes(32))))
                ),
            Toggle::make('is_active')
                ->label('Active')
                ->default(true)
                ->helperText('Inactive connections receive no pushes and their secret stops working — flip to swap sites.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->description(fn (Connection $record): string => $record->webhook_url),
                ToggleColumn::make('is_active')
                    ->label('Active'),
                IconColumn::make('last_test.ok')
                    ->label('Last test')
                    ->boolean()
                    ->placeholder('—')
                    ->tooltip(fn (Connection $record): ?string => $record->last_test['message'] ?? null),
                TextColumn::make('last_push.at')
                    ->label('Last push')
                    ->since()
                    ->placeholder('never')
                    ->description(fn (Connection $record): ?string => isset($record->last_push['ok'])
                        ? (($record->last_push['ok'] ? '✓ ' : '✗ ').($record->last_push['type'] ?? ''))
                        : null),
                TextColumn::make('last_pull.at')
                    ->label('Site last pulled')
                    ->since()
                    ->placeholder('never'),
            ])
            ->headerActions([
                CreateAction::make()->slideOver()->modalWidth('xl'),
            ])
            ->recordActions([
                Action::make('test')
                    ->label('Test')
                    ->icon('heroicon-o-signal')
                    ->action(function (Connection $record): void {
                        $result = app(ConnectionTester::class)->test($record);

                        Notification::make()
                            ->title($result['ok'] ? "\"{$record->name}\" connected" : "\"{$record->name}\" failed")
                            ->body($result['message'])
                            ->{$result['ok'] ? 'success' : 'danger'}()
                            ->persistent()
                            ->send();
                    }),
                Action::make('push')
                    ->label('Push events')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Connection $record): string => "Push all published events to \"{$record->name}\"?")
                    ->modalDescription('Existing mirrored events on that site are updated in place — safe to repeat.')
                    ->action(function (Connection $record): void {
                        $count = static::pushAllTo($record->getKey());

                        Notification::make()->success()
                            ->title('Push queued')
                            ->body("{$count} published event(s) queued for \"{$record->name}\".")
                            ->send();
                    }),
                EditAction::make()->slideOver()->modalWidth('xl'),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('No connections yet')
            ->emptyStateDescription('Add a WordPress site: its webhook URL is shown in the WP admin under Events → Settings → Connection. Until one is added, the TICKETING_WP_WEBHOOK_* values in .env are used as a fallback.');
    }

    /**
     * Queues a push of every published event to one connection (or all
     * active ones when null).
     */
    public static function pushAllTo(?int $connectionId): int
    {
        $payloadBuilder = app(EventPayloadBuilder::class);

        /** @var Collection<int, Event> $events */
        $events = ticketing_model('event')::query()
            ->where('status', EventStatus::Published)
            ->get();

        foreach ($events as $event) {
            PushEventToWordPress::dispatch('event.updated', $payloadBuilder->build($event), $connectionId);
        }

        return $events->count();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageConnections::route('/'),
        ];
    }
}
