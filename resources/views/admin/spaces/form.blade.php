@php
    $isEdit = $space->exists;
    $input = 'mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm';
@endphp

<x-admin-layout :title="$isEdit ? 'Edit space' : 'Add space'">
    <form method="POST" action="{{ $isEdit ? route('admin.spaces.update', $space) : route('admin.spaces.store') }}" class="space-y-8">
        @csrf
        @if ($isEdit) @method('PUT') @endif

        <section class="rounded-xl border border-stone-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-stone-900">Basics</h2>
            <div class="mt-4 grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-stone-700">Name (Indonesian, default)</label>
                    <input type="text" name="name" value="{{ old('name', $space->name) }}" required
                        class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
                <div class="col-span-2">
                    <label class="block text-sm font-medium text-stone-700">Description (Indonesian, default)</label>
                    <textarea name="description" rows="2" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">{{ old('description', $space->description) }}</textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700">Name (English)</label>
                    <input type="text" name="translations[en][name]" value="{{ old('translations.en.name', $space->translations['en']['name'] ?? '') }}"
                        class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700">Name (Japanese)</label>
                    <input type="text" name="translations[ja][name]" value="{{ old('translations.ja.name', $space->translations['ja']['name'] ?? '') }}"
                        class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700">Description (English)</label>
                    <textarea name="translations[en][description]" rows="2" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">{{ old('translations.en.description', $space->translations['en']['description'] ?? '') }}</textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700">Description (Japanese)</label>
                    <textarea name="translations[ja][description]" rows="2" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">{{ old('translations.ja.description', $space->translations['ja']['description'] ?? '') }}</textarea>
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-stone-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-stone-900">Room & capacity</h2>
            <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div>
                    <label class="block text-sm font-medium text-stone-700">Setting</label>
                    <select name="space_type" required class="{{ $input }}">
                        @foreach (['indoor' => 'Indoor hall', 'semi_outdoor' => 'Semi-outdoor pavilion', 'outdoor' => 'Open-air space'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('space_type', $space->space_type) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700">Size (m²)</label>
                    <input type="number" name="size_sqm" value="{{ old('size_sqm', $space->size_sqm) }}" class="{{ $input }}">
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700">Ceiling height (m)</label>
                    <input type="number" step="0.1" name="ceiling_height_m" value="{{ old('ceiling_height_m', $space->ceiling_height_m) }}" class="{{ $input }}">
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700">Level</label>
                    <input type="text" name="level_label" value="{{ old('level_label', $space->level_label) }}" placeholder="Level 2" maxlength="30" class="{{ $input }}">
                </div>
            </div>
            <p class="mt-5 text-sm font-medium text-stone-700">Capacity by setup <span class="font-normal text-stone-400">— leave blank for a setup this space cannot offer</span></p>
            <div class="mt-2 grid grid-cols-2 gap-4 sm:grid-cols-5">
                @foreach (['banquet' => 'Banquet', 'theatre' => 'Theatre', 'classroom' => 'Classroom', 'boardroom' => 'Boardroom', 'cocktail' => 'Cocktail'] as $layout => $label)
                    <div>
                        <label class="block text-xs text-stone-500">{{ $label }}</label>
                        <input type="number" name="layout_{{ $layout }}" value="{{ old('layout_'.$layout, $space->layouts[$layout] ?? '') }}" min="1" class="{{ $input }}">
                    </div>
                @endforeach
            </div>
        </section>

        <section class="rounded-xl border border-stone-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-stone-900">Pricing & inclusions</h2>
            <div class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div>
                    <label class="block text-sm font-medium text-stone-700">Base price / event day</label>
                    <input type="number" step="1000" name="base_price" value="{{ old('base_price', $space->base_price) }}" required class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700">Catering price / guest / day</label>
                    <input type="number" step="1000" name="catering_price" value="{{ old('catering_price', $space->catering_price) }}" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700">Sort order</label>
                    <input type="number" name="sort_order" value="{{ old('sort_order', $space->sort_order) }}" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
            </div>
            <div class="mt-4 flex flex-wrap gap-6">
                <label class="flex items-center gap-2 text-sm text-stone-700">
                    <input type="checkbox" name="av_included" value="1" @checked(old('av_included', $space->av_included)) class="rounded border-stone-300">
                    Sound & lighting included
                </label>
                <label class="flex items-center gap-2 text-sm text-stone-700">
                    <input type="checkbox" name="catering_available" value="1" @checked(old('catering_available', $space->catering_available)) class="rounded border-stone-300">
                    In-house catering available
                </label>
                <label class="flex items-center gap-2 text-sm text-stone-700">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $space->is_active)) class="rounded border-stone-300">
                    Published (visible on the website)
                </label>
            </div>
            <div class="mt-4">
                <label class="block text-sm font-medium text-stone-700">Inclusions (comma-separated)</label>
                <input type="text" name="amenities" value="{{ old('amenities', implode(', ', $space->amenities ?? [])) }}" placeholder="stage, sound_system, lighting_rig, led_wall, bridal_suite, valet"
                    class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2 text-sm">
            </div>
        </section>

        <section class="rounded-xl border border-stone-200 bg-white p-5">
            <h2 class="text-sm font-semibold text-stone-900">Photos</h2>

            @if ($isEdit && $space->images->isNotEmpty())
                <div class="mt-4 space-y-2">
                    @foreach ($space->images as $image)
                        <div class="flex items-center gap-3 rounded-lg border border-stone-200 p-2">
                            <img src="{{ $image->image_source }}" alt="{{ $image->alt_text }}" class="h-14 w-20 rounded object-cover">
                            <div class="flex-1 text-xs text-stone-500">
                                <p class="truncate">{{ $image->image_url }}</p>
                                <p>Tags: {{ implode(', ', $image->tags ?? []) }}</p>
                            </div>
                            <label class="flex items-center gap-1 text-xs text-red-600">
                                <input type="checkbox" name="delete_images[]" value="{{ $image->id }}" class="rounded border-stone-300">
                                Delete
                            </label>
                        </div>
                    @endforeach
                </div>
            @endif

            <p class="mt-4 text-xs text-stone-500">Add photos by URL (e.g. an Unsplash or CDN link). Tag them so the AI Planner can pick the right one — e.g. "stage, night" or "banquet, entrance".</p>
            @for ($i = 0; $i < 3; $i++)
                <div class="mt-2 grid grid-cols-6 gap-2">
                    <input type="url" name="new_images[{{ $i }}][url]" placeholder="https://…" class="col-span-3 rounded-lg border border-stone-300 px-3 py-2 text-sm">
                    <input type="text" name="new_images[{{ $i }}][tags]" placeholder="banquet, stage" class="col-span-2 rounded-lg border border-stone-300 px-3 py-2 text-sm">
                    <input type="text" name="new_images[{{ $i }}][alt]" placeholder="Alt text" class="rounded-lg border border-stone-300 px-3 py-2 text-sm">
                </div>
            @endfor
        </section>

        <div class="flex justify-end gap-3">
            <a href="{{ route('admin.spaces.index') }}" class="rounded-lg border border-stone-300 px-4 py-2 text-sm text-stone-600 hover:bg-stone-50">Cancel</a>
            <button type="submit" class="rounded-lg bg-ink px-4 py-2 text-sm font-medium text-white hover:bg-signal hover:text-signal-ink">Save space</button>
        </div>
    </form>
</x-admin-layout>
