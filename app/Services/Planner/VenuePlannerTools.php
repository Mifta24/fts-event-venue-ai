<?php

namespace App\Services\Planner;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\HandoverRequest;
use App\Models\Space;
use App\Models\Venue;
use App\Models\VenueKnowledgeItem;
use App\Notifications\NewHandoverRequest;
use App\Services\Reservation\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Executes the AI Event Planner's tools against this venue's controlled data.
 * Every venue fact, space, price and availability answer must pass through
 * here — the model itself is never trusted to hold any of it.
 */
class VenuePlannerTools
{
    public function __construct(
        private readonly Venue $venue,
        private readonly Conversation $conversation,
        private readonly string $locale,
        private readonly ReservationService $reservations = new ReservationService,
    ) {}

    /**
     * OpenAI-compatible function-calling schema (used by the local LM Studio
     * / Ollama endpoint via PlannerService — see topic in that class).
     */
    public static function definitions(): array
    {
        $layouts = ['type' => 'string', 'enum' => Space::LAYOUTS, 'description' => 'How the room is set up: banquet (round tables), theatre (rows of chairs), classroom, boardroom, or cocktail (standing reception)'];

        return array_map(
            fn (array $tool) => ['type' => 'function', 'function' => $tool],
            [
                [
                    'name' => 'search_knowledge',
                    'description' => 'Search the venue\'s approved knowledge base: house rules and policies (noise curfew, decoration, outside vendors, deposits, cancellation), facilities (stage, sound and lighting, bridal suite, green room, loading dock, generator), catering and beverage packages, parking and valet, transport and nearby hotels, and FAQs. Always use this instead of answering venue-fact questions from memory. Returns up to 5 matching entries.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => ['type' => 'string', 'description' => 'Keywords from the guest question, e.g. "parking", "outside caterer" or "curfew"'],
                            'category' => [
                                'type' => 'string',
                                'enum' => ['general', 'facilities', 'policies', 'catering', 'transport', 'faq'],
                                'description' => 'Optional category filter',
                            ],
                        ],
                        'required' => ['query'],
                    ],
                ],
                [
                    'name' => 'search_spaces',
                    'description' => 'Search the venue\'s halls, gardens and rooms that fit the guest\'s event date(s) and headcount, with real-time availability and the total price — weekday and multi-day discounts already applied — checked. Use this whenever a guest describes the event they plan (date, number of guests, type of event, setup, indoor or outdoor) rather than naming one space.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'event_start' => ['type' => 'string', 'description' => 'First event day, YYYY-MM-DD'],
                            'event_end' => ['type' => 'string', 'description' => 'Last event day, YYYY-MM-DD. The same as event_start for a one-day event'],
                            'guests' => ['type' => 'integer', 'minimum' => 1],
                            'layout' => $layouts,
                            'space_type' => ['type' => 'string', 'enum' => Space::TYPES, 'description' => 'Optional preference: indoor, semi_outdoor or outdoor'],
                            'catering' => ['type' => 'boolean', 'description' => 'Whether to price in-house catering too'],
                        ],
                        'required' => ['event_start', 'event_end', 'guests'],
                    ],
                ],
                [
                    'name' => 'get_space_detail',
                    'description' => 'Get full details and photos for one space by its slug (from a previous search_spaces result): size, ceiling height, capacity in every setup, what is included, catering option, and the discounts. Use this when the guest asks to see more about, or see photos of, a specific space.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'space_slug' => ['type' => 'string'],
                            'image_tag' => ['type' => 'string', 'description' => 'Optional filter, e.g. "stage", "banquet", "entrance", "outdoor", "night"'],
                        ],
                        'required' => ['space_slug'],
                    ],
                ],
                [
                    'name' => 'check_availability',
                    'description' => 'Get the current, real-time availability and exact total price for one space over specific event days, including in-house catering and any weekday or multi-day discount. ALWAYS call this before confirming a price or telling a guest a date is free — never state a price or availability from memory.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'space_slug' => ['type' => 'string'],
                            'event_start' => ['type' => 'string'],
                            'event_end' => ['type' => 'string'],
                            'guests' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Needed to price catering'],
                            'catering' => ['type' => 'boolean'],
                        ],
                        'required' => ['space_slug', 'event_start', 'event_end'],
                    ],
                ],
                [
                    'name' => 'create_booking_request',
                    'description' => 'Create an event booking request after the guest confirms the space and dates and you have their name and phone. This re-checks availability before booking. It does not charge payment or sign a contract — it creates a pending request for the events team to confirm.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'space_slug' => ['type' => 'string'],
                            'event_start' => ['type' => 'string'],
                            'event_end' => ['type' => 'string'],
                            'event_type' => ['type' => 'string', 'enum' => Booking::EVENT_TYPES],
                            'guests' => ['type' => 'integer', 'minimum' => 1],
                            'setup_style' => $layouts,
                            'catering' => ['type' => 'boolean'],
                            'guest_name' => ['type' => 'string'],
                            'guest_email' => ['type' => 'string'],
                            'guest_phone' => ['type' => 'string'],
                            'notes' => ['type' => 'string'],
                        ],
                        'required' => ['space_slug', 'event_start', 'event_end', 'event_type', 'guests', 'guest_name', 'guest_phone'],
                    ],
                ],
                [
                    'name' => 'request_human_handover',
                    'description' => 'Hand this conversation over to a human member of the events team. Use this for special requests, complaints, very large or multi-venue events, negotiated rates, events longer than the online limit, unusual cancellations, payment problems, or any question you cannot answer confidently from the available tools. Always write a clear summary so staff do not need to ask the guest to repeat themselves.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'reason' => [
                                'type' => 'string',
                                'enum' => ['special_request', 'complaint', 'large_event', 'negotiated_rate', 'unusual_cancellation', 'payment_issue', 'low_confidence'],
                            ],
                            'summary' => ['type' => 'string', 'description' => 'What the guest wants and the relevant context gathered so far, written for a staff member who has not seen this conversation'],
                        ],
                        'required' => ['reason', 'summary'],
                    ],
                ],
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{text: string, ui: array|null}
     */
    public function dispatch(string $name, array $input): array
    {
        return match ($name) {
            'search_knowledge' => $this->searchKnowledge($input),
            'search_spaces' => $this->searchSpaces($input),
            'get_space_detail' => $this->getSpaceDetail($input),
            'check_availability' => $this->checkAvailability($input),
            'create_booking_request' => $this->createBookingRequest($input),
            'request_human_handover' => $this->requestHumanHandover($input),
            default => ['text' => "Unknown tool: {$name}", 'ui' => null],
        };
    }

    private function searchKnowledge(array $input): array
    {
        $query = trim((string) ($input['query'] ?? ''));
        $category = $input['category'] ?? null;
        $words = collect(preg_split('/\s+/', Str::lower($query)))->filter();

        $items = $this->venue->knowledgeItems()
            ->where('is_active', true)
            ->when($category, fn ($q) => $q->where('category', $category))
            ->get()
            ->filter(function (VenueKnowledgeItem $item) use ($query, $words) {
                if ($query === '') {
                    return true;
                }

                $haystack = Str::lower($item->title.' '.$item->body.' '.implode(' ', $item->tags ?? []));

                return Str::contains($haystack, Str::lower($query))
                    || $words->contains(fn ($word) => Str::contains($haystack, $word));
            })
            ->take(5);

        if ($items->isEmpty()) {
            return [
                'text' => 'No matching knowledge base entry was found. Do not guess the answer — tell the guest you will check with the team, or call request_human_handover.',
                'ui' => null,
            ];
        }

        $results = $items->map(fn (VenueKnowledgeItem $item) => [
            'category' => $item->category,
            'title' => $item->translatedTitle($this->locale),
            'body' => $item->translatedBody($this->locale),
        ])->values()->all();

        return ['text' => json_encode($results, JSON_UNESCAPED_UNICODE), 'ui' => null];
    }

    private function searchSpaces(array $input): array
    {
        [$start, $end, $days] = $this->parseEvent($input['event_start'], $input['event_end']);
        $guests = (int) $input['guests'];
        $layout = $input['layout'] ?? null;
        $spaceType = $input['space_type'] ?? null;
        $catering = (bool) ($input['catering'] ?? false);

        if ($days < 1) {
            return ['text' => 'event_end must not be before event_start.', 'ui' => null];
        }

        $candidates = $this->venue->spaces()
            ->where('is_active', true)
            ->get()
            ->filter(fn (Space $space) => $this->reservations->fitsCapacity($space, $guests, $layout))
            ->when($spaceType, fn ($c) => $c->filter(fn (Space $space) => $space->space_type === $spaceType));

        $matches = [];
        foreach ($candidates as $space) {
            $stay = $this->reservations->quote($space, $start, $end, $guests, $catering);
            if (! $stay) {
                continue;
            }

            $thumbnail = $space->images->first();

            $matches[] = [
                'space_slug' => $space->slug,
                'name' => $space->translatedName($this->locale),
                'space_type' => $space->space_type,
                'size_sqm' => $space->size_sqm,
                'capacity' => $layout ? $space->capacityFor($layout) : $space->maxGuests(),
                'layout' => $layout,
                'layouts' => $space->layouts,
                'av_included' => $space->av_included,
                'catering_available' => $space->catering_available,
                'days' => $days,
                'discount_percent' => $stay['discount_percent'],
                'total_price' => $stay['grand_total'],
                'currency' => $this->venue->currency,
                'thumbnail_url' => $thumbnail?->image_source,
            ];
        }

        if (empty($matches)) {
            return [
                'text' => 'No space is free on every day of that event for this headcount. Tell the guest honestly and offer to check different dates, another setup or a smaller or larger space, or call request_human_handover if they want the events team to look for options.',
                'ui' => null,
            ];
        }

        usort($matches, fn ($a, $b) => $a['total_price'] <=> $b['total_price']);

        return [
            'text' => json_encode($matches, JSON_UNESCAPED_UNICODE),
            'ui' => [
                'type' => 'space_results',
                'event_start' => $input['event_start'],
                'event_end' => $input['event_end'],
                'spaces' => $matches,
            ],
        ];
    }

    private function getSpaceDetail(array $input): array
    {
        $space = $this->findSpace($input['space_slug']);
        if (! $space) {
            return ['text' => 'Space not found.', 'ui' => null];
        }

        $images = $space->images;
        $tag = $input['image_tag'] ?? null;
        if ($tag) {
            $images = $images->filter(fn ($img) => $img->hasTag($tag));
        }

        $detail = [
            'space_slug' => $space->slug,
            'name' => $space->translatedName($this->locale),
            'description' => $space->translatedDescription($this->locale),
            'space_type' => $space->space_type,
            'size_sqm' => $space->size_sqm,
            'ceiling_height_m' => $space->ceiling_height_m,
            'level_label' => $space->level_label,
            'layouts' => $space->layouts,
            'max_guests' => $space->maxGuests(),
            'av_included' => $space->av_included,
            'catering_available' => $space->catering_available,
            'catering_price_per_guest_per_day' => $space->catering_price,
            'amenities' => $space->amenities,
            'starting_price' => $space->base_price,
            'currency' => $this->venue->currency,
            'discounts' => [
                'weekday_discount_percent' => $this->venue->weekday_discount_percent,
                'weekday_note' => 'applies when every event day falls between Monday and Thursday',
                'multiday_discount_percent' => $this->venue->multiday_discount_percent,
                'multiday_from_days' => Venue::MULTIDAY_MIN_DAYS,
            ],
            'note' => 'starting_price is a per-day rental rate before discounts and is indicative only — always call check_availability for the exact price on real dates.',
            'images' => $images->map(fn ($img) => [
                'url' => $img->image_source,
                'tags' => $img->tags,
                'alt' => $img->alt_text,
            ])->values()->all(),
        ];

        return [
            'text' => json_encode($detail, JSON_UNESCAPED_UNICODE),
            'ui' => ['type' => 'space_detail', 'space' => $detail],
        ];
    }

    private function checkAvailability(array $input): array
    {
        $space = $this->findSpace($input['space_slug']);
        if (! $space) {
            return ['text' => 'Space not found.', 'ui' => null];
        }

        [$start, $end, $days] = $this->parseEvent($input['event_start'], $input['event_end']);
        if ($days < 1) {
            return ['text' => 'event_end must not be before event_start.', 'ui' => null];
        }

        $guests = (int) ($input['guests'] ?? 0);
        $catering = (bool) ($input['catering'] ?? false);
        $stay = $this->reservations->quote($space, $start, $end, $guests, $catering);

        if (! $stay) {
            return [
                'text' => json_encode([
                    'available' => false,
                    'space_slug' => $space->slug,
                    'message' => 'Not every day in this range is free for this space.',
                ], JSON_UNESCAPED_UNICODE),
                'ui' => ['type' => 'availability', 'available' => false, 'space_slug' => $space->slug],
            ];
        }

        $result = [
            'available' => true,
            'space_slug' => $space->slug,
            'name' => $space->translatedName($this->locale),
            'event_start' => $start->toDateString(),
            'event_end' => $end->toDateString(),
            'days' => $days,
            'daily_breakdown' => $stay['daily'],
            'space_total' => $stay['space_total'],
            'catering_total' => $stay['catering_total'],
            'subtotal' => $stay['subtotal'],
            'discount_percent' => $stay['discount_percent'],
            'discount_total' => $stay['discount_total'],
            'grand_total' => $stay['grand_total'],
            'currency' => $this->venue->currency,
            'min_available_units' => $stay['min_available_units'],
        ];

        return [
            'text' => json_encode($result, JSON_UNESCAPED_UNICODE),
            'ui' => ['type' => 'availability', 'available' => true, 'quote' => $result],
        ];
    }

    private function createBookingRequest(array $input): array
    {
        $space = $this->findSpace($input['space_slug']);
        if (! $space) {
            return ['text' => 'Space not found.', 'ui' => null];
        }

        [$start, $end, $days] = $this->parseEvent($input['event_start'], $input['event_end']);
        if ($days < 1) {
            return ['text' => 'event_end must not be before event_start.', 'ui' => null];
        }

        if ($days > ReservationService::MAX_DAYS) {
            return ['text' => 'This event is longer than the online limit of '.ReservationService::MAX_DAYS.' days. Call request_human_handover so the events team can handle it.', 'ui' => null];
        }

        $guests = (int) $input['guests'];
        $layout = $input['setup_style'] ?? null;

        if (! $this->reservations->fitsCapacity($space, $guests, $layout)) {
            return ['text' => 'This space does not seat that many guests in that setup. Suggest a larger space or another setup.', 'ui' => null];
        }

        $eventType = in_array($input['event_type'] ?? null, Booking::EVENT_TYPES, true) ? $input['event_type'] : 'social';

        $booking = $this->reservations->createRequest($this->venue, $space, [
            'event_start' => $start,
            'event_end' => $end,
            'event_type' => $eventType,
            'guests' => $guests,
            'setup_style' => $layout,
            'catering' => (bool) ($input['catering'] ?? false),
            'guest_name' => $input['guest_name'],
            'guest_email' => $input['guest_email'] ?? null,
            'guest_phone' => $input['guest_phone'],
            'locale' => $this->locale,
            'notes' => $input['notes'] ?? null,
        ], $this->conversation);

        if (! $booking) {
            return [
                'text' => 'The space is no longer free on these exact dates — availability may have just changed. Call check_availability again or offer alternative dates.',
                'ui' => null,
            ];
        }

        $payload = [
            'booking_reference' => $booking->reference,
            'space_slug' => $space->slug,
            'name' => $space->translatedName($this->locale),
            'event_type' => $eventType,
            'event_start' => $start->toDateString(),
            'event_end' => $end->toDateString(),
            'days' => $days,
            'guests' => $guests,
            'total_price' => (float) $booking->total_price,
            'currency' => $this->venue->currency,
            'status' => 'pending_confirmation',
        ];

        return [
            'text' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'ui' => ['type' => 'booking_confirmation', 'booking' => $payload],
        ];
    }

    private function requestHumanHandover(array $input): array
    {
        $reason = $input['reason'];
        $summary = $input['summary'];

        $handover = HandoverRequest::create([
            'conversation_id' => $this->conversation->id,
            'reason' => $reason,
            'summary' => $summary,
            'status' => HandoverRequest::STATUS_OPEN,
        ]);

        $this->venue->notifyStaff(new NewHandoverRequest($handover));

        $this->conversation->update([
            'status' => Conversation::STATUS_HANDED_OVER,
            'handover_summary' => $summary,
        ]);

        return [
            'text' => json_encode([
                'ok' => true,
                'message' => 'A staff member has been notified and will join this conversation shortly.',
            ], JSON_UNESCAPED_UNICODE),
            'ui' => ['type' => 'handover', 'reason' => $reason, 'summary' => $summary],
        ];
    }

    // --- helpers ---

    private function findSpace(string $slug): ?Space
    {
        return $this->venue->spaces()->where('slug', $slug)->first();
    }

    /**
     * Both dates are event days, so a one-day event passes the same date twice.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: int}
     */
    private function parseEvent(string $eventStart, string $eventEnd): array
    {
        $start = CarbonImmutable::parse($eventStart)->startOfDay();
        $end = CarbonImmutable::parse($eventEnd)->startOfDay();

        return [$start, $end, (int) $start->diffInDays($end, false) + 1];
    }
}
