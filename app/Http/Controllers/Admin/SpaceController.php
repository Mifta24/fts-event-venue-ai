<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesCurrentVenue;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Space;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SpaceController extends Controller
{
    use ResolvesCurrentVenue;

    public function index(Request $request): View
    {
        $venue = $this->currentVenue($request);

        $spaces = $venue->spaces()->withCount('images')->orderBy('sort_order')->get();

        return view('admin.spaces.index', compact('venue', 'spaces'));
    }

    public function create(Request $request): View
    {
        $venue = $this->currentVenue($request);

        return view('admin.spaces.form', [
            'venue' => $venue,
            'space' => new Space(['space_type' => 'indoor', 'layouts' => ['banquet' => 100], 'is_active' => true]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $venue = $this->currentVenue($request);
        $data = $this->validated($request);

        $space = $venue->spaces()->create($data);
        $this->syncImages($space, $request);

        return redirect()->route('admin.spaces.index')->with('status', "Space \"{$space->name}\" created.");
    }

    public function edit(Request $request, Space $space): View
    {
        $venue = $this->currentVenue($request);
        abort_if($space->venue_id !== $venue->id, 404);

        $space->load('images');

        return view('admin.spaces.form', compact('venue', 'space'));
    }

    public function update(Request $request, Space $space): RedirectResponse
    {
        $venue = $this->currentVenue($request);
        abort_if($space->venue_id !== $venue->id, 404);

        $space->update($this->validated($request, $space));
        $this->syncImages($space, $request);

        return redirect()->route('admin.spaces.index')->with('status', "Space \"{$space->name}\" updated.");
    }

    public function destroy(Request $request, Space $space): RedirectResponse
    {
        $venue = $this->currentVenue($request);
        abort_if($space->venue_id !== $venue->id, 404);

        $openBookings = $space->bookings()
            ->whereIn('status', [Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED])
            ->whereDate('event_end', '>=', now($venue->timezone)->toDateString())
            ->count();

        if ($openBookings > 0) {
            return back()->with('error', "\"{$space->name}\" still has {$openBookings} pending or confirmed booking(s). Cancel them first, or hide the space instead.");
        }

        $space->delete();

        return redirect()->route('admin.spaces.index')->with('status', 'Space deleted.');
    }

    private function validated(Request $request, ?Space $space = null): array
    {
        $layoutRules = collect(Space::LAYOUTS)->mapWithKeys(fn (string $layout) => ["layout_{$layout}" => ['nullable', 'integer', 'min:1', 'max:20000']])->all();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'translations.en.name' => ['nullable', 'string'],
            'translations.en.description' => ['nullable', 'string'],
            'translations.ja.name' => ['nullable', 'string'],
            'translations.ja.description' => ['nullable', 'string'],
            'space_type' => ['required', Rule::in(Space::TYPES)],
            'size_sqm' => ['nullable', 'integer', 'min:1'],
            'ceiling_height_m' => ['nullable', 'numeric', 'min:1', 'max:60'],
            'level_label' => ['nullable', 'string', 'max:30'],
            ...$layoutRules,
            'av_included' => ['sometimes', 'boolean'],
            'catering_available' => ['sometimes', 'boolean'],
            'catering_price' => ['nullable', 'numeric', 'min:0'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'amenities' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $layouts = collect(Space::LAYOUTS)
            ->mapWithKeys(fn (string $layout) => [$layout => isset($data["layout_{$layout}"]) ? (int) $data["layout_{$layout}"] : null])
            ->filter()
            ->all();

        if ($layouts === []) {
            throw ValidationException::withMessages(['layout_banquet' => 'Fill in the capacity of at least one setup.']);
        }

        $amenities = collect(explode(',', (string) ($data['amenities'] ?? '')))
            ->map(fn ($a) => Str::of($a)->trim()->slug('_')->toString())
            ->filter()
            ->values()
            ->all();

        return [
            'name' => $data['name'],
            'slug' => $space?->slug ?? $this->uniqueSlug($request->user()->currentVenue(), $data['name']),
            'description' => $data['description'] ?? null,
            'translations' => [
                'en' => ['name' => $data['translations']['en']['name'] ?? null, 'description' => $data['translations']['en']['description'] ?? null],
                'ja' => ['name' => $data['translations']['ja']['name'] ?? null, 'description' => $data['translations']['ja']['description'] ?? null],
            ],
            'space_type' => $data['space_type'],
            'size_sqm' => $data['size_sqm'] ?? null,
            'ceiling_height_m' => $data['ceiling_height_m'] ?? null,
            'level_label' => $data['level_label'] ?? null,
            'layouts' => $layouts,
            'av_included' => $request->boolean('av_included'),
            'catering_available' => $request->boolean('catering_available'),
            'catering_price' => $data['catering_price'] ?? null,
            'base_price' => $data['base_price'],
            'amenities' => $amenities,
            'is_active' => $request->boolean('is_active'),
            'sort_order' => $data['sort_order'] ?? 0,
        ];
    }

    private function uniqueSlug($venue, string $name): string
    {
        $base = Str::slug($name) ?: 'space';
        $slug = $base;
        $suffix = 1;

        while ($venue->spaces()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function syncImages(Space $space, Request $request): void
    {
        $deleteIds = collect($request->input('delete_images', []))->map(fn ($id) => (int) $id);
        if ($deleteIds->isNotEmpty()) {
            $space->images()->whereIn('id', $deleteIds)->delete();
        }

        $newImages = collect($request->input('new_images', []))
            ->filter(fn ($row) => filled($row['url'] ?? null));

        $sort = $space->images()->max('sort_order') + 1;
        foreach ($newImages as $row) {
            $space->images()->create([
                'image_url' => $row['url'],
                'alt_text' => $row['alt'] ?? $space->name,
                'tags' => collect(explode(',', $row['tags'] ?? ''))->map(fn ($t) => trim($t))->filter()->values()->all(),
                'sort_order' => $sort++,
            ]);
        }
    }
}
