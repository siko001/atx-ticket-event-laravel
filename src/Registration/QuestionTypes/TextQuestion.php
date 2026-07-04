<?php

namespace AtxDigital\Ticketing\Registration\QuestionTypes;

use AtxDigital\Ticketing\Contracts\QuestionTypeContract;
use AtxDigital\Ticketing\Models\RegistrationQuestion;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\TextInput;

class TextQuestion implements QuestionTypeContract
{
    public function label(): string
    {
        return 'Text';
    }

    public function makeField(RegistrationQuestion $question): Field
    {
        return TextInput::make('question_'.$question->getKey())
            ->label($question->label)
            ->required($question->is_required)
            ->maxLength(1000);
    }

    public function rules(RegistrationQuestion $question): array
    {
        return ['string', 'max:1000'];
    }

    public function castValue(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }
}
