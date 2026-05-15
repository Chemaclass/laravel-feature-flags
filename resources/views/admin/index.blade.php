@php
    /** @var \Illuminate\Support\Collection $entriesByKey */
    /** @var int $total */
    $fmtDate = static fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('Y-m-d\TH:i') : '';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Feature Flags</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>[x-cloak]{display:none}</style>
</head>
<body class="bg-gray-50 text-gray-900">
<div class="max-w-6xl mx-auto p-6">
    <header class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-semibold">Feature Flags</h1>
            <p class="text-sm text-gray-500">{{ $entriesByKey->count() }} unique keys, {{ $total }} rows total</p>
        </div>
        <button type="button" onclick="document.getElementById('new-flag').classList.toggle('hidden')"
                class="px-3 py-2 rounded bg-indigo-600 text-white text-sm hover:bg-indigo-700">
            + New flag
        </button>
    </header>

    <div id="new-flag" class="hidden mb-6 p-4 bg-white shadow rounded">
        <h2 class="text-lg font-medium mb-3">Create flag</h2>
        <form data-action="{{ route('feature-flags.store') }}" data-method="POST" class="ff-form grid grid-cols-2 gap-3">
            <label class="text-sm">Key
                <input type="text" name="key" required class="mt-1 w-full px-2 py-1 border rounded text-sm" placeholder="my-feature">
            </label>
            <label class="text-sm">Scope id <span class="text-xs text-gray-400">(empty = global)</span>
                <input type="text" name="scope_id" class="mt-1 w-full px-2 py-1 border rounded text-sm" placeholder="">
            </label>
            <label class="text-sm">Hint
                <input type="text" name="hint" class="mt-1 w-full px-2 py-1 border rounded text-sm" placeholder="optional description">
            </label>
            <div class="text-sm flex items-end gap-4">
                <label class="flex items-center gap-2"><input type="checkbox" name="value" value="1" checked> Enabled</label>
                <label class="flex items-center gap-2"><input type="checkbox" name="is_dev" value="1"> Dev only</label>
            </div>
            <label class="text-sm">Enabled from
                <input type="datetime-local" name="enabled_from" class="mt-1 w-full px-2 py-1 border rounded text-sm">
            </label>
            <label class="text-sm">Enabled until
                <input type="datetime-local" name="enabled_until" class="mt-1 w-full px-2 py-1 border rounded text-sm">
            </label>
            <div class="col-span-2 flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('new-flag').classList.add('hidden')"
                        class="px-3 py-1.5 rounded border text-sm">Cancel</button>
                <button type="submit" class="px-3 py-1.5 rounded bg-indigo-600 text-white text-sm hover:bg-indigo-700">Create</button>
            </div>
        </form>
    </div>

    @foreach ($entriesByKey as $key => $rows)
        @php
            $global = $rows->firstWhere('scope_id', null);
            $scoped = $rows->filter(fn ($r) => $r->scope_id !== null)->values();
            $allDev = $rows->every(fn ($r) => (bool) $r->is_dev);
        @endphp

        <section class="mb-6 bg-white shadow rounded">
            <header class="flex items-center justify-between px-4 py-3 border-b bg-gray-50 rounded-t">
                <div class="flex items-center gap-3">
                    <h3 class="font-mono text-base font-semibold">{{ $key }}</h3>
                    @if ($global)
                        <span class="text-xs text-gray-500">global +{{ $scoped->count() }} scope override{{ $scoped->count() === 1 ? '' : 's' }}</span>
                    @else
                        <span class="text-xs text-amber-600">no global default (scope-only)</span>
                    @endif
                </div>
                <div class="flex items-center gap-2">
                    <form data-action="{{ route('feature-flags.toggle-dev', $key) }}" data-method="POST" class="ff-form">
                        <button type="submit" title="Toggle dev marker for every row of this key"
                                class="px-2 py-1 rounded text-xs font-medium {{ $allDev ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-600' }}">
                            {{ $allDev ? 'DEV' : 'dev?' }}
                        </button>
                    </form>
                </div>
            </header>

            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-2 text-left">Scope</th>
                        <th class="px-4 py-2 text-left">On/Off</th>
                        <th class="px-4 py-2 text-left">Dev</th>
                        <th class="px-4 py-2 text-left">Hint</th>
                        <th class="px-4 py-2 text-left">From</th>
                        <th class="px-4 py-2 text-left">Until</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($rows as $row)
                        <tr class="{{ $row->scope_id === null ? 'bg-indigo-50/30' : '' }}">
                            <td class="px-4 py-2 font-mono text-xs">
                                @if ($row->scope_id === null)
                                    <span class="px-2 py-0.5 rounded bg-indigo-100 text-indigo-800 text-xs">global</span>
                                @else
                                    {{ $row->scope_id }}
                                @endif
                            </td>

                            <td class="px-4 py-2">
                                <form data-action="{{ route('feature-flags.toggle', $row->id) }}" data-method="POST" class="ff-form inline">
                                    <button type="submit"
                                            class="px-2 py-1 rounded text-xs font-medium {{ $row->value ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-700' }}">
                                        {{ $row->value ? 'ON' : 'OFF' }}
                                    </button>
                                </form>
                            </td>

                            <td class="px-4 py-2">
                                <form data-action="{{ route('feature-flags.toggle-dev-row', $row->id) }}" data-method="POST" class="ff-form inline">
                                    <button type="submit"
                                            class="px-2 py-1 rounded text-xs font-medium {{ $row->is_dev ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-500' }}">
                                        {{ $row->is_dev ? 'DEV' : '—' }}
                                    </button>
                                </form>
                            </td>

                            <td class="px-4 py-2">
                                <form data-action="{{ route('feature-flags.update', $row->id) }}" data-method="PATCH" class="ff-form flex gap-1 items-center">
                                    <input type="text" name="hint" value="{{ $row->hint }}"
                                           class="px-2 py-1 border rounded text-xs w-44">
                                    <button type="submit" class="text-xs text-indigo-600 hover:underline">save</button>
                                </form>
                            </td>

                            <td class="px-4 py-2">
                                <form data-action="{{ route('feature-flags.update', $row->id) }}" data-method="PATCH" class="ff-form flex gap-1 items-center">
                                    <input type="datetime-local" name="enabled_from" value="{{ $fmtDate($row->enabled_from) }}"
                                           class="px-1 py-1 border rounded text-xs">
                                    <button type="submit" class="text-xs text-indigo-600 hover:underline">save</button>
                                </form>
                            </td>

                            <td class="px-4 py-2">
                                <form data-action="{{ route('feature-flags.update', $row->id) }}" data-method="PATCH" class="ff-form flex gap-1 items-center">
                                    <input type="datetime-local" name="enabled_until" value="{{ $fmtDate($row->enabled_until) }}"
                                           class="px-1 py-1 border rounded text-xs">
                                    <button type="submit" class="text-xs text-indigo-600 hover:underline">save</button>
                                </form>
                            </td>

                            <td class="px-4 py-2 text-right">
                                <form data-action="{{ route('feature-flags.destroy', $row->id) }}" data-method="DELETE" class="ff-form inline"
                                      data-confirm="Delete this row ({{ $row->scope_id ?? 'global' }})?">
                                    <button type="submit" class="text-xs text-red-600 hover:underline">delete</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach

                    <tr class="bg-gray-50/60">
                        <td colspan="7" class="px-4 py-2">
                            <form data-action="{{ route('feature-flags.store') }}" data-method="POST" class="ff-form flex flex-wrap gap-2 items-center text-xs">
                                <input type="hidden" name="key" value="{{ $key }}">
                                <input type="hidden" name="value" value="1">
                                <span class="text-gray-500">Add scope override:</span>
                                <input type="text" name="scope_id" placeholder="scope id" required
                                       class="px-2 py-1 border rounded">
                                <input type="text" name="hint" placeholder="hint (optional)"
                                       class="px-2 py-1 border rounded">
                                <label class="flex items-center gap-1"><input type="checkbox" name="is_dev" value="1"> dev</label>
                                <button type="submit" class="px-2 py-1 rounded bg-indigo-600 text-white">Add</button>
                            </form>
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>
    @endforeach

    @if ($entriesByKey->isEmpty())
        <div class="bg-white shadow rounded p-8 text-center text-gray-500">
            No flags yet. Click <strong>+ New flag</strong> above to create one.
        </div>
    @endif
</div>

<div id="ff-toast" class="hidden fixed bottom-4 right-4 px-3 py-2 rounded shadow text-sm"></div>

<script>
(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const toast = document.getElementById('ff-toast');
    const show = (msg, ok = true) => {
        toast.textContent = msg;
        toast.className = 'fixed bottom-4 right-4 px-3 py-2 rounded shadow text-sm ' +
            (ok ? 'bg-green-600 text-white' : 'bg-red-600 text-white');
        setTimeout(() => toast.classList.add('hidden'), 1500);
    };

    document.addEventListener('submit', async (e) => {
        const form = e.target.closest('.ff-form');
        if (!form) return;
        e.preventDefault();

        const confirmMsg = form.dataset.confirm;
        if (confirmMsg && !window.confirm(confirmMsg)) return;

        const action = form.dataset.action;
        const method = (form.dataset.method || 'POST').toUpperCase();

        const fd = new FormData(form);
        // checkboxes: serialize as 0/1 explicitly so server gets bool semantics
        form.querySelectorAll('input[type=checkbox]').forEach((cb) => {
            if (!fd.has(cb.name)) fd.set(cb.name, '0');
            else fd.set(cb.name, '1');
        });

        const body = {};
        fd.forEach((v, k) => { body[k] = v; });

        try {
            const res = await fetch(action, {
                method,
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: method === 'GET' ? undefined : JSON.stringify(body),
            });
            if (!res.ok) {
                const data = await res.json().catch(() => ({}));
                show(data.message || ('Error ' + res.status), false);
                return;
            }
            show('Saved');
            // Reload to reflect server-side state (grouping, derived states, etc.)
            setTimeout(() => window.location.reload(), 300);
        } catch (err) {
            show(String(err), false);
        }
    });
})();
</script>
</body>
</html>
