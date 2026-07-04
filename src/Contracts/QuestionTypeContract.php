<?php

namespace AtxDigital\Ticketing\Contracts;

use AtxDigital\Ticketing\Models\RegistrationQuestion;
use Filament\Forms\Components\Field;

interface QuestionTypeContract
{
    /**
     * Human-readable label shown when picking a question type in the admin.
     */
    public function label(): string;

    /**
     * Build the Filament form field for this question.
     */
    public function makeField(RegistrationQuestion $question): Field;

    /**
     * Laravel validation rules for a submitted answer to this question.
     *
     * @return array<int, mixed>
     */
    public function rules(RegistrationQuestion $question): array;

    /**
     * Normalise a submitted answer to the string stored in RegistrationResponse.
     */
    public function castValue(mixed $value): ?string;
}
