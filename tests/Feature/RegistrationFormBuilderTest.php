<?php

use AtxDigital\Ticketing\Models\RegistrationQuestion;
use AtxDigital\Ticketing\Registration\RegistrationFormBuilder;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;

it('maps question types to Filament fields', function () {
    $questions = collect([
        RegistrationQuestion::factory()->create(['type' => 'text']),
        RegistrationQuestion::factory()->create(['type' => 'textarea']),
        RegistrationQuestion::factory()->select(['A', 'B'])->create(),
        RegistrationQuestion::factory()->create(['type' => 'checkbox']),
        RegistrationQuestion::factory()->create(['type' => 'radio', 'options' => ['X', 'Y']]),
    ]);

    $fields = app(RegistrationFormBuilder::class)->fields($questions);

    expect($fields[0])->toBeInstanceOf(TextInput::class)
        ->and($fields[1])->toBeInstanceOf(Textarea::class)
        ->and($fields[2])->toBeInstanceOf(Select::class)
        ->and($fields[3])->toBeInstanceOf(Checkbox::class)
        ->and($fields[4])->toBeInstanceOf(Radio::class)
        ->and($fields[0]->getName())->toBe('question_'.$questions[0]->getKey());
});

it('builds validation rules keyed by question id', function () {
    $required = RegistrationQuestion::factory()->required()->create(['type' => 'text']);
    $optional = RegistrationQuestion::factory()->select(['S', 'M'])->create();

    $rules = app(RegistrationFormBuilder::class)->validationRules(collect([$required, $optional]));

    expect($rules["answers.{$required->getKey()}"][0])->toBe('required')
        ->and($rules["answers.{$optional->getKey()}"][0])->toBe('nullable');
});

it('throws a helpful error for unregistered question types', function () {
    $question = RegistrationQuestion::factory()->create(['type' => 'starsign']);

    app(RegistrationFormBuilder::class)->resolveType($question);
})->throws(InvalidArgumentException::class, 'starsign');

it('casts checkbox answers to stored strings', function () {
    $question = RegistrationQuestion::factory()->create(['type' => 'checkbox']);

    $builder = app(RegistrationFormBuilder::class);

    expect($builder->castValue($question, true))->toBe('1')
        ->and($builder->castValue($question, 'false'))->toBe('0');
});
