<?php

namespace AtxDigital\Ticketing\Filament\Resources\EventResource\RelationManagers;

use AtxDigital\Ticketing\Filament\MoneyInput;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class TicketTypesRelationManager extends RelationManager
{
    protected static string $relationship = 'ticketTypes';

    /**
     * Rule types the form has dedicated fields for; anything else (custom
     * client rules) falls back to a raw key/value editor.
     */
    protected const BUILT_IN_RULE_TYPES = ['early_bird', 'promo_code', 'quantity_break'];

    /**
     * @return array<string, string>
     */
    public static function ruleTypeOptions(): array
    {
        $labels = [
            'early_bird' => 'Early bird — cheaper until a date',
            'promo_code' => 'Promo code — buyer enters a discount code',
            'quantity_break' => 'Quantity break — bulk discount',
        ];

        $options = [];

        foreach (array_keys((array) config('ticketing.pricing_rules', [])) as $type) {
            $options[$type] = $labels[$type] ?? Str::headline($type);
        }

        return $options;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->datalist((array) config('ticketing.ticket_type_presets', [])),
            Textarea::make('description')
                ->rows(2),
            MoneyInput::make('base_price')
                ->label('Price')
                ->required()
                ->helperText('e.g. 25.00 — enter 0 for free tickets.'),
            TextInput::make('currency')
                ->default((string) config('ticketing.currency', 'eur'))
                ->required()
                ->maxLength(3),
            TextInput::make('quantity_available')
                ->numeric()
                ->minValue(1)
                ->helperText('Leave empty for unlimited.'),
            TextInput::make('sort_order')
                ->numeric()
                ->default(0),
            Toggle::make('is_active')
                ->default(true),
            Repeater::make('pricingRules')
                ->relationship()
                ->label('Automatic pricing rules')
                ->columnSpanFull()
                ->defaultItems(0)
                ->addActionLabel('Add pricing rule')
                ->itemLabel(fn (array $state): ?string => static::ruleTypeOptions()[$state['type'] ?? ''] ?? null)
                ->collapsible()
                ->schema([
                    Select::make('type')
                        ->options(static::ruleTypeOptions())
                        ->required()
                        ->live()
                        ->native(false)
                        ->helperText(fn (Get $get): string => $get('type') === 'promo_code'
                            ? 'No settings needed — this applies whichever valid discount code the buyer enters at checkout. (Codes work even without this rule.)'
                            : 'What kind of automatic discount this is.'),

                    // Early bird: date window.
                    DateTimePicker::make('config.starts_at')
                        ->label('Discount starts')
                        ->seconds(false)
                        ->helperText('Leave empty for "from now".')
                        ->visible(fn (Get $get): bool => $get('type') === 'early_bird'),
                    DateTimePicker::make('config.ends_at')
                        ->label('Discount ends')
                        ->seconds(false)
                        ->after('config.starts_at')
                        ->visible(fn (Get $get): bool => $get('type') === 'early_bird'),

                    // Quantity break: threshold.
                    TextInput::make('config.min_quantity')
                        ->label('Minimum tickets in the order')
                        ->numeric()
                        ->minValue(2)
                        ->helperText('The discount kicks in from this quantity of this ticket type.')
                        ->visible(fn (Get $get): bool => $get('type') === 'quantity_break'),

                    // Shared discount amount for early bird & quantity break.
                    Select::make('config.value_type')
                        ->label('Discount type')
                        ->options([
                            'percentage' => 'Percentage off',
                            'fixed' => 'Fixed amount off',
                        ])
                        ->default('percentage')
                        ->native(false)
                        ->live()
                        ->visible(fn (Get $get): bool => in_array($get('type'), ['early_bird', 'quantity_break'], true)),
                    TextInput::make('config.value')
                        ->label(fn (Get $get): string => $get('config.value_type') === 'fixed' ? 'Amount off (per ticket)' : 'Percent off')
                        ->numeric()
                        ->minValue(0)
                        ->step(fn (Get $get): string => $get('config.value_type') === 'fixed' ? '0.01' : '1')
                        ->helperText(fn (Get $get): string => $get('config.value_type') === 'fixed'
                            ? 'e.g. 5.50 takes 5.50 off each ticket.'
                            : 'e.g. 20 = 20% off.')
                        ->formatStateUsing(fn ($state, Get $get) => $get('config.value_type') === 'fixed' && $state !== null && $state !== ''
                            ? number_format(((int) $state) / 100, 2, '.', '')
                            : $state)
                        ->dehydrateStateUsing(fn ($state, Get $get) => $state === null || $state === ''
                            ? null
                            : ($get('config.value_type') === 'fixed' ? (int) round(((float) $state) * 100) : (int) $state))
                        ->visible(fn (Get $get): bool => in_array($get('type'), ['early_bird', 'quantity_break'], true)),

                    // Custom (client-registered) rule types keep a raw editor.
                    KeyValue::make('config')
                        ->label('Custom rule settings')
                        ->helperText('Settings passed to your custom rule class.')
                        ->visible(fn (Get $get): bool => filled($get('type'))
                            && ! in_array($get('type'), self::BUILT_IN_RULE_TYPES, true)),

                    TextInput::make('priority')
                        ->numeric()
                        ->default(0)
                        ->helperText('When several rules apply, lower numbers run first.'),
                    Toggle::make('is_active')
                        ->default(true),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('base_price')
                    ->money(fn ($record) => $record->currency, divideBy: 100)
                    ->sortable(),
                TextColumn::make('quantity_available')->placeholder('Unlimited'),
                TextColumn::make('pricing_rules_count')
                    ->counts('pricingRules')
                    ->label('Rules'),
                IconColumn::make('is_active')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
