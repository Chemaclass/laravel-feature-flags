<?php

declare(strict_types=1);

namespace Chemaclass\FeatureFlags\Sync;

use RuntimeException;

/**
 * Loads flag definitions from a PHP or JSON file and normalizes them into rows
 * keyed by their (key, scope_id, environment) identity.
 */
final class FlagDefinitionLoader
{
    /**
     * @return array<string, array<string, mixed>> identity => normalized definition
     */
    public function load(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Definitions file not found: {$path}");
        }

        $raw = str_ends_with($path, '.json')
            ? json_decode((string) file_get_contents($path), true)
            : require $path;

        if (! is_array($raw)) {
            throw new RuntimeException("Definitions file must return an array: {$path}");
        }

        $normalized = [];
        foreach ($raw as $definition) {
            if (! is_array($definition) || ! isset($definition['key'])) {
                throw new RuntimeException('Each definition needs at least a "key".');
            }

            $scopeId = $definition['scope_id'] ?? null;
            $environment = $definition['environment'] ?? null;
            $identity = $definition['key'].'|'.($scopeId ?? '').'|'.($environment ?? '');

            $normalized[$identity] = [
                'key' => (string) $definition['key'],
                'scope_id' => $scopeId,
                'environment' => $environment,
                'value' => (bool) ($definition['value'] ?? false),
                'rollout_percentage' => $definition['rollout_percentage'] ?? null,
                'hint' => $definition['hint'] ?? null,
                'is_dev' => (bool) ($definition['is_dev'] ?? false),
            ];
        }

        return $normalized;
    }

    public function identityOf(string $key, ?string $scopeId, ?string $environment): string
    {
        return $key.'|'.($scopeId ?? '').'|'.($environment ?? '');
    }
}
