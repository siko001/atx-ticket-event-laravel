<?php

namespace AtxDigital\Ticketing\Filament\Resources;

use AtxDigital\Ticketing\Enums\EventStatus;
use AtxDigital\Ticketing\Filament\Resources\EventResource\Pages;
use AtxDigital\Ticketing\Filament\Resources\EventResource\RelationManagers;
use BackedEnum;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class EventResource extends TicketingResource
{
    /**
     * Accepted upload types for event media (images and web-safe video).
     *
     * @var array<int, string>
     */
    public const MEDIA_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/avif',
        'video/mp4',
        'video/webm',
        'video/quicktime',
    ];

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?int $navigationSort = 1;

    public static function getModel(): string
    {
        return ticketing_model('event');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(static::formComponents());
    }

    /**
     * Split out so host apps can extend the resource and append components
     * without re-declaring the whole form.
     *
     * @return array<int, mixed>
     */
    public static function formComponents(): array
    {
        return [
            Section::make('Details')
                ->columns(2)
                ->schema([
                    TextInput::make('title')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (string $operation, ?string $state, Set $set) {
                            if ($operation === 'create') {
                                $set('slug', Str::slug((string) $state));
                            }
                        }),
                    TextInput::make('slug')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),
                    Select::make('status')
                        ->options(EventStatus::class)
                        ->default(EventStatus::Draft)
                        ->required()
                        ->native(false)
                        ->live()
                        ->afterStateUpdated(function ($state, $livewire): void {
                            // Prompt to cascade the status to the event's dates
                            // as soon as it changes (Edit page only).
                            if ($livewire instanceof Pages\EditEvent) {
                                $livewire->onStatusChanged($state);
                            }
                        }),
                    Select::make('timezone')
                        ->options(array_combine(timezone_identifiers_list(), timezone_identifiers_list()))
                        ->searchable()
                        ->default('UTC')
                        ->required(),
                    Select::make('categories')
                        ->relationship('categories', 'name')
                        ->multiple()
                        ->preload(),
                    TextInput::make('max_capacity')
                        ->numeric()
                        ->minValue(1)
                        ->helperText('Applies per occurrence. Leave empty for unlimited.'),
                    Toggle::make('requires_attendee_details')
                        ->label('Name every ticket')
                        ->columnSpanFull()
                        ->helperText('Buyers must enter a name (and optional email) for each ticket — "Ticket 1: Mary, Ticket 2: John". Off = only the buyer\'s details are collected and tickets are issued in their name.'),
                    RichEditor::make('description')
                        ->columnSpanFull(),
                ]),
            Section::make('Media')
                ->description('Images or video (mp4/webm/mov), max 100 MB per file.')
                ->collapsible()
                ->schema([
                    FileUpload::make('image')
                        ->label('Main image or video')
                        ->acceptedFileTypes(self::MEDIA_MIME_TYPES)
                        ->maxSize(102400)
                        ->disk((string) config('ticketing.storage.media_disk', 'public'))
                        ->directory('ticketing/events')
                        ->helperText('Shown as the event\'s main/featured media on the website.'),
                    FileUpload::make('gallery')
                        ->label('Gallery (images & videos)')
                        ->acceptedFileTypes(self::MEDIA_MIME_TYPES)
                        ->maxSize(102400)
                        ->multiple()
                        ->reorderable()
                        ->panelLayout('grid')
                        ->disk((string) config('ticketing.storage.media_disk', 'public'))
                        ->directory('ticketing/events/gallery')
                        ->helperText('Extra photos and clips shown in a gallery on the event page.'),
                ]),
            Section::make('Venue')
                ->description('Leave empty for online events.')
                ->columns(2)
                ->collapsible()
                ->schema([
                    TextInput::make('venue_name')->maxLength(255),
                    TextInput::make('venue_address')->maxLength(255),
                    TextInput::make('venue_lat')->numeric()->label('Latitude'),
                    TextInput::make('venue_lng')->numeric()->label('Longitude'),
                ]),
            Section::make('Schedule')
                ->description(fn (string $operation): string => $operation === 'create'
                    ? 'Set when the event first takes place. One-off events need nothing else here.'
                    : 'Dates are managed in the Occurrences tab below — add, edit or cancel individual dates there.')
                ->columns(2)
                ->collapsible()
                ->schema([
                    DateTimePicker::make('first_starts_at')
                        ->label('Starts at')
                        ->seconds(false)
                        ->required()
                        ->dehydrated(false)
                        ->visibleOn('create')
                        ->helperText('Creates the event\'s first date.'),
                    DateTimePicker::make('first_ends_at')
                        ->label('Ends at')
                        ->seconds(false)
                        ->after('first_starts_at')
                        ->dehydrated(false)
                        ->visibleOn('create'),
                    Toggle::make('is_recurring')
                        ->label('This event repeats')
                        ->helperText('Turn on for a weekly/monthly series — future dates are then generated automatically.')
                        ->live()
                        ->columnSpanFull(),
                    TextInput::make('recurrence_rule')
                        ->label('Repeat rule (RRULE)')
                        ->placeholder('FREQ=WEEKLY;BYDAY=TU')
                        ->helperText(new HtmlString(
                            '<span style="font-weight:600;">Common examples:</span>'
                            .'<ul style="list-style: disc; margin: 0.3rem 0 0.5rem 1.25rem; display: grid; gap: 0.2rem;">'
                            .'<li><code>FREQ=WEEKLY;BYDAY=TU</code> — every Tuesday</li>'
                            .'<li><code>FREQ=WEEKLY;INTERVAL=2;BYDAY=TH</code> — every other Thursday</li>'
                            .'<li><code>FREQ=MONTHLY;BYDAY=1TU</code> — first Tuesday of each month</li>'
                            .'<li>add <code>;COUNT=10</code> to stop after 10 dates</li>'
                            .'</ul>'
                            .'Dates are generated ~12 months ahead (topped up daily) using the time and duration of the first occurrence. '
                            .'<a href="https://icalendar.org/rrule-tool.html" target="_blank" rel="noopener" style="text-decoration: underline;">Build a rule visually ↗</a>'
                        ))
                        ->visible(fn (Get $get): bool => (bool) $get('is_recurring'))
                        ->requiredIfAccepted('is_recurring')
                        ->columnSpanFull(),
                ]),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->sortable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('categories.name')->badge()->separator(','),
                TextColumn::make('occurrences_count')
                    ->counts('occurrences')
                    ->label('Occurrences'),
                TextColumn::make('published_at')->dateTime()->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(EventStatus::class),
                SelectFilter::make('categories')->relationship('categories', 'name'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\OccurrencesRelationManager::class,
            RelationManagers\TicketTypesRelationManager::class,
            RelationManagers\SpeakersRelationManager::class,
            RelationManagers\SponsorsRelationManager::class,
            RelationManagers\RegistrationQuestionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEvents::route('/'),
            'create' => Pages\CreateEvent::route('/create'),
            'edit' => Pages\EditEvent::route('/{record}/edit'),
        ];
    }
}
