<?php

declare(strict_types=1);

use Chemaclass\FeatureFlags\Manager\FeatureFlagManager;
use Chemaclass\FeatureFlags\Models\FeatureFlag;
use Workbench\App\Models\User;

beforeEach(function (): void {
    config()->set('feature-flags.admin.middleware', ['web']);

    $this->user = User::query()->create([
        'name'  => 'Admin',
        'email' => 'admin@example.com',
        'password' => bcrypt('secret'),
    ]);
});

it('index renders all flags grouped by key', function (): void {
    $manager = app(FeatureFlagManager::class);
    $manager->create(['key' => 'alpha', 'scope_id' => null,    'value' => true]);
    $manager->create(['key' => 'alpha', 'scope_id' => 'org-1', 'value' => false]);
    $manager->create(['key' => 'beta',  'scope_id' => null,    'value' => false]);

    $this->actingAs($this->user)
        ->get('/admin/feature-flags')
        ->assertOk()
        ->assertSee('alpha')
        ->assertSee('beta')
        ->assertSee('org-1')
        ->assertSee('3 entries');
});

it('store creates a new flag', function (): void {
    $response = $this->actingAs($this->user)
        ->postJson('/admin/feature-flags', [
            'key'   => 'gamma',
            'value' => true,
            'hint'  => 'Created via admin',
        ]);

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['success', 'id']);

    $row = FeatureFlag::query()->where('key', 'gamma')->first();
    expect($row)->not->toBeNull();
    expect($row->value)->toBeTrue();
    expect($row->hint)->toBe('Created via admin');
});

it('store updates an existing flag (key + scope_id is the unique pair)', function (): void {
    app(FeatureFlagManager::class)->create([
        'key' => 'delta', 'scope_id' => null, 'value' => false,
    ]);

    $this->actingAs($this->user)
        ->postJson('/admin/feature-flags', [
            'key'      => 'delta',
            'scope_id' => null,
            'value'    => true,
        ])->assertOk();

    expect(FeatureFlag::query()->where('key', 'delta')->count())->toBe(1)
        ->and(FeatureFlag::query()->where('key', 'delta')->first()->value)->toBeTrue();
});

it('toggle flips a flag value by id', function (): void {
    $row = FeatureFlag::query()->create([
        'key' => 'epsilon', 'scope_id' => null, 'value' => false,
    ]);

    $this->actingAs($this->user)
        ->postJson("/admin/feature-flags/{$row->id}/toggle")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('value', true);

    expect($row->refresh()->value)->toBeTrue();

    $this->actingAs($this->user)
        ->postJson("/admin/feature-flags/{$row->id}/toggle")
        ->assertJsonPath('value', false);
});

it('toggle-dev flips is_dev for every row sharing a key', function (): void {
    $a = FeatureFlag::query()->create([
        'key' => 'zeta', 'scope_id' => null,    'value' => true, 'is_dev' => false,
    ]);
    $b = FeatureFlag::query()->create([
        'key' => 'zeta', 'scope_id' => 'org-1', 'value' => true, 'is_dev' => false,
    ]);

    $this->actingAs($this->user)
        ->postJson('/admin/feature-flags/toggle-dev/zeta')
        ->assertOk()
        ->assertJsonPath('isDev', true);

    expect($a->refresh()->is_dev)->toBeTrue()
        ->and($b->refresh()->is_dev)->toBeTrue();

    $this->actingAs($this->user)
        ->postJson('/admin/feature-flags/toggle-dev/zeta')
        ->assertJsonPath('isDev', false);

    expect($a->refresh()->is_dev)->toBeFalse()
        ->and($b->refresh()->is_dev)->toBeFalse();
});

it('destroy removes a flag row', function (): void {
    $row = FeatureFlag::query()->create([
        'key' => 'eta', 'scope_id' => null, 'value' => true,
    ]);

    $this->actingAs($this->user)
        ->deleteJson("/admin/feature-flags/{$row->id}")
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(FeatureFlag::query()->find($row->id))->toBeNull();
});

it('store validates required fields', function (): void {
    $this->actingAs($this->user)
        ->postJson('/admin/feature-flags', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['key', 'value']);
});

