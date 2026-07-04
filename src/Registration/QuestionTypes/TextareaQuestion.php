<?php

namespace AtxDigital\Ticketing\Registration\QuestionTypes;

use AtxDigital\Ticketing\Contracts\QuestionTypeContract;
use AtxDigital\Ticketing\Models\RegistrationQuestion;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Textarea;

class TextareaQuestion implements QuestionTypeContract
{
    public function label(): string
    {
        return 'Long text';
    }

    public function makeField(RegistrationQuestion $question): Field
    {
        return Textarea::make('question_'.$question->getKey())
            ->label($question->label)
            ->required($question->is_required)
            ->rows(4)
            ->maxLength(5000);
    }

    public function rules(RegistrationQuestion $question): array
    {
        return ['string', 'max:5000'];
    }

    public function castValue(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }
}
