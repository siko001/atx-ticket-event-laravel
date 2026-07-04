<?php

namespace AtxDigital\Ticketing\Registration\QuestionTypes;

use AtxDigital\Ticketing\Contracts\QuestionTypeContract;
use AtxDigital\Ticketing\Models\RegistrationQuestion;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Field;

class CheckboxQuestion implements QuestionTypeContract
{
    public function label(): string
    {
        return 'Checkbox';
    }

    public function makeField(RegistrationQuestion $question): Field
    {
        return Checkbox::make('question_'.$question->getKey())
            ->label($question->label)
            ->accepted($question->is_required);
    }

    public function rules(RegistrationQuestion $question): array
    {
        return $question->is_required ? ['accepted'] : ['boolean'];
    }

    public function castValue(mixed $value): ?string
    {
        return filter_var($value, FILTER_VALIDATE_BOOL) ? '1' : '0';
    }
}
