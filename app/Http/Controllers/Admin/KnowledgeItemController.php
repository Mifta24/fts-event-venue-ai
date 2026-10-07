<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesCurrentApartment;
use App\Http\Controllers\Controller;
use App\Models\ApartmentKnowledgeItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KnowledgeItemController extends Controller
{
    use ResolvesCurrentApartment;

    public function index(Request $request): View
    {
        $apartment = $this->currentApartment($request);

        $items = $apartment->knowledgeItems()->orderBy('category')->orderBy('sort_order')->get();

        return view('admin.knowledge-items.index', compact('apartment', 'items'));
    }

    public function create(Request $request): View
    {
        $apartment = $this->currentApartment($request);

        return view('admin.knowledge-items.form', [
            'apartment' => $apartment,
            'item' => new ApartmentKnowledgeItem(['category' => ApartmentKnowledgeItem::CATEGORY_GENERAL, 'is_active' => true]),
            'categories' => $this->categories(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $apartment = $this->currentApartment($request);

        $item = $apartment->knowledgeItems()->create($this->validated($request));

        return redirect()->route('admin.knowledge-items.index')->with('status', "Knowledge item \"{$item->title}\" created.");
    }

    public function edit(Request $request, ApartmentKnowledgeItem $knowledgeItem): View
    {
        $apartment = $this->currentApartment($request);
        abort_if($knowledgeItem->apartment_id !== $apartment->id, 404);

        return view('admin.knowledge-items.form', [
            'apartment' => $apartment,
            'item' => $knowledgeItem,
            'categories' => $this->categories(),
        ]);
    }

    public function update(Request $request, ApartmentKnowledgeItem $knowledgeItem): RedirectResponse
    {
        $apartment = $this->currentApartment($request);
        abort_if($knowledgeItem->apartment_id !== $apartment->id, 404);

        $knowledgeItem->update($this->validated($request));

        return redirect()->route('admin.knowledge-items.index')->with('status', "Knowledge item \"{$knowledgeItem->title}\" updated.");
    }

    public function destroy(Request $request, ApartmentKnowledgeItem $knowledgeItem): RedirectResponse
    {
        $apartment = $this->currentApartment($request);
        abort_if($knowledgeItem->apartment_id !== $apartment->id, 404);

        $knowledgeItem->delete();

        return redirect()->route('admin.knowledge-items.index')->with('status', 'Knowledge item deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'category' => ['required', 'string', 'in:'.implode(',', $this->categories())],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'translations.en.title' => ['nullable', 'string'],
            'translations.en.body' => ['nullable', 'string'],
            'translations.ja.title' => ['nullable', 'string'],
            'translations.ja.body' => ['nullable', 'string'],
            'tags' => ['nullable', 'string'],
            'image_url' => ['nullable', 'url:http,https', 'max:2048'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        return [
            'category' => $data['category'],
            'title' => $data['title'],
            'body' => $data['body'],
            'translations' => [
                'en' => ['title' => $data['translations']['en']['title'] ?? null, 'body' => $data['translations']['en']['body'] ?? null],
                'ja' => ['title' => $data['translations']['ja']['title'] ?? null, 'body' => $data['translations']['ja']['body'] ?? null],
            ],
            'tags' => collect(explode(',', (string) ($data['tags'] ?? '')))->map(fn ($t) => trim($t))->filter()->values()->all(),
            'image_url' => $data['image_url'] ?? null,
            'is_active' => $request->boolean('is_active'),
            'sort_order' => $data['sort_order'] ?? 0,
        ];
    }

    private function categories(): array
    {
        return [
            ApartmentKnowledgeItem::CATEGORY_GENERAL,
            ApartmentKnowledgeItem::CATEGORY_FACILITIES,
            ApartmentKnowledgeItem::CATEGORY_POLICIES,
            ApartmentKnowledgeItem::CATEGORY_DINING,
            ApartmentKnowledgeItem::CATEGORY_TRANSPORT,
            ApartmentKnowledgeItem::CATEGORY_FAQ,
        ];
    }
}
