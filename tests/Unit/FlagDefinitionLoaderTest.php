<?php

declare(strict_types=1);

use Chemaclass\FeatureFlags\Sync\FlagDefinitionLoader;

beforeEach(function (): void {
    $this->loader = new FlagDefinitionLoader;
    $this->path = sys_get_temp_dir().'/ff_loader_'.uniqid();
});

afterEach(function (): void {
    @unlink($this->path);
    @unlink($this->path.'.json');
});

it('throws when the file is missing', function (): void {
    $this->loader->load($this->path.'/nope.php');
})->throws(RuntimeException::class, 'not found');

it('throws when the file does not return an array', function (): void {
    file_put_contents($this->path, '<?php return "nope";');
    $this->loader->load($this->path);
})->throws(RuntimeException::class, 'must return an array');

it('throws when a definition has no key', function (): void {
    file_put_contents($this->path, '<?php return [["value" => true]];');
    $this->loader->load($this->path);
})->throws(RuntimeException::class, 'needs at least a "key"');

it('loads and normalizes a PHP definitions file', function (): void {
    file_put_contents($this->path, '<?php return [["key" => "a", "value" => 1, "rollout_percentage" => 20]];');

    $defs = $this->loader->load($this->path);

    expect($defs)->toHaveKey('a||')
        ->and($defs['a||']['value'])->toBeTrue()
        ->and($defs['a||']['rollout_percentage'])->toBe(20)
        ->and($defs['a||']['is_dev'])->toBeFalse();
});

it('loads a JSON definitions file', function (): void {
    $json = $this->path.'.json';
    file_put_contents($json, json_encode([['key' => 'b', 'value' => false, 'scope_id' => 'team-1']]));

    $defs = $this->loader->load($json);

    expect($defs)->toHaveKey('b|team-1|')
        ->and($defs['b|team-1|']['scope_id'])->toBe('team-1');
});

it('builds a stable identity string', function (): void {
    expect($this->loader->identityOf('k', 'scope', 'prod'))->toBe('k|scope|prod')
        ->and($this->loader->identityOf('k', null, null))->toBe('k||');
});
