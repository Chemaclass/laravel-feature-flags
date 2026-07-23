<?php

declare(strict_types=1);

use Chemaclass\FeatureFlags\Targeting\RuleEvaluator;

beforeEach(function (): void {
    $this->eval = new RuleEvaluator;
});

it('returns null when no rule matches', function (): void {
    $rules = [['when' => [['attr' => 'plan', 'op' => 'eq', 'value' => 'pro']], 'then' => true]];

    expect($this->eval->matches($rules, ['plan' => 'free']))->toBeNull();
});

it('first matching rule wins', function (): void {
    $rules = [
        ['when' => [['attr' => 'plan', 'op' => 'eq', 'value' => 'pro']], 'then' => true],
        ['when' => [['attr' => 'country', 'op' => 'in', 'value' => ['DE']]], 'then' => false],
    ];

    expect($this->eval->matches($rules, ['plan' => 'pro', 'country' => 'DE']))->toBeTrue();
});

it('ANDs all conditions within a rule', function (): void {
    $rules = [[
        'when' => [
            ['attr' => 'plan', 'op' => 'eq', 'value' => 'pro'],
            ['attr' => 'country', 'op' => 'in', 'value' => ['DE', 'AT']],
        ],
        'then' => true,
    ]];

    expect($this->eval->matches($rules, ['plan' => 'pro', 'country' => 'DE']))->toBeTrue()
        ->and($this->eval->matches($rules, ['plan' => 'pro', 'country' => 'US']))->toBeNull();
});

it('missing attribute never throws and fails the condition', function (): void {
    $rules = [['when' => [['attr' => 'plan', 'op' => 'eq', 'value' => 'pro']], 'then' => true]];

    expect($this->eval->matches($rules, []))->toBeNull();
});

it('supports every operator', function (string $op, mixed $value, mixed $actual, bool $expected): void {
    $rules = [['when' => [['attr' => 'x', 'op' => $op, 'value' => $value]], 'then' => true]];

    expect($this->eval->matches($rules, ['x' => $actual]))->toBe($expected ? true : null);
})->with([
    ['eq', 'a', 'a', true],
    ['neq', 'a', 'b', true],
    ['in', ['a', 'b'], 'b', true],
    ['not_in', ['a', 'b'], 'c', true],
    ['gt', 5, 6, true],
    ['gte', 5, 5, true],
    ['lt', 5, 4, true],
    ['lte', 5, 5, true],
    ['contains', 'ell', 'hello', true],
    ['starts_with', 'he', 'hello', true],
    ['ends_with', 'lo', 'hello', true],
    ['eq', 'a', 'b', false],
    ['gt', 5, 4, false],
    ['unknown_op', 'a', 'a', false],
]);
