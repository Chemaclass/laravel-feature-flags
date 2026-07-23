<?php

declare(strict_types=1);

namespace Chemaclass\FeatureFlags\Variants;

/**
 * Deterministically picks one weighted variant for a key+scope. The same
 * key+scope always lands on the same variant, and the distribution across many
 * scopes approximates the configured weights. Weights need not sum to 100.
 */
final class VariantSelector
{
    /**
     * @param  array<int, array{name?: string, weight?: int|float}>  $variants
     */
    public function select(array $variants, string $key, ?string $scopeId): ?string
    {
        $valid = [];
        $total = 0.0;
        foreach ($variants as $variant) {
            $name = $variant['name'] ?? null;
            $weight = (float) ($variant['weight'] ?? 0);
            if ($name === null || $weight <= 0) {
                continue;
            }
            $valid[] = ['name' => $name, 'weight' => $weight];
            $total += $weight;
        }

        if ($valid === [] || $total <= 0) {
            return null;
        }

        // Salt differs from the rollout bucket so variant and rollout decisions
        // are independent for the same key+scope.
        $point = (crc32('variant:'.$key.':'.($scopeId ?? '')) % 10000) / 10000 * $total;

        $cursor = 0.0;
        foreach ($valid as $variant) {
            $cursor += $variant['weight'];
            if ($point < $cursor) {
                return $variant['name'];
            }
        }

        return $valid[array_key_last($valid)]['name'];
    }
}
