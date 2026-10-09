<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Space;
use App\Models\SpaceInventory;
use App\Models\Venue;
use App\Services\Reservation\ReservationHandover;
use App\Services\Reservation\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReservationController extends Controller
{
    private const SUPPORTED_LOCALES = ['id', 'en', 'ja'];

    /** How far ahead the date picker looks for open days. */
    private const AVAILABILITY_DAYS = 366;

    private const PHONE_PATTERN = '/^\+?[0-9\s\-().]{6,20}$/';

    /**
     * Guest-facing validation messages, keyed by locale.
     *
     * @var array<string, array<string, string>>
     */
    public const MESSAGES = [
        'en' => [
            'invalid' => 'Please check this field.',
            'start_past' => 'The event date cannot be in the past.',
            'end_after' => 'The last event day cannot be before the first.',
            'too_long' => 'Online requests cover up to :max event days. For a longer event, please contact our events team.',
            'space_unknown' => 'Please choose a space.',
            'capacity' => 'This space does not seat that many guests in the chosen setup. Choose a larger space or another layout.',
            'unavailable' => 'This space is not available on those dates.',
            'contact_email' => 'Please enter a valid email address.',
            'contact_phone' => 'Please enter a valid phone or WhatsApp number.',
        ],
        'id' => [
            'invalid' => 'Mohon periksa kolom ini.',
            'start_past' => 'Tanggal acara tidak boleh di masa lalu.',
            'end_after' => 'Hari terakhir acara tidak boleh sebelum hari pertama.',
            'too_long' => 'Permintaan online maksimal :max hari acara. Untuk acara yang lebih panjang, hubungi tim event kami.',
            'space_unknown' => 'Silakan pilih ruang.',
            'capacity' => 'Ruang ini tidak muat untuk jumlah tamu tersebut pada susunan yang dipilih. Pilih ruang yang lebih besar atau susunan lain.',
            'unavailable' => 'Ruang ini tidak tersedia pada tanggal tersebut.',
            'contact_email' => 'Masukkan alamat email yang valid.',
            'contact_phone' => 'Masukkan nomor telepon atau WhatsApp yang valid.',
        ],
        'ja' => [
            'invalid' => 'この項目をご確認ください。',
            'start_past' => '開催日は過去にできません。',
            'end_after' => '最終日は初日より前にできません。',
            'too_long' => 'オンラインでのご依頼は最大:max日までです。それ以上の長期開催はイベントチームにご相談ください。',
            'space_unknown' => '会場を選択してください。',
            'capacity' => 'この会場では、選択したレイアウトで人数に対応できません。広い会場か別のレイアウトをお選びください。',
            'unavailable' => 'この日程では、この会場はご利用いただけません。',
            'contact_email' => '有効なメールアドレスを入力してください。',
            'contact_phone' => '有効な電話番号またはWhatsApp番号を入力してください。',
        ],
    ];

    public function __construct(
        private readonly ReservationService $reservations,
        private readonly ReservationHandover $handover,
    ) {}

    /**
     * The days on which at least one space still has a free slot, so the
     * date picker can grey out the days that are fully booked.
     */
    public function availability(string $venueSlug): JsonResponse
    {
        $venue = $this->publishedVenue($venueSlug);
        $today = CarbonImmutable::now($venue->timezone)->startOfDay();
        $until = $today->addDays(self::AVAILABILITY_DAYS);

        $dates = SpaceInventory::whereIn('space_id', $venue->spaces()->where('is_active', true)->select('id'))
            ->whereRaw('total_units > booked_units')
            ->whereDate('event_date', '>=', $today->toDateString())
            ->whereDate('event_date', '<=', $until->toDateString())
            ->distinct()
            ->orderBy('event_date')
            ->get(['event_date'])
            ->map(fn (SpaceInventory $day) => $day->event_date->toDateString())
            ->unique()
            ->values();

        return response()->json([
            'today' => $today->toDateString(),
            'until' => $until->toDateString(),
            'dates' => $dates,
        ]);
    }

    public function quote(Request $request, string $venueSlug): JsonResponse
    {
        $venue = $this->publishedVenue($venueSlug);
        $locale = $this->locale($request, $venue);

        $data = $request->validate($this->eventRules($venue), $this->validationMessages($locale));
        [$space, $start, $end, $catering] = $this->resolveEvent($venue, $data, $locale);

        $quote = $this->reservations->quote($space, $start, $end, (int) $data['guests'], $catering);

        if (! $quote) {
            return $this->unavailable($venue, $space, $data, $locale);
        }

        return response()->json([
            'available' => true,
            'days' => $quote['days'],
            'space_total' => $quote['space_total'],
            'catering_total' => $quote['catering_total'],
            'subtotal' => $quote['subtotal'],
            'discount_percent' => $quote['discount_percent'],
            'discount_total' => $quote['discount_total'],
            'grand_total' => $quote['grand_total'],
            'currency' => $venue->currency,
        ]);
    }

    public function store(Request $request, string $venueSlug): JsonResponse
    {
        $venue = $this->publishedVenue($venueSlug);
        $locale = $this->locale($request, $venue);
        $messages = self::MESSAGES[$locale];

        $data = $request->validate([
            ...$this->eventRules($venue),
            'guest_name' => ['required', 'string', 'max:100'],
            'contact_type' => ['required', Rule::in(['whatsapp', 'phone', 'email'])],
            'contact_value' => ['required', 'string', 'max:120'],
            'special_request' => ['nullable', 'string', 'max:500'],
            'guest_token' => ['nullable', 'uuid'],
        ], $this->validationMessages($locale));

        $isEmail = $data['contact_type'] === 'email';

        if ($isEmail && ! filter_var($data['contact_value'], FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['contact_value' => $messages['contact_email']]);
        }

        if (! $isEmail && ! preg_match(self::PHONE_PATTERN, $data['contact_value'])) {
            throw ValidationException::withMessages(['contact_value' => $messages['contact_phone']]);
        }

        [$space, $start, $end, $catering] = $this->resolveEvent($venue, $data, $locale);

        $conversation = isset($data['guest_token'])
            ? Conversation::where('venue_id', $venue->id)->where('guest_token', $data['guest_token'])->first()
            : null;

        $booking = $this->reservations->createRequest($venue, $space, [
            'event_start' => $start,
            'event_end' => $end,
            'event_type' => $data['event_type'],
            'guests' => (int) $data['guests'],
            'setup_style' => $data['setup_style'] ?? null,
            'catering' => $catering,
            'guest_name' => $data['guest_name'],
            'guest_email' => $isEmail ? $data['contact_value'] : null,
            'guest_phone' => $isEmail ? null : $data['contact_value'],
            'contact_type' => $data['contact_type'],
            'locale' => $locale,
            'notes' => $data['special_request'] ?? null,
        ], $conversation);

        if (! $booking) {
            return $this->unavailable($venue, $space, $data, $locale);
        }

        return response()->json([
            'reference' => $booking->reference,
            'status' => $booking->status,
            'total' => (float) $booking->total_price,
            'currency' => $venue->currency,
            'handover' => $this->handover->forBooking($venue, $booking, $locale),
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function eventRules(Venue $venue): array
    {
        $today = now($venue->timezone)->toDateString();

        return [
            'space_slug' => ['required', 'string', 'max:120'],
            'event_start' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$today],
            'event_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:event_start'],
            'event_type' => ['required', Rule::in(Booking::EVENT_TYPES)],
            'guests' => ['required', 'integer', 'min:1', 'max:5000'],
            'setup_style' => ['nullable', Rule::in(Space::LAYOUTS)],
            'catering' => ['nullable', 'boolean'],
            'locale' => ['nullable', Rule::in(self::SUPPORTED_LOCALES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function validationMessages(string $locale): array
    {
        $messages = self::MESSAGES[$locale];

        return [
            'required' => $messages['invalid'],
            'date_format' => $messages['invalid'],
            'integer' => $messages['invalid'],
            'min' => $messages['invalid'],
            'max' => $messages['invalid'],
            'boolean' => $messages['invalid'],
            'in' => $messages['invalid'],
            'uuid' => $messages['invalid'],
            'event_start.after_or_equal' => $messages['start_past'],
            'event_end.after_or_equal' => $messages['end_after'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: Space, 1: CarbonImmutable, 2: CarbonImmutable, 3: bool}
     */
    private function resolveEvent(Venue $venue, array $data, string $locale): array
    {
        $messages = self::MESSAGES[$locale];

        $space = $venue->spaces()->where('is_active', true)->where('slug', $data['space_slug'])->first();

        if (! $space) {
            throw ValidationException::withMessages(['space_slug' => $messages['space_unknown']]);
        }

        $start = CarbonImmutable::parse($data['event_start'])->startOfDay();
        $end = CarbonImmutable::parse($data['event_end'])->startOfDay();

        if ((int) $start->diffInDays($end) + 1 > ReservationService::MAX_DAYS) {
            throw ValidationException::withMessages([
                'event_end' => str_replace(':max', (string) ReservationService::MAX_DAYS, $messages['too_long']),
            ]);
        }

        if (! $this->reservations->fitsCapacity($space, (int) $data['guests'], $data['setup_style'] ?? null)) {
            throw ValidationException::withMessages(['guests' => $messages['capacity']]);
        }

        return [$space, $start, $end, (bool) ($data['catering'] ?? false)];
    }

    /**
     * The space cannot be booked on those days: say so, and offer the spaces
     * that can host the same event on the same dates.
     *
     * @param  array<string, mixed>  $data
     */
    private function unavailable(Venue $venue, Space $requested, array $data, string $locale): JsonResponse
    {
        $start = CarbonImmutable::parse($data['event_start'])->startOfDay();
        $end = CarbonImmutable::parse($data['event_end'])->startOfDay();
        $guests = (int) $data['guests'];
        $layout = $data['setup_style'] ?? null;

        $alternatives = $venue->spaces()
            ->where('is_active', true)
            ->whereKeyNot($requested->id)
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (Space $space) => $this->reservations->fitsCapacity($space, $guests, $layout))
            ->map(function (Space $space) use ($start, $end, $guests, $locale) {
                $quote = $this->reservations->quote($space, $start, $end, $guests);

                return $quote ? [
                    'slug' => $space->slug,
                    'name' => $space->translatedName($locale),
                    'total' => $quote['grand_total'],
                ] : null;
            })
            ->filter()
            ->values();

        $message = self::MESSAGES[$locale]['unavailable'];

        return response()->json([
            'message' => $message,
            'errors' => ['space_slug' => [$message]],
            'alternatives' => $alternatives,
            'currency' => $venue->currency,
        ], 422);
    }

    private function locale(Request $request, Venue $venue): string
    {
        return in_array($request->input('locale'), self::SUPPORTED_LOCALES, true)
            ? $request->input('locale')
            : $venue->default_locale;
    }

    private function publishedVenue(string $venueSlug): Venue
    {
        $venue = Venue::where('slug', $venueSlug)->first();

        abort_if(! $venue || ! $venue->isPublished(), 404);

        return $venue;
    }
}
