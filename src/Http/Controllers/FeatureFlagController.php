<?php

declare(strict_types=1);

namespace Chemaclass\FeatureFlags\Http\Controllers;

use Chemaclass\FeatureFlags\Manager\FeatureFlagManager;
use Chemaclass\FeatureFlags\Models\FeatureFlag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

final class FeatureFlagController extends Controller
{
    public function __construct(
        private readonly FeatureFlagManager $manager,
    ) {}

    public function index(): View
    {
        /** @var class-string<FeatureFlag> $modelCls */
        $modelCls = config('feature-flags.model', FeatureFlag::class);
        $entries = $modelCls::query()
            ->orderBy('key')
            ->orderByRaw('scope_id IS NULL DESC')
            ->orderBy('scope_id')
            ->get();

        return view('feature-flags::admin.index', [
            'entriesByKey' => $entries->groupBy('key'),
            'total' => $entries->count(),
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string'],
            'scope_id' => ['nullable', 'string'],
            'environment' => ['nullable', 'string'],
            'value' => ['required', 'boolean'],
            'rollout_percentage' => ['nullable', 'integer', 'between:0,100'],
            'rules' => ['nullable', 'array'],
            'prerequisites' => ['nullable', 'array'],
            'variants' => ['nullable', 'array'],
            'variant_payloads' => ['nullable', 'array'],
            'hint' => ['nullable', 'string'],
            'is_dev' => ['nullable', 'boolean'],
            'enabled_from' => ['nullable', 'date'],
            'enabled_until' => ['nullable', 'date'],
        ]);

        $feature = $this->manager->updateOrCreate(
            ['key' => $data['key'], 'scope_id' => $data['scope_id'] ?? null, 'environment' => $data['environment'] ?? null],
            [
                'value' => $data['value'],
                'rollout_percentage' => $data['rollout_percentage'] ?? null,
                'rules' => $data['rules'] ?? null,
                'prerequisites' => $data['prerequisites'] ?? null,
                'variants' => $data['variants'] ?? null,
                'variant_payloads' => $data['variant_payloads'] ?? null,
                'hint' => $data['hint'] ?? null,
                'is_dev' => $data['is_dev'] ?? false,
                'enabled_from' => $data['enabled_from'] ?? null,
                'enabled_until' => $data['enabled_until'] ?? null,
            ],
        );

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'id' => $feature->id]);
        }

        return redirect()->back();
    }

    public function update(Request $request, string $id): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'value' => ['sometimes', 'boolean'],
            'rollout_percentage' => ['sometimes', 'nullable', 'integer', 'between:0,100'],
            'rules' => ['sometimes', 'nullable', 'array'],
            'prerequisites' => ['sometimes', 'nullable', 'array'],
            'variants' => ['sometimes', 'nullable', 'array'],
            'variant_payloads' => ['sometimes', 'nullable', 'array'],
            'environment' => ['sometimes', 'nullable', 'string'],
            'hint' => ['sometimes', 'nullable', 'string'],
            'is_dev' => ['sometimes', 'boolean'],
            'scope_id' => ['sometimes', 'nullable', 'string'],
            'enabled_from' => ['sometimes', 'nullable', 'date'],
            'enabled_until' => ['sometimes', 'nullable', 'date'],
        ]);

        $feature = $this->manager->update($id, $data);

        if ($feature === null) {
            return $request->wantsJson()
                ? response()->json(['success' => false, 'message' => 'Not found'], 404)
                : redirect()->back();
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'id' => $feature->id]);
        }

        return redirect()->back();
    }

    public function toggle(string $id): JsonResponse
    {
        $value = $this->manager->toggleValue($id);

        return response()->json(['success' => true, 'value' => $value]);
    }

    public function toggleDev(string $id): JsonResponse
    {
        $feature = $this->manager->findById($id);
        if ($feature === null) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        $updated = $this->manager->update($id, ['is_dev' => ! $feature->isDev]);

        return response()->json(['success' => true, 'isDev' => $updated !== null ? $updated->isDev : false]);
    }

    public function toggleDevByKey(string $key): JsonResponse
    {
        $value = $this->manager->toggleDevByKey($key);

        return response()->json(['success' => true, 'isDev' => $value]);
    }

    public function destroy(Request $request, string $id): JsonResponse|RedirectResponse
    {
        $this->manager->delete($id);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->back();
    }
}
