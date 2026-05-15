@php
    /** @var \Illuminate\Support\Collection $entriesByKey */
    /** @var int $total */
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Feature Flags</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-900">
<div class="max-w-6xl mx-auto p-6">
    <header class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-semibold">Feature Flags</h1>
        <span class="text-sm text-gray-500">{{ $total }} entries</span>
    </header>

    <div class="bg-white shadow rounded">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-100">
            <tr>
                <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider">Key</th>
                <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider">Scope</th>
                <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider">Value</th>
                <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider">Dev</th>
                <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wider">Window</th>
                <th class="px-4 py-2"></th>
            </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
            @foreach ($entriesByKey as $key => $rows)
                @foreach ($rows as $row)
                    <tr>
                        <td class="px-4 py-2 font-mono text-sm">{{ $row->key }}</td>
                        <td class="px-4 py-2 text-sm">{{ $row->scope_id ?? '—' }}</td>
                        <td class="px-4 py-2">
                            <form method="POST" action="{{ route('feature-flags.toggle', $row->id) }}">
                                @csrf
                                <button type="submit"
                                        class="px-2 py-1 rounded text-xs font-medium {{ $row->value ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-700' }}">
                                    {{ $row->value ? 'ON' : 'OFF' }}
                                </button>
                            </form>
                        </td>
                        <td class="px-4 py-2 text-sm">{{ $row->is_dev ? 'yes' : '—' }}</td>
                        <td class="px-4 py-2 text-xs text-gray-500">
                            {{ optional($row->enabled_from)->format('Y-m-d H:i') ?? '—' }}
                            →
                            {{ optional($row->enabled_until)->format('Y-m-d H:i') ?? '—' }}
                        </td>
                        <td class="px-4 py-2 text-right">
                            <form method="POST" action="{{ route('feature-flags.destroy', $row->id) }}"
                                  onsubmit="return confirm('Delete this feature flag?')">
                                @csrf
                                @method('DELETE')
                                <button class="text-red-600 text-sm">Delete</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            @endforeach
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
