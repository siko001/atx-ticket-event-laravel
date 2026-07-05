<?php

namespace AtxDigital\Ticketing\Registration\QuestionTypes;

use AtxDigital\Ticketing\Contracts\QuestionTypeContract;
use AtxDigital\Ticketing\Models\RegistrationQuestion;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Field;

/**
 * A "choose any number" checkbox group with predefined options — distinct from
 * the single yes/no CheckboxQuestion. Answers are stored as a comma-separated
 * string of the chosen option labels.
 */
class CheckboxesQuestion implements QuestionTypeContract
{
    public function label(): string
    {
        return 'Checkboxes (choose multiple)';
    }

    public function makeField(RegistrationQuestion $question): Field
    {
        $options = array_values($question->options ?? []);

        return CheckboxList::make('question_'.$question->getKey())
            ->label($question->label)
            ->required($question->is_required)
            ->options(array_combine($options, $options))
            ->bulkToggleable();
    }

    public function rules(RegistrationQuestion $question): array
    {
        $options = array_values($question->options ?? []);

        return [
            function (string $attribute, mixed $value, callable $fail) use ($options): void {
                foreach (self::normalise($value) as $choice) {
                    if (! in_array($choice, $options, true)) {
                        $fail('The selected option is invalid.');

                        return;
                    }
                }
            },
        ];
    }

    public function castValue(mixed $value): ?string
    {
        $choices = self::normalise($value);

        return $choices === [] ? null : implode(', ', $choices);
    }

    /**
     * Accepts an array (Filament) or a comma-separated string (WordPress form)
     * and returns a clean list of chosen values.
     *
     * @return list<string>
     */
    private static function normalise(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        $items = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_filter(array_map(
            fn ($item): string => trim((string) $item),
            $items,
        ), fn (string $item): bool => $item !== ''));
    }
}
