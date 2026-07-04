<?php

namespace AtxDigital\Ticketing\Registration;

use AtxDigital\Ticketing\Contracts\QuestionTypeContract;
use AtxDigital\Ticketing\Models\RegistrationQuestion;
use Filament\Forms\Components\Field;
use InvalidArgumentException;

/**
 * Turns an event's RegistrationQuestion set into Filament form fields and
 * validation rules at runtime. Question types are resolved through
 * config('ticketing.question_types'), so new types need no migration —
 * just a registered QuestionTypeContract implementation.
 */
class RegistrationFormBuilder
{
    /**
     * @param  iterable<RegistrationQuestion>  $questions
     * @return array<int, Field>
     */
    public function fields(iterable $questions): array
    {
        $fields = [];

        foreach ($questions as $question) {
            $fields[] = $this->resolveType($question)->makeField($question);
        }

        return $fields;
    }

    /**
     * Validation rules for submitted answers keyed "{prefix}.{question id}".
     *
     * @param  iterable<RegistrationQuestion>  $questions
     * @return array<string, array<int, mixed>>
     */
    public function validationRules(iterable $questions, string $prefix = 'answers'): array
    {
        $rules = [];

        foreach ($questions as $question) {
            $type = $this->resolveType($question);

            $rules["{$prefix}.{$question->getKey()}"] = [
                $question->is_required ? 'required' : 'nullable',
                ...$type->rules($question),
            ];
        }

        return $rules;
    }

    public function castValue(RegistrationQuestion $question, mixed $value): ?string
    {
        return $this->resolveType($question)->castValue($value);
    }

    public function resolveType(RegistrationQuestion $question): QuestionTypeContract
    {
        $map = (array) config('ticketing.question_types', []);
        $class = $map[$question->type] ?? null;

        if ($class === null || ! is_a($class, QuestionTypeContract::class, true)) {
            throw new InvalidArgumentException(
                "No question type registered for [{$question->type}]. Check config('ticketing.question_types')."
            );
        }

        return app($class);
    }

    /**
     * Type options for admin selects: ['text' => 'Text', ...].
     *
     * @return array<string, string>
     */
    public static function typeOptions(): array
    {
        $options = [];

        foreach ((array) config('ticketing.question_types', []) as $key => $class) {
            $options[$key] = app($class)->label();
        }

        return $options;
    }
}
