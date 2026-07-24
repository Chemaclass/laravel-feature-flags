<?php

declare(strict_types=1);

namespace Chemaclass\FeatureFlags\Targeting;

/**
 * Evaluates a flag's targeting rules against a caller-supplied context.
 *
 * A rule set is an ordered list; the first rule whose conditions all match wins
 * and yields its `then` boolean. All conditions inside a rule AND together;
 * rules OR (first match wins). A condition is either an attribute check
 * (`{attr, op, value}`) or a reference to a reusable segment (`{segment: name}`),
 * which matches when all of that segment's conditions match. A missing context
 * attribute or unknown segment makes its condition false, never throws. Returns
 * null when no rule matches, so the caller falls back to the flag's value.
 */
final class RuleEvaluator
{
    /**
     * @param  array<int, array{when?: array<int, array<string, mixed>>, then?: bool}>  $rules
     * @param  array<string, mixed>  $context
     * @param  array<string, list<array<string, mixed>>>  $segments  name => conditions
     */
    public function matches(array $rules, array $context, array $segments = []): ?bool
    {
        foreach ($rules as $rule) {
            $conditions = $rule['when'] ?? [];
            $allMatch = true;
            foreach ($conditions as $condition) {
                if (! $this->conditionMatches($condition, $context, $segments)) {
                    $allMatch = false;
                    break;
                }
            }

            if ($allMatch) {
                return (bool) ($rule['then'] ?? true);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $condition
     * @param  array<string, mixed>  $context
     * @param  array<string, list<array<string, mixed>>>  $segments
     */
    private function conditionMatches(array $condition, array $context, array $segments): bool
    {
        if (isset($condition['segment'])) {
            $conditions = $segments[$condition['segment']] ?? null;
            if ($conditions === null) {
                return false;
            }
            foreach ($conditions as $inner) {
                if (! $this->attributeMatches($inner, $context)) {
                    return false;
                }
            }

            return true;
        }

        return $this->attributeMatches($condition, $context);
    }

    /**
     * @param  array<string, mixed>  $condition
     * @param  array<string, mixed>  $context
     */
    private function attributeMatches(array $condition, array $context): bool
    {
        $attr = $condition['attr'] ?? null;
        $op = $condition['op'] ?? 'eq';
        $expected = $condition['value'] ?? null;

        if ($attr === null || ! array_key_exists($attr, $context)) {
            return false;
        }

        $actual = $context[$attr];

        return match ($op) {
            'eq' => $actual === $expected,
            'neq' => $actual !== $expected,
            'in' => is_array($expected) && in_array($actual, $expected, true),
            'not_in' => is_array($expected) && ! in_array($actual, $expected, true),
            'gt' => is_numeric($actual) && is_numeric($expected) && $actual > $expected,
            'gte' => is_numeric($actual) && is_numeric($expected) && $actual >= $expected,
            'lt' => is_numeric($actual) && is_numeric($expected) && $actual < $expected,
            'lte' => is_numeric($actual) && is_numeric($expected) && $actual <= $expected,
            'contains' => is_string($actual) && is_string($expected) && str_contains($actual, $expected),
            'starts_with' => is_string($actual) && is_string($expected) && str_starts_with($actual, $expected),
            'ends_with' => is_string($actual) && is_string($expected) && str_ends_with($actual, $expected),
            default => false,
        };
    }
}
