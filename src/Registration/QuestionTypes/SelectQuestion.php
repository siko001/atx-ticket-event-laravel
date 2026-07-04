<?php

namespace AtxDigital\Ticketing\Registration\QuestionTypes;

use AtxDigital\Ticketing\Contracts\QuestionTypeContract;
use AtxDigital\Ticketing\Models\RegistrationQuestion;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Illuminate\Validation\Rule;

class SelectQuestion implements QuestionTypeContract
{
    public function label(): string
    {
        return 'Dropdown';
    }

    public function makeField(RegistrationQuestion $question): Field
    {
        $options = array_values($question->options ?? []);

        return Select::make('question_'.$question->getKey())
            ->label($question->label)
            ->required($question->is_required)
            ->options(array_combine($options, $options));
    }

    public function rules(RegistrationQuestion $question): array
    {
        return ['string', Rule::in(array_values($question->options ?? []))];
    }

    public function castValue(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }
}
