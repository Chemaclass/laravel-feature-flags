@php
    /** @var \Illuminate\Support\Collection $entriesByKey */
    /** @var int $total */
    $fmtDate = static fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('Y-m-d\TH:i') : '';
    $scopeColor = static function (?string $scope): string {
        if ($scope === null) return 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300 ring-indigo-200 dark:ring-indigo-500/30';
        $palette = [
            'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300 ring-sky-200 dark:ring-sky-500/30',
            'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300 ring-emerald-200 dark:ring-emerald-500/30',
            'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300 ring-rose-200 dark:ring-rose-500/30',
            'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300 ring-amber-200 dark:ring-amber-500/30',
            'bg-fuchsia-100 text-fuchsia-700 dark:bg-fuchsia-500/15 dark:text-fuchsia-300 ring-fuchsia-200 dark:ring-fuchsia-500/30',
            'bg-cyan-100 text-cyan-700 dark:bg-cyan-500/15 dark:text-cyan-300 ring-cyan-200 dark:ring-cyan-500/30',
            'bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300 ring-violet-200 dark:ring-violet-500/30',
            'bg-teal-100 text-teal-700 dark:bg-teal-500/15 dark:text-teal-300 ring-teal-200 dark:ring-teal-500/30',
        ];
        return $palette[abs(crc32($scope)) % count($palette)];
    };
@endphp
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Feature Flags</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'media',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                        mono: ['"JetBrains Mono"', 'ui-monospace', 'monospace'],
                    },
                },
            },
        };
    </script>
    <style>
        body { font-feature-settings: 'cv11', 'ss01'; }
        .switch { position: relative; display: inline-flex; height: 22px; width: 40px; flex-shrink: 0; cursor: pointer; border-radius: 9999px; transition: background-color .2s; }
        .switch input { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
        .switch .knob { position: absolute; top: 2px; left: 2px; height: 18px; width: 18px; border-radius: 9999px; background: white; box-shadow: 0 1px 2px rgba(0,0,0,.15), 0 1px 3px rgba(0,0,0,.1); transition: transform .2s; }
        .switch.on { background: #10b981; }
        .switch.on .knob { transform: translateX(18px); }
        .switch.off { background: #d4d4d8; }
        @media (prefers-color-scheme: dark) { .switch.off { background: #3f3f46; } }
        .toast-enter { animation: slideIn .25s ease-out forwards; }
        .toast-leave { animation: slideOut .25s ease-in forwards; }
        @keyframes slideIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes slideOut { from { opacity: 1; transform: translateY(0); } to { opacity: 0; transform: translateY(8px); } }
        details > summary { list-style: none; }
        details > summary::-webkit-details-marker { display: none; }
        details[open] .chev { transform: rotate(90deg); }
        .chev { transition: transform .15s; }
        input[type="datetime-local"], input[type="text"] { transition: border-color .15s, box-shadow .15s; }
        input:focus { outline: none; box-shadow: 0 0 0 3px rgba(99, 102, 241, .2); }
    </style>
</head>
<body class="min-h-full bg-zinc-50 text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100 font-sans antialiased">

<header class="sticky top-0 z-30 backdrop-blur-xl bg-white/70 dark:bg-zinc-950/70 border-b border-zinc-200/60 dark:border-zinc-800/60">
    <div class="max-w-5xl mx-auto px-6 py-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-indigo-500 to-fuchsia-500 grid place-items-center text-white shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>
            </div>
            <div>
                <h1 class="text-base font-semibold tracking-tight">Feature Flags</h1>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $entriesByKey->count() }} unique keys · {{ $total }} rows total</p>
            </div>
        </div>
        <button type="button" data-toggle="#new-flag"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-zinc-900 dark:bg-white text-white dark:text-zinc-900 text-sm font-medium hover:opacity-90 transition shadow-sm">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            New flag
        </button>
    </div>
</header>

<main class="max-w-5xl mx-auto px-6 py-8">

    <section id="new-flag" class="hidden mb-8 p-5 bg-white dark:bg-zinc-900 rounded-xl shadow-sm ring-1 ring-zinc-200 dark:ring-zinc-800">
        <h2 class="text-sm font-semibold mb-4 text-zinc-700 dark:text-zinc-300">Create flag</h2>
        <form data-action="{{ route('feature-flags.store') }}" data-method="POST" class="ff-form grid grid-cols-2 gap-x-4 gap-y-3">
            <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Key
                <input type="text" name="key" required placeholder="my-feature"
                       class="mt-1 w-full px-3 py-2 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg text-sm font-mono">
            </label>
            <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Scope id <span class="text-zinc-400 font-normal">(empty = global)</span>
                <input type="text" name="scope_id" placeholder="—"
                       class="mt-1 w-full px-3 py-2 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg text-sm font-mono">
            </label>
            <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400 col-span-2">Hint
                <input type="text" name="hint" placeholder="optional description"
                       class="mt-1 w-full px-3 py-2 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg text-sm">
            </label>
            <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Enabled from
                <input type="datetime-local" name="enabled_from"
                       class="mt-1 w-full px-3 py-2 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg text-sm">
            </label>
            <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">Enabled until
                <input type="datetime-local" name="enabled_until"
                       class="mt-1 w-full px-3 py-2 bg-zinc-50 dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-lg text-sm">
            </label>
            <div class="col-span-2 flex items-center justify-between pt-2">
                <div class="flex items-center gap-4 text-xs text-zinc-600 dark:text-zinc-400">
                    <label class="inline-flex items-center gap-2"><input type="checkbox" name="value" value="1" checked class="rounded"> Enabled</label>
                    <label class="inline-flex items-center gap-2"><input type="checkbox" name="is_dev" value="1" class="rounded"> Dev only</label>
                </div>
                <div class="flex gap-2">
                    <button type="button" data-toggle="#new-flag"
                            class="px-3 py-1.5 rounded-lg text-sm border border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-800 transition">Cancel</button>
                    <button type="submit" class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium transition shadow-sm">Create</button>
                </div>
            </div>
        </form>
    </section>

    @forelse ($entriesByKey as $key => $rows)
        @php
            $global = $rows->firstWhere('scope_id', null);
            $scoped = $rows->filter(fn ($r) => $r->scope_id !== null)->values();
            $allDev = $rows->every(fn ($r) => (bool) $r->is_dev);
        @endphp

        <details open class="group mb-4 bg-white dark:bg-zinc-900 rounded-xl shadow-sm ring-1 ring-zinc-200 dark:ring-zinc-800 overflow-hidden">
            <summary class="flex items-center justify-between px-5 py-4 cursor-pointer hover:bg-zinc-50/60 dark:hover:bg-zinc-800/40 transition">
                <div class="flex items-center gap-3 min-w-0">
                    <svg class="chev text-zinc-400" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                    <h3 class="font-mono text-sm font-semibold truncate">{{ $key }}</h3>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400">
                        @if ($global)
                            global · {{ $scoped->count() }} {{ \Illuminate\Support\Str::plural('override', $scoped->count()) }}
                        @else
                            <span class="text-amber-600 dark:text-amber-400">⚠ no global default</span>
                        @endif
                    </span>
                </div>
                <form data-action="{{ route('feature-flags.toggle-dev', $key) }}" data-method="POST" class="ff-form" onclick="event.stopPropagation()">
                    <button type="submit" title="Toggle dev marker for every row of this key"
                            class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-[11px] font-semibold tracking-wide ring-1
                                   {{ $allDev
                                       ? 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30'
                                       : 'bg-zinc-100 text-zinc-500 ring-zinc-200 dark:bg-zinc-800 dark:text-zinc-400 dark:ring-zinc-700' }}">
                        DEV
                    </button>
                </form>
            </summary>

            <div class="border-t border-zinc-100 dark:border-zinc-800 divide-y divide-zinc-100 dark:divide-zinc-800">
                @foreach ($rows as $row)
                    <article class="px-5 py-3 flex flex-wrap items-center gap-x-4 gap-y-2 {{ $row->scope_id === null ? 'bg-indigo-50/30 dark:bg-indigo-500/5' : '' }}">
                        <div class="flex items-center gap-2 min-w-[8rem]">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-medium ring-1 {{ $scopeColor($row->scope_id) }}">
                                @if ($row->scope_id === null)
                                    <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                                    global
                                @else
                                    <span class="font-mono">{{ $row->scope_id }}</span>
                                @endif
                            </span>
                        </div>

                        <form data-action="{{ route('feature-flags.toggle', $row->id) }}" data-method="POST" class="ff-form" data-auto-submit>
                            <label class="switch {{ $row->value ? 'on' : 'off' }}" title="{{ $row->value ? 'Enabled' : 'Disabled' }}">
                                <input type="checkbox" {{ $row->value ? 'checked' : '' }} aria-label="Toggle value">
                                <span class="knob"></span>
                            </label>
                        </form>

                        <form data-action="{{ route('feature-flags.toggle-dev-row', $row->id) }}" data-method="POST" class="ff-form">
                            <button type="submit" title="Toggle dev marker"
                                    class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-[11px] font-semibold tracking-wide ring-1 transition
                                           {{ $row->is_dev
                                              ? 'bg-amber-100 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/30'
                                              : 'bg-zinc-50 text-zinc-400 ring-zinc-200 dark:bg-zinc-800/60 dark:text-zinc-500 dark:ring-zinc-700' }}">
                                @if ($row->is_dev)
                                    <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
                                    DEV
                                @else
                                    DEV?
                                @endif
                            </button>
                        </form>

                        <form data-action="{{ route('feature-flags.update', $row->id) }}" data-method="PATCH" class="ff-form flex-1 min-w-[14rem]" data-debounce>
                            <input type="text" name="hint" value="{{ $row->hint }}" placeholder="Add a hint…"
                                   class="w-full px-2.5 py-1.5 bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200 dark:border-zinc-700 rounded-md text-xs">
                        </form>

                        <form data-action="{{ route('feature-flags.update', $row->id) }}" data-method="PATCH" class="ff-form" data-debounce>
                            <label class="inline-flex items-center gap-1 text-[11px] text-zinc-500 dark:text-zinc-400">
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                <input type="datetime-local" name="enabled_from" value="{{ $fmtDate($row->enabled_from) }}"
                                       class="px-1.5 py-1 bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200 dark:border-zinc-700 rounded-md text-[11px]">
                            </label>
                        </form>

                        <form data-action="{{ route('feature-flags.update', $row->id) }}" data-method="PATCH" class="ff-form" data-debounce>
                            <label class="inline-flex items-center gap-1 text-[11px] text-zinc-500 dark:text-zinc-400">
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                                <input type="datetime-local" name="enabled_until" value="{{ $fmtDate($row->enabled_until) }}"
                                       class="px-1.5 py-1 bg-zinc-50 dark:bg-zinc-800/60 border border-zinc-200 dark:border-zinc-700 rounded-md text-[11px]">
                            </label>
                        </form>

                        <form data-action="{{ route('feature-flags.destroy', $row->id) }}" data-method="DELETE" class="ff-form ml-auto"
                              data-confirm="Delete the {{ $row->scope_id ?? 'global' }} row for {{ $key }}?">
                            <button type="submit" title="Delete row"
                                    class="p-1.5 rounded-md text-zinc-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-2 14a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L5 6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                            </button>
                        </form>
                    </article>
                @endforeach

                <article class="px-5 py-3 bg-zinc-50/40 dark:bg-zinc-800/20">
                    <form data-action="{{ route('feature-flags.store') }}" data-method="POST" class="ff-form flex flex-wrap items-center gap-2">
                        <input type="hidden" name="key" value="{{ $key }}">
                        <input type="hidden" name="value" value="1">
                        <span class="text-xs text-zinc-500 dark:text-zinc-400 inline-flex items-center gap-1">
                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                            Add scope override
                        </span>
                        <input type="text" name="scope_id" required placeholder="scope id"
                               class="px-2.5 py-1.5 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-md text-xs font-mono">
                        <input type="text" name="hint" placeholder="hint (optional)"
                               class="px-2.5 py-1.5 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-md text-xs flex-1 min-w-[10rem]">
                        <label class="inline-flex items-center gap-1 text-[11px] text-zinc-500 dark:text-zinc-400">
                            <input type="checkbox" name="is_dev" value="1" class="rounded"> dev
                        </label>
                        <button type="submit" class="px-3 py-1.5 rounded-md bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-medium transition">
                            Add
                        </button>
                    </form>
                </article>
            </div>
        </details>
    @empty
        <div class="text-center py-16 px-6 bg-white dark:bg-zinc-900 rounded-xl ring-1 ring-zinc-200 dark:ring-zinc-800">
            <div class="mx-auto w-12 h-12 rounded-full bg-zinc-100 dark:bg-zinc-800 grid place-items-center mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="text-zinc-400"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>
            </div>
            <h3 class="font-semibold mb-1">No flags yet</h3>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mb-4">Create your first flag to get started.</p>
            <button type="button" data-toggle="#new-flag"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium transition shadow-sm">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                New flag
            </button>
        </div>
    @endforelse

</main>

<div id="ff-toast" class="hidden fixed bottom-4 right-4 px-3.5 py-2 rounded-lg shadow-lg text-sm font-medium ring-1"></div>

<script>
(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const toastEl = document.getElementById('ff-toast');

    const toast = (msg, ok = true) => {
        toastEl.textContent = msg;
        toastEl.className = 'toast-enter fixed bottom-4 right-4 px-3.5 py-2 rounded-lg shadow-lg text-sm font-medium ring-1 ' +
            (ok
                ? 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/30'
                : 'bg-rose-50 text-rose-700 ring-rose-200 dark:bg-rose-500/15 dark:text-rose-300 dark:ring-rose-500/30');
        clearTimeout(toastEl._t);
        toastEl._t = setTimeout(() => {
            toastEl.classList.replace('toast-enter', 'toast-leave');
            setTimeout(() => toastEl.classList.add('hidden'), 250);
        }, 1300);
    };

    document.addEventListener('click', (e) => {
        const t = e.target.closest('[data-toggle]');
        if (!t) return;
        document.querySelector(t.dataset.toggle)?.classList.toggle('hidden');
    });

    const submit = async (form) => {
        const confirmMsg = form.dataset.confirm;
        if (confirmMsg && !window.confirm(confirmMsg)) return;

        const action = form.dataset.action;
        const method = (form.dataset.method || 'POST').toUpperCase();

        const body = {};
        form.querySelectorAll('input, select, textarea').forEach((el) => {
            if (!el.name) return;
            if (el.type === 'checkbox') body[el.name] = el.checked ? 1 : 0;
            else body[el.name] = el.value;
        });

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
                toast(data.message || ('Error ' + res.status), false);
                return false;
            }
            toast('Saved');
            return true;
        } catch (err) {
            toast(String(err), false);
            return false;
        }
    };

    document.addEventListener('submit', async (e) => {
        const form = e.target.closest('.ff-form');
        if (!form) return;
        e.preventDefault();
        const ok = await submit(form);
        if (ok) setTimeout(() => window.location.reload(), 300);
    });

    // Toggle switches: submit on change without page reload
    document.addEventListener('change', async (e) => {
        const form = e.target.closest('.ff-form[data-auto-submit]');
        if (!form) return;
        const sw = form.querySelector('.switch');
        if (sw) sw.classList.toggle('on', e.target.checked), sw.classList.toggle('off', !e.target.checked);
        await submit(form);
    });

    // Debounced text/date saves
    const debouncers = new WeakMap();
    document.addEventListener('input', (e) => {
        const form = e.target.closest('.ff-form[data-debounce]');
        if (!form) return;
        clearTimeout(debouncers.get(form));
        debouncers.set(form, setTimeout(() => submit(form), 600));
    });
    document.addEventListener('change', (e) => {
        const form = e.target.closest('.ff-form[data-debounce]');
        if (!form || e.target.type === 'text') return;
        clearTimeout(debouncers.get(form));
        submit(form);
    });
})();
</script>
</body>
</html>
