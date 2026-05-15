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
        $entries = $modelCls::query()->orderBy('key')->orderBy('scope_id')->get();

        return view('feature-flags::admin.index', [
            'entriesByKey' => $entries->groupBy('key'),
            'total'        => $entries->count(),
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'key'           => ['required', 'string'],
            'scope_id'      => ['nullable', 'string'],
            'value'         => ['required', 'boolean'],
            'hint'          => ['nullable', 'string'],
            'is_dev'        => ['nullable', 'boolean'],
            'enabled_from'  => ['nullable', 'date'],
            'enabled_until' => ['nullable', 'date'],
        ]);

        $feature = $this->manager->updateOrCreate(
            ['key' => $data['key'], 'scope_id' => $data['scope_id'] ?? null],
            [
                'value'         => $data['value'],
                'hint'          => $data['hint'] ?? null,
                'is_dev'        => $data['is_dev'] ?? false,
                'enabled_from'  => $data['enabled_from'] ?? null,
                'enabled_until' => $data['enabled_until'] ?? null,
            ],
        );

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

    public function toggleDev(string $key): JsonResponse
    {
        $value = $this->manager->toggleDevByKey($key);

        return response()->json(['success' => true, 'isDev' => $value]);
    }

    public function destroy(string $id): JsonResponse
    {
        /** @var class-string<FeatureFlag> $modelCls */
        $modelCls = config('feature-flags.model', FeatureFlag::class);
        $modelCls::query()->whereKey($id)->delete();

        return response()->json(['success' => true]);
    }
}
