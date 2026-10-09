<x-admin-layout title="Spaces">
    <x-slot name="actions">
        <a href="{{ route('admin.spaces.create') }}" class="rounded-lg bg-ink px-3 py-2 text-sm font-medium text-white hover:bg-signal hover:text-signal-ink">
            Add space
        </a>
    </x-slot>

    <div class="overflow-x-auto rounded-xl border border-stone-200 bg-white">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-stone-200 bg-stone-50 text-xs uppercase text-stone-500">
                <tr>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Setting</th>
                    <th class="px-4 py-3">Price / day</th>
                    <th class="px-4 py-3">Capacity</th>
                    <th class="px-4 py-3">Images</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($spaces as $space)
                    <tr>
                        <td class="px-4 py-3 font-medium text-stone-900">{{ $space->name }}</td>
                        <td class="px-4 py-3">{{ ucfirst(str_replace('_', '-', $space->space_type)) }} @if($space->size_sqm)<span class="text-stone-400">· {{ $space->size_sqm }} m²</span>@endif</td>
                        <td class="px-4 py-3">{{ $venue->currency }} {{ number_format((float) $space->base_price, 0, ',', '.') }}</td>
                        <td class="px-4 py-3">{{ number_format($space->maxGuests(), 0, ',', '.') }} guests</td>
                        <td class="px-4 py-3">{{ $space->images_count }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs {{ $space->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-stone-100 text-stone-500' }}">
                                {{ $space->is_active ? 'Active' : 'Hidden' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.spaces.inventory.index', $space) }}" class="text-stone-600 hover:underline">Dates & rates</a>
                            <a href="{{ route('admin.spaces.edit', $space) }}" class="ml-3 text-stone-600 hover:underline">Edit</a>
                            <form method="POST" action="{{ route('admin.spaces.destroy', $space) }}" class="inline" onsubmit="return confirm('Delete this space?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="ml-3 text-red-600 hover:underline">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-stone-400">No spaces yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-admin-layout>
