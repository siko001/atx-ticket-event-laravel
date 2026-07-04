<?php

namespace AtxDigital\Ticketing\Filament\Resources;

use AtxDigital\Ticketing\Enums\DiscountType;
use AtxDigital\Ticketing\Filament\Resources\DiscountCodeResource\Pages;
use AtxDigital\Ticketing\Models\TicketType;
use BackedEnum;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class DiscountCodeResource extends TicketingResource
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-ticket';

    protected static ?int $navigationSort = 3;

    public static function getModel(): string
    {
        return ticketing_model('discount_code');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')
                ->required()
                ->maxLength(64)
                ->unique(ignoreRecord: true),
            Select::make('type')
                ->options(DiscountType::class)
                ->default(DiscountType::Percentage)
                ->required()
                ->native(false)
                ->live(),
            TextInput::make('value')
                ->label(fn (Get $get): string => static::isFixed($get) ? 'Amount off' : 'Percent off')
                ->numeric()
                ->minValue(0)
                ->required()
                ->step(fn (Get $get): string => static::isFixed($get) ? '0.01' : '1')
                ->helperText(fn (Get $get): string => static::isFixed($get)
                    ? 'e.g. 5.50 takes 5.50 off the order.'
                    : 'e.g. 10 = 10% off (0–100).')
                ->formatStateUsing(fn ($state, Get $get) => static::isFixed($get) && $state !== null && $state !== ''
                    ? number_format(((int) $state) / 100, 2, '.', '')
                    : $state)
                ->dehydrateStateUsing(fn ($state, Get $get) => $state === null || $state === ''
                    ? null
                    : (static::isFixed($get) ? (int) round(((float) $state) * 100) : (int) $state)),
            TextInput::make('max_uses')
                ->numeric()
                ->minValue(1)
                ->helperText('Leave empty for unlimited uses.'),
            DateTimePicker::make('valid_from')->seconds(false),
            DateTimePicker::make('valid_until')->seconds(false)->after('valid_from'),
            Select::make('ticket_type_ids')
                ->label('Limit to ticket types')
                ->multiple()
                ->options(function (): array {
                    /** @var Collection<int, TicketType> $ticketTypes */
                    $ticketTypes = ticketing_model('ticket_type')::query()->with('event')->get();

                    return $ticketTypes
                        ->mapWithKeys(fn ($ticketType) => [
                            $ticketType->getKey() => ($ticketType->event->title ?? '?').' — '.$ticketType->name,
                        ])
                        ->all();
                })
                ->helperText('Leave empty to apply to all ticket types.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('type')->badge(),
                TextColumn::make('value')
                    ->label('Discount')
                    ->formatStateUsing(fn ($state, $record): string => $record->type === DiscountType::Fixed
                        ? ticketing_money((int) $state)
                        : $state.'%'),
                TextColumn::make('uses_count')
                    ->label('Uses')
                    ->formatStateUsing(fn ($record): string => $record->uses_count.($record->max_uses !== null ? ' / '.$record->max_uses : '')),
                TextColumn::make('valid_from')->dateTime()->toggleable(),
                TextColumn::make('valid_until')->dateTime()->toggleable(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageDiscountCodes::route('/'),
        ];
    }

    /**
     * Whether the form's current discount type is "fixed" (money, entered in
     * major units) as opposed to "percentage".
     */
    protected static function isFixed(Get $get): bool
    {
        $type = $get('type');

        if ($type instanceof DiscountType) {
            return $type === DiscountType::Fixed;
        }

        return DiscountType::tryFrom((string) $type) === DiscountType::Fixed;
    }
}
