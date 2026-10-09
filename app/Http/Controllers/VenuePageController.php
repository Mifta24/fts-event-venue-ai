<?php

namespace App\Http\Controllers;

use App\Models\Space;
use App\Models\Venue;
use App\Models\VenueKnowledgeItem;
use App\Services\Reservation\ReservationHandover;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class VenuePageController extends Controller
{
    private const SUPPORTED_LOCALES = ['id', 'en', 'ja'];

    /** The planner's face for the chat and narrator avatars. */
    private const AVATAR_IMAGE = 'images/character/character avatar.jpg';

    /**
     * The planner's poses, transparent cut-outs shown in front of the scene photo.
     *
     * @var array<string, string>
     */
    private const CHARACTER_POSES = [
        'standing' => 'images/character/character standing.png',
        'presenting' => 'images/character/character presenting.png',
        'consulting' => 'images/character/character consulting.png',
    ];

    /**
     * Which pose she takes in each scene.
     *
     * @var array<string, string>
     */
    private const SCENE_POSES = [
        'spaces' => 'presenting',
        'reservation' => 'consulting',
        'staff' => 'consulting',
    ];

    /**
     * Event photography behind each scene, so every cue of the site opens
     * onto the venue and the occasions it hosts.
     *
     * @var array<string, array{image: string, focus: string, focusMobile: string}>
     */
    private const SCENE_PHOTOS = [
        'foyer' => ['image' => 'https://images.unsplash.com/photo-1549895058-36748fa6c6a7?auto=format&fit=crop&w=2000&q=80', 'focus' => 'center 45%', 'focusMobile' => '40% 45%'],
        'spaces' => ['image' => 'https://images.unsplash.com/photo-1712314947761-a8d718bd8c32?auto=format&fit=crop&w=2000&q=80', 'focus' => 'center 50%', 'focusMobile' => '50% 50%'],
        'facilities' => ['image' => 'https://images.unsplash.com/photo-1576514129883-2f1d47a65da6?auto=format&fit=crop&w=2000&q=80', 'focus' => 'center 55%', 'focusMobile' => '50% 55%'],
        'info' => ['image' => 'https://images.unsplash.com/photo-1560585810-44ce3741dd3b?auto=format&fit=crop&w=2000&q=80', 'focus' => 'center 50%', 'focusMobile' => '50% 50%'],
        'staff' => ['image' => 'https://images.unsplash.com/photo-1536392706976-e486e2ba97af?auto=format&fit=crop&w=2000&q=80', 'focus' => 'center 50%', 'focusMobile' => '50% 50%'],
        'reservation' => ['image' => 'https://images.unsplash.com/photo-1670529776180-60e4132ab90c?auto=format&fit=crop&w=2000&q=80', 'focus' => 'center 55%', 'focusMobile' => '50% 55%'],
    ];

    /** The cover of the opening screen, before any venue is entered. */
    private const OPENING_IMAGE = 'https://images.unsplash.com/photo-1526568929-7cdd510e77fd?auto=format&fit=crop&w=2000&q=80';

    /**
     * Each section of the site is a cue in the run of show. The code is what
     * the rundown panel and the cue display show.
     *
     * @var array<string, string>
     */
    public const CUES = [
        'foyer' => 'F',
        'spaces' => '01',
        'facilities' => '02',
        'info' => '03',
        'reservation' => '04',
        'staff' => '05',
    ];

    public function __construct(private readonly ReservationHandover $handover) {}

    /**
     * What the AI planner says when the guest steps onto a cue.
     * Every sentence is built from stored venue data, never generated.
     *
     * @var array<string, array<string, string>>
     */
    private const NARRATION = [
        'en' => [
            'spaces' => 'These are our event spaces: :count in all, from :price per event day. Open any of them and I will walk you through its capacity and setups, or ask me anything.',
            'spaces_one' => 'This is our event space, from :price per event day. Open it and I will walk you through its capacity and setups, or ask me anything.',
            'setting' => ':type of :size m²',
            'setting_only' => ':type',
            'ceiling' => 'with a :height m ceiling',
            'level' => 'on :level',
            'capacity' => 'It seats up to :guests guests, from :layouts.',
            'price' => 'Rental starts from :price per event day. Catering and discounts are worked into your quote, and final availability is confirmed by our events team.',
            'facilities' => 'We offer :count facilities and services, including :examples. Choose one to read the details, or ask me anything.',
            'facilities_one' => 'We offer :examples. Open it to read the details, or ask me anything.',
            'info_welcome' => 'Welcome to :venue.',
            'info_place' => 'The venue is in :location.',
            'info_hours' => 'Load-in is from :in and every event wraps up by :out.',
            'info_more' => 'Below you will find the address, house rules and frequently asked questions — or just ask me.',
            'tour_spaces' => 'Raising the curtain on our event spaces.',
            'tour_facilities' => 'Heading backstage to the facilities and services.',
            'tour_info' => 'Going to the venue information desk.',
            'tour_staff' => 'Going to meet the events team.',
            'tour_reservation' => 'Going to the planning table to shape your event.',
            'tour_foyer' => 'Returning to the foyer.',
            'listen' => 'Listen', 'stop' => 'Stop', 'skip' => 'Skip', 'speaks' => 'is speaking',
        ],
        'id' => [
            'spaces' => 'Ini ruang-ruang acara kami: total :count, mulai dari :price per hari acara. Buka salah satu, nanti saya jelaskan kapasitas dan susunannya — atau tanyakan apa saja kepada saya.',
            'spaces_one' => 'Ini ruang acara kami, mulai dari :price per hari acara. Buka ruangnya, nanti saya jelaskan kapasitas dan susunannya — atau tanyakan apa saja kepada saya.',
            'setting' => ':type seluas :size m²',
            'setting_only' => ':type',
            'ceiling' => 'dengan plafon setinggi :height m',
            'level' => 'di :level',
            'capacity' => 'Ruang ini memuat hingga :guests tamu, mulai dari :layouts.',
            'price' => 'Sewa mulai dari :price per hari acara. Katering dan diskon dihitung dalam penawaran Anda, dan ketersediaan final dikonfirmasi oleh tim event kami.',
            'facilities' => 'Kami menyediakan :count fasilitas dan layanan, di antaranya :examples. Pilih salah satu untuk membaca detailnya, atau tanyakan apa saja kepada saya.',
            'facilities_one' => 'Kami menyediakan :examples. Buka untuk membaca detailnya, atau tanyakan apa saja kepada saya.',
            'info_welcome' => 'Selamat datang di :venue.',
            'info_place' => 'Venue kami berada di :location.',
            'info_hours' => 'Load-in mulai pukul :in dan setiap acara selesai paling lambat pukul :out.',
            'info_more' => 'Di bawah ini ada alamat, aturan venue, dan pertanyaan yang sering diajukan — atau tanyakan langsung kepada saya.',
            'tour_spaces' => 'Membuka tirai untuk ruang-ruang acara kami.',
            'tour_facilities' => 'Menuju belakang panggung: fasilitas dan layanan.',
            'tour_info' => 'Menuju meja informasi venue.',
            'tour_staff' => 'Menuju ruang tim event.',
            'tour_reservation' => 'Menuju meja perencanaan untuk menyusun acara Anda.',
            'tour_foyer' => 'Kembali ke foyer.',
            'listen' => 'Dengarkan', 'stop' => 'Berhenti', 'skip' => 'Lewati', 'speaks' => 'sedang berbicara',
        ],
        'ja' => [
            'spaces' => 'こちらがイベントスペースです。全:count会場、1日 :price からご用意しています。会場を開いていただければ、収容人数とレイアウトをご案内します。ご質問もお気軽にどうぞ。',
            'spaces_one' => 'こちらがイベントスペースです。1日 :price からご利用いただけます。開いていただければ、収容人数とレイアウトをご案内します。ご質問もお気軽にどうぞ。',
            'setting' => ':size m²の:type',
            'setting_only' => ':type',
            'ceiling' => '天井高は:height mで',
            'level' => ':levelにあり、',
            'capacity' => '最大:guests名様まで、:layoutsなどのレイアウトでご利用いただけます。',
            'price' => 'ご利用料金は1日 :price から。ケータリングと割引は見積もりに反映され、空き状況はイベントチームが最終確認いたします。',
            'facilities' => ':count件の施設・サービスをご用意しています。:examples などです。選ぶと詳細をご覧いただけます。',
            'facilities_one' => ':examples をご用意しています。詳細をご覧ください。',
            'info_welcome' => ':venue へようこそ。',
            'info_place' => '会場は:locationにございます。',
            'info_hours' => '搬入は:in以降、イベントは:outまでに終了となります。',
            'info_more' => '以下に、所在地、ハウスルール、よくあるご質問をご案内しています。お気軽にお尋ねください。',
            'tour_spaces' => 'イベントスペースの幕を開けます。',
            'tour_facilities' => '舞台裏の施設・サービスへご案内します。',
            'tour_info' => '会場のインフォメーションへご案内します。',
            'tour_staff' => 'イベントチームのもとへご案内します。',
            'tour_reservation' => 'プランニングテーブルで、イベントの計画をお手伝いします。',
            'tour_foyer' => 'フォワイエへ戻ります。',
            'listen' => '音声で聞く', 'stop' => '停止', 'skip' => 'スキップ', 'speaks' => '話しています',
        ],
    ];

    /**
     * Guest-facing names for the coded space values stored in the database.
     *
     * @var array<string, array{layout: array<string, string>, type: array<string, string>, amenity: array<string, string>, event: array<string, string>}>
     */
    private const SPACE_TERMS = [
        'en' => [
            'layout' => ['banquet' => 'Banquet', 'theatre' => 'Theatre', 'classroom' => 'Classroom', 'boardroom' => 'Boardroom', 'cocktail' => 'Cocktail (standing)'],
            'type' => ['indoor' => 'Indoor hall', 'semi_outdoor' => 'Semi-outdoor pavilion', 'outdoor' => 'Open-air space'],
            'amenity' => ['stage' => 'Stage', 'led_wall' => 'LED video wall', 'sound_system' => 'Sound system', 'lighting_rig' => 'Lighting rig', 'projector' => 'Projector & screen', 'wifi' => 'Fibre Wi-Fi', 'air_conditioning' => 'Air conditioning', 'bridal_suite' => 'Bridal suite', 'green_room' => 'Green room', 'dance_floor' => 'Dance floor', 'podium' => 'Podium', 'round_tables' => 'Round tables', 'chiavari_chairs' => 'Chiavari chairs', 'generator' => 'Backup generator', 'valet' => 'Valet parking', 'catering_kitchen' => 'Catering kitchen', 'security' => 'Event security', 'red_carpet' => 'Red carpet entrance', 'video_conference' => 'Video conferencing', 'whiteboard' => 'Whiteboard', 'rain_cover' => 'Rain cover', 'fairy_lights' => 'Festoon lighting', 'city_view' => 'City view', 'garden_view' => 'Garden view'],
            'event' => ['wedding' => 'Wedding', 'corporate' => 'Corporate event', 'conference' => 'Conference', 'gala' => 'Gala dinner', 'birthday' => 'Birthday party', 'exhibition' => 'Exhibition', 'social' => 'Social gathering'],
        ],
        'id' => [
            'layout' => ['banquet' => 'Banquet (meja bundar)', 'theatre' => 'Teater', 'classroom' => 'Kelas', 'boardroom' => 'Boardroom', 'cocktail' => 'Cocktail (berdiri)'],
            'type' => ['indoor' => 'Ruang indoor', 'semi_outdoor' => 'Paviliun semi-terbuka', 'outdoor' => 'Ruang terbuka'],
            'amenity' => ['stage' => 'Panggung', 'led_wall' => 'Video wall LED', 'sound_system' => 'Sound system', 'lighting_rig' => 'Rig pencahayaan', 'projector' => 'Proyektor & layar', 'wifi' => 'Wi-Fi fiber', 'air_conditioning' => 'AC', 'bridal_suite' => 'Bridal suite', 'green_room' => 'Green room', 'dance_floor' => 'Lantai dansa', 'podium' => 'Podium', 'round_tables' => 'Meja bundar', 'chiavari_chairs' => 'Kursi chiavari', 'generator' => 'Genset cadangan', 'valet' => 'Valet parking', 'catering_kitchen' => 'Dapur katering', 'security' => 'Keamanan acara', 'red_carpet' => 'Karpet merah', 'video_conference' => 'Video conference', 'whiteboard' => 'Papan tulis', 'rain_cover' => 'Pelindung hujan', 'fairy_lights' => 'Lampu gantung festoon', 'city_view' => 'Pemandangan kota', 'garden_view' => 'Pemandangan taman'],
            'event' => ['wedding' => 'Pernikahan', 'corporate' => 'Acara perusahaan', 'conference' => 'Konferensi', 'gala' => 'Gala dinner', 'birthday' => 'Pesta ulang tahun', 'exhibition' => 'Pameran', 'social' => 'Acara sosial'],
        ],
        'ja' => [
            'layout' => ['banquet' => 'バンケット（円卓）', 'theatre' => 'シアター', 'classroom' => 'スクール', 'boardroom' => 'ボードルーム', 'cocktail' => 'カクテル（立食）'],
            'type' => ['indoor' => '屋内ホール', 'semi_outdoor' => '半屋外パビリオン', 'outdoor' => '屋外スペース'],
            'amenity' => ['stage' => 'ステージ', 'led_wall' => 'LEDビデオウォール', 'sound_system' => '音響設備', 'lighting_rig' => '照明設備', 'projector' => 'プロジェクター・スクリーン', 'wifi' => '光Wi-Fi', 'air_conditioning' => 'エアコン', 'bridal_suite' => 'ブライズルーム', 'green_room' => '控室', 'dance_floor' => 'ダンスフロア', 'podium' => '演台', 'round_tables' => '円卓', 'chiavari_chairs' => 'キアヴァリチェア', 'generator' => '非常用発電機', 'valet' => 'バレーパーキング', 'catering_kitchen' => 'ケータリングキッチン', 'security' => '警備スタッフ', 'red_carpet' => 'レッドカーペット', 'video_conference' => 'ビデオ会議設備', 'whiteboard' => 'ホワイトボード', 'rain_cover' => '雨よけ', 'fairy_lights' => 'フェストゥーンライト', 'city_view' => 'シティビュー', 'garden_view' => 'ガーデンビュー'],
            'event' => ['wedding' => '結婚式', 'corporate' => '企業イベント', 'conference' => 'カンファレンス', 'gala' => 'ガラディナー', 'birthday' => 'バースデーパーティー', 'exhibition' => '展示会', 'social' => '懇親会'],
        ],
    ];

    public function index(Request $request): View
    {
        $venues = Venue::where('public_status', 'published')->orderBy('name')->get();

        $locale = in_array($request->query('lang'), self::SUPPORTED_LOCALES, true)
            ? $request->query('lang')
            : ($venues->first()?->default_locale ?? 'id');

        return view('opening', [
            'venues' => $venues,
            'locale' => $locale,
            'supportedLocales' => self::SUPPORTED_LOCALES,
            'opening' => $this->openingLabels($locale),
            'openingImage' => self::OPENING_IMAGE,
            'cues' => self::CUES,
        ]);
    }

    public function show(Request $request, string $venueSlug): View
    {
        [$venue, $locale] = $this->resolveStage($request, $venueSlug);

        return view('venue.show', $this->stageData($venue, $locale, 'foyer'));
    }

    public function spaces(Request $request, string $venueSlug): View
    {
        [$venue, $locale] = $this->resolveStage($request, $venueSlug);

        return view('venue.spaces-index', $this->stageData($venue, $locale, 'spaces'));
    }

    public function space(Request $request, string $venueSlug, string $spaceSlug): View
    {
        [$venue, $locale] = $this->resolveStage($request, $venueSlug);

        $data = $this->stageData($venue, $locale, 'space');
        $spaces = $data['spaces'];
        $index = $spaces->search(fn (Space $space) => $space->slug === $spaceSlug);

        abort_if($index === false, 404);

        return view('venue.space-detail', [
            ...$data,
            'space' => $spaces[$index],
            'spaceIndex' => $index,
            'previousSpace' => $spaces[($index - 1 + $spaces->count()) % $spaces->count()],
            'nextSpace' => $spaces[($index + 1) % $spaces->count()],
        ]);
    }

    public function facilities(Request $request, string $venueSlug): View
    {
        [$venue, $locale] = $this->resolveStage($request, $venueSlug);

        return view('venue.facilities-index', $this->stageData($venue, $locale, 'facilities'));
    }

    public function facility(Request $request, string $venueSlug, int $facilityId): View
    {
        [$venue, $locale] = $this->resolveStage($request, $venueSlug);

        $data = $this->stageData($venue, $locale, 'facility');
        $facilities = $data['facilities'];
        $index = $facilities->search(fn (VenueKnowledgeItem $item) => $item->id === $facilityId);

        abort_if($index === false, 404);

        return view('venue.facility-detail', [
            ...$data,
            'facility' => $facilities[$index],
            'facilityIndex' => $index,
            'previousFacility' => $facilities[($index - 1 + $facilities->count()) % $facilities->count()],
            'nextFacility' => $facilities[($index + 1) % $facilities->count()],
        ]);
    }

    public function info(Request $request, string $venueSlug): View
    {
        [$venue, $locale] = $this->resolveStage($request, $venueSlug);

        return view('venue.info-index', $this->stageData($venue, $locale, 'info'));
    }

    public function staff(Request $request, string $venueSlug): View
    {
        [$venue, $locale] = $this->resolveStage($request, $venueSlug);

        return view('venue.staff-index', $this->stageData($venue, $locale, 'staff'));
    }

    public function reservationScene(Request $request, string $venueSlug): View
    {
        [$venue, $locale] = $this->resolveStage($request, $venueSlug);

        return view('venue.reservation-index', [
            ...$this->stageData($venue, $locale, 'reservation'),
            'preselectedSpace' => (string) $request->query('space', ''),
        ]);
    }

    /**
     * @return array{0: Venue, 1: string}
     */
    private function resolveStage(Request $request, string $venueSlug): array
    {
        $venue = Venue::where('slug', $venueSlug)->firstOrFail();

        abort_if(! $venue->isPublished(), 404);

        $locale = in_array($request->query('lang'), self::SUPPORTED_LOCALES, true)
            ? $request->query('lang')
            : $venue->default_locale;

        return [$venue, $locale];
    }

    /**
     * Everything the shared stage shell needs, for whichever cue the guest
     * has stepped onto.
     *
     * @return array<string, mixed>
     */
    private function stageData(Venue $venue, string $locale, string $scene): array
    {
        $spaces = $venue->spaces()
            ->where('is_active', true)
            ->with(['images' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get()
            ->values();

        $facilities = $venue->knowledgeItems()->where('is_active', true)
            ->whereIn('category', ['facilities', 'catering', 'transport'])
            ->orderBy('sort_order')->get()->values();

        $labels = $this->labels($locale);
        $foyer = $this->foyerLabels($locale);

        return [
            'venue' => $venue,
            'spaces' => $spaces,
            'locale' => $locale,
            'scene' => $scene,
            'cue' => self::CUES[['space' => 'spaces', 'facility' => 'facilities'][$scene] ?? $scene] ?? self::CUES['foyer'],
            'backdrop' => $this->sceneBackdrop($scene),
            'menuItems' => $this->stageMenu($venue, $locale, $labels, $foyer),
            'supportedLocales' => self::SUPPORTED_LOCALES,
            'labels' => $labels,
            'foyer' => $foyer,
            'spaceTerms' => self::SPACE_TERMS[$locale],
            'wizard' => $this->wizardLabels($venue, $locale),
            'narration' => self::NARRATION[$locale],
            'spaceNarrations' => $this->spaceNarrations($venue, $spaces, $locale),
            'staffLinks' => $this->handover->forStaff($venue, $locale),
            'today' => now($venue->timezone)->toDateString(),
            'infoItems' => $venue->knowledgeItems()->where('is_active', true)
                ->whereIn('category', ['general', 'policies', 'faq'])
                ->orderBy('sort_order')->get()->groupBy('category'),
            'facilities' => $facilities,
            'sceneNarrations' => $this->sceneNarrations($venue, $facilities, $locale),
        ];
    }

    /**
     * The photograph behind a scene, the planner posed in front of it,
     * plus the crop of her face used by the chat and narrator avatars.
     *
     * @return array{image: string, focus: string, focusMobile: string, character: string, pose: string, avatarImage: string, avatarZoom: string, avatarFocus: string}
     */
    private function sceneBackdrop(string $scene): array
    {
        $photo = self::SCENE_PHOTOS[['space' => 'spaces', 'facility' => 'facilities'][$scene] ?? $scene] ?? self::SCENE_PHOTOS['foyer'];

        $pose = self::SCENE_POSES[['space' => 'spaces'][$scene] ?? $scene] ?? 'standing';

        return [
            ...$photo,
            'character' => asset(self::CHARACTER_POSES[$pose]),
            'pose' => $pose,
            'avatarImage' => asset(self::AVATAR_IMAGE),
            'avatarZoom' => 'cover',
            'avatarFocus' => '50% 50%',
        ];
    }

    /**
     * The rundown panel. Every section is its own cue, so choosing one
     * drops the curtain on the current scene before the next one opens.
     *
     * @param  array<string, string>  $labels
     * @param  array<string, string>  $foyer
     * @return list<array{key: string, cue: string, label: string, href: string, exit: bool, tour: ?string, topic: string}>
     */
    private function stageMenu(Venue $venue, string $locale, array $labels, array $foyer): array
    {
        $tour = self::NARRATION[$locale];
        $url = fn (string $name) => route("venue.{$name}", ['venueSlug' => $venue->slug, 'lang' => $locale]);

        return [
            ['key' => 'spaces', 'cue' => self::CUES['spaces'], 'label' => $labels['spaces_heading'], 'href' => $url('spaces'), 'exit' => true, 'tour' => $tour['tour_spaces'], 'topic' => $labels['menu_spaces_q']],
            ['key' => 'facilities', 'cue' => self::CUES['facilities'], 'label' => $labels['menu_facilities'], 'href' => $url('facilities'), 'exit' => true, 'tour' => $tour['tour_facilities'], 'topic' => $labels['menu_facilities_q']],
            ['key' => 'info', 'cue' => self::CUES['info'], 'label' => $foyer['menu_info'], 'href' => $url('info'), 'exit' => true, 'tour' => $tour['tour_info'], 'topic' => $labels['menu_policies_q']],
            ['key' => 'reservation', 'cue' => self::CUES['reservation'], 'label' => $foyer['reservation'], 'href' => $url('reservation'), 'exit' => true, 'tour' => $tour['tour_reservation'], 'topic' => $foyer['reservation_q']],
            ['key' => 'staff', 'cue' => self::CUES['staff'], 'label' => $labels['menu_staff'], 'href' => $url('staff'), 'exit' => true, 'tour' => $tour['tour_staff'], 'topic' => $labels['menu_staff_q']],
        ];
    }

    /**
     * Intros for the facilities cue and the venue information scene,
     * built only from stored venue data.
     *
     * @param  Collection<int, VenueKnowledgeItem>  $facilities
     * @return array{facilities: ?string, info: string}
     */
    private function sceneNarrations(Venue $venue, Collection $facilities, string $locale): array
    {
        $templates = self::NARRATION[$locale];
        $separator = $locale === 'ja' ? '、' : '; ';
        $time = fn (?string $value) => $value ? substr($value, 0, 5) : null;

        $intro = null;

        if ($facilities->isNotEmpty()) {
            $examples = $facilities->take(3)->map(fn ($item) => $item->translatedTitle($locale))->implode($separator);
            $intro = str_replace(
                [':count', ':examples'],
                [(string) $facilities->count(), $examples],
                $templates[$facilities->count() === 1 ? 'facilities_one' : 'facilities']
            );
        }

        $location = collect([$venue->city, $venue->country])->filter()->implode($locale === 'ja' ? '、' : ', ');
        $parts = [str_replace(':venue', $venue->name, $templates['info_welcome'])];

        if ($location !== '') {
            $parts[] = str_replace(':location', $location, $templates['info_place']);
        }

        if ($time($venue->load_in_time) && $time($venue->curfew_time)) {
            $parts[] = str_replace([':in', ':out'], [$time($venue->load_in_time), $time($venue->curfew_time)], $templates['info_hours']);
        }

        $parts[] = $templates['info_more'];

        return ['facilities' => $intro, 'info' => implode(' ', $parts)];
    }

    /**
     * @param  Collection<int, Space>  $spaces
     * @return array{spaces: ?string, space: array<string, string>}
     */
    private function spaceNarrations(Venue $venue, Collection $spaces, string $locale): array
    {
        $templates = self::NARRATION[$locale];
        $terms = self::SPACE_TERMS[$locale];
        $money = fn ($value) => $venue->currency.' '.number_format((float) $value, 0, ',', '.');
        $sentence = fn (string $text) => preg_match('/[.!?。！？]$/u', $text) ? $text : $text.($locale === 'ja' ? '。' : '.');
        $join = $locale === 'ja' ? '、' : ', ';

        if ($spaces->isEmpty()) {
            return ['spaces' => null, 'space' => []];
        }

        $intro = str_replace(
            [':count', ':price'],
            [(string) $spaces->count(), $money($spaces->min('base_price'))],
            $templates[$spaces->count() === 1 ? 'spaces_one' : 'spaces']
        );

        $detail = $spaces->mapWithKeys(function (Space $space) use ($templates, $terms, $money, $sentence, $locale, $join) {
            $type = $terms['type'][$space->space_type] ?? Str::headline($space->space_type);

            $parts = [$space->translatedName($locale).'.', filled($space->translatedDescription($locale)) ? $sentence(trim($space->translatedDescription($locale))) : null];

            $setting = $space->size_sqm
                ? str_replace([':type', ':size'], [$type, (string) $space->size_sqm], $templates['setting'])
                : str_replace(':type', $type, $templates['setting_only']);

            if ($space->ceiling_height_m) {
                $setting .= $join.str_replace(':height', rtrim(rtrim((string) $space->ceiling_height_m, '0'), '.'), $templates['ceiling']);
            }

            if ($space->level_label) {
                $setting .= $join.str_replace(':level', $space->level_label, $templates['level']);
            }

            $parts[] = $sentence($setting);

            $layouts = collect($space->layouts ?? [])
                ->sortDesc()
                ->take(3)
                ->map(fn ($guests, $layout) => ($terms['layout'][$layout] ?? Str::headline($layout)).' '.$guests)
                ->implode($join);

            $parts[] = str_replace([':guests', ':layouts'], [(string) $space->maxGuests(), $layouts], $templates['capacity']);
            $parts[] = str_replace(':price', $money($space->base_price), $templates['price']);

            return [$space->slug => implode(' ', array_filter($parts))];
        })->all();

        return ['spaces' => $intro, 'space' => $detail];
    }

    /**
     * @return array<string, string>
     */
    private function openingLabels(string $locale): array
    {
        return match ($locale) {
            'en' => [
                'eyebrow' => 'Event venue · AI event planner', 'welcome' => 'Every great event', 'welcome_em' => 'starts with the right room.',
                'tagline' => 'Tour the halls, gardens and rooms with an AI event planner that knows every capacity, rate and house rule — from a boardroom meeting to a thousand-guest wedding.',
                'enter' => 'Enter :name', 'empty' => 'The virtual foyer is being prepared. Please come back soon.',
                'loading' => 'Raising the curtain…', 'sound_on' => 'Sound on', 'sound_off' => 'Sound off', 'directory' => 'Run of show',
                'spaces' => 'Event spaces', 'facilities' => 'Facilities & services', 'reservation' => 'Plan your event', 'staff' => 'Events team',
            ],
            'ja' => [
                'eyebrow' => 'イベント会場 · AIイベントプランナー', 'welcome' => '素晴らしいイベントは、', 'welcome_em' => 'ふさわしい会場から。',
                'tagline' => 'すべての収容人数・料金・ハウスルールを知るAIイベントプランナーと、ホール、ガーデン、会議室をご案内します。少人数の会議から千人規模の結婚式まで。',
                'enter' => ':name に入る', 'empty' => 'バーチャルフォワイエは準備中です。しばらくしてからお越しください。',
                'loading' => '幕を上げています…', 'sound_on' => 'サウンドオン', 'sound_off' => 'サウンドオフ', 'directory' => '進行表',
                'spaces' => 'イベントスペース', 'facilities' => '施設・サービス', 'reservation' => 'イベントを計画する', 'staff' => 'イベントチーム',
            ],
            default => [
                'eyebrow' => 'Venue acara · AI event planner', 'welcome' => 'Acara hebat', 'welcome_em' => 'dimulai dari ruang yang tepat.',
                'tagline' => 'Jelajahi ballroom, taman, dan ruang rapat bersama AI Event Planner yang hafal setiap kapasitas, tarif, dan aturan venue — dari rapat direksi sampai pernikahan seribu tamu.',
                'enter' => 'Masuk ke :name', 'empty' => 'Foyer virtual sedang dipersiapkan. Silakan kembali lagi nanti.',
                'loading' => 'Membuka tirai…', 'sound_on' => 'Suara aktif', 'sound_off' => 'Suara mati', 'directory' => 'Rundown acara',
                'spaces' => 'Ruang acara', 'facilities' => 'Fasilitas & layanan', 'reservation' => 'Rencanakan acara', 'staff' => 'Tim event',
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function wizardLabels(Venue $venue, string $locale): array
    {
        $discountHint = str_replace(
            [':weekday', ':multiday_days', ':multiday'],
            [(string) $venue->weekday_discount_percent, (string) Venue::MULTIDAY_MIN_DAYS, (string) $venue->multiday_discount_percent],
            match ($locale) {
                'en' => 'Event rates: :weekday% off when every event day is Monday to Thursday, :multiday% off for events of :multiday_days days or more.',
                'ja' => 'イベント割引：全日程が月〜木なら:weekday%オフ、:multiday_days日以上のイベントは:multiday%オフ。',
                default => 'Tarif acara: hemat :weekday% bila semua hari acara Senin–Kamis, hemat :multiday% untuk acara :multiday_days hari atau lebih.',
            }
        );

        $terms = self::SPACE_TERMS[$locale];

        return [...ReservationController::MESSAGES[$locale], 'discount_hint' => $venue->weekday_discount_percent > 0 || $venue->multiday_discount_percent > 0 ? $discountHint : '', 'event_names' => $terms['event'], 'layout_names' => $terms['layout'], ...match ($locale) {
            'en' => [
                'title' => 'Event request', 'intro' => 'A few quick steps. Final availability and rates are confirmed by our events team.',
                'step_of' => 'Step :current of :total', 'steps' => ['Dates', 'Your event', 'Space', 'Your details', 'Summary'],
                'cal_prev' => 'Previous month', 'cal_next' => 'Next month', 'cal_pick_start' => 'Pick the first day of your event.', 'cal_pick_end' => 'Now pick the last day — tap the same day again for a one-day event.', 'cal_none' => 'No dates are open online right now. Ask the planner or the events team.', 'cal_full' => 'Fully booked', 'cal_choose' => 'Choose a date',
                'event_start' => 'First day', 'event_end' => 'Last day', 'days' => 'day(s)', 'event_type' => 'Type of event', 'guests' => 'Number of guests', 'setup_style' => 'Setup',
                'choose_space' => 'Choose a space', 'fits' => 'Up to :count guests', 'too_small' => 'Does not fit your guest list in this setup',
                'guests_unit' => 'guests', 'catering_short' => 'catering', 'catering' => 'Add in-house catering', 'catering_each' => '+:price per guest, per day', 'name' => 'Full name', 'contact_method' => 'How should we contact you?',
                'whatsapp' => 'WhatsApp', 'phone' => 'Phone', 'email' => 'Email', 'contact_value' => 'Number or email address',
                'special' => 'Special request (optional)', 'special_placeholder' => 'e.g. rehearsal the day before, outside caterer, stage for a live band',
                'next' => 'Next', 'back' => 'Back', 'edit' => 'Edit', 'submit' => 'Submit event request', 'sending' => 'Sending…',
                'checking' => 'Checking availability…', 'estimated_total' => 'Estimated total', 'subtotal' => 'Subtotal', 'discount' => 'Event discount',
                'summary_dates' => 'Dates', 'summary_event' => 'Event', 'summary_space' => 'Space',
                'contact' => 'Contact', 'disclaimer' => 'This is an event request, not a contract. Final availability and rates are confirmed by our events team. No payment is taken now.',
                'available_instead' => 'Free on your dates instead:', 'done_title' => 'Request received', 'reference' => 'Your reference',
                'awaiting' => 'Awaiting confirmation from the events team', 'done_hint' => 'Send your request to our team to speed up confirmation.',
                'send_whatsapp' => 'Send to WhatsApp', 'call_venue' => 'Call the events team', 'email_venue' => 'Email the events team', 'new_request' => 'New request',
                'error_network' => 'Connection problem. Your details are saved — please try again or contact the events team.',
                'error_generic' => 'Something went wrong. Please try again or contact the events team.', 'select_space' => 'Please choose a space.',
                'contact_staff' => 'Contact the events team',
            ],
            'ja' => [
                'title' => 'イベントのご依頼', 'intro' => 'かんたんな手順です。空き状況と料金はイベントチームが最終確認いたします。',
                'step_of' => 'ステップ :current / :total', 'steps' => ['日程', 'イベント', '会場', 'ご連絡先', '確認'],
                'cal_prev' => '前の月', 'cal_next' => '次の月', 'cal_pick_start' => 'イベントの初日を選んでください。', 'cal_pick_end' => '続いて最終日を選んでください。1日のみの場合は同じ日をもう一度タップ。', 'cal_none' => '現在オンラインで受付中の日程はありません。プランナーまたはイベントチームにご相談ください。', 'cal_full' => '満室', 'cal_choose' => '日付を選択',
                'event_start' => '初日', 'event_end' => '最終日', 'days' => '日間', 'event_type' => 'イベントの種類', 'guests' => 'ご来場人数', 'setup_style' => 'レイアウト',
                'choose_space' => '会場を選ぶ', 'fits' => '最大:count名', 'too_small' => 'このレイアウトでは人数に対応できません',
                'guests_unit' => '名', 'catering_short' => 'ケータリング', 'catering' => '館内ケータリングを追加', 'catering_each' => '+:price／1名・1日', 'name' => 'お名前', 'contact_method' => 'ご連絡方法',
                'whatsapp' => 'WhatsApp', 'phone' => '電話', 'email' => 'メール', 'contact_value' => '番号またはメールアドレス',
                'special' => 'ご要望（任意）', 'special_placeholder' => '例：前日リハーサル、外部ケータリング、生演奏用ステージ',
                'next' => '次へ', 'back' => '戻る', 'edit' => '編集', 'submit' => 'イベントのご依頼を送信', 'sending' => '送信中…',
                'checking' => '空き状況を確認中…', 'estimated_total' => '概算合計', 'subtotal' => '小計', 'discount' => 'イベント割引',
                'summary_dates' => '日程', 'summary_event' => 'イベント', 'summary_space' => '会場',
                'contact' => 'ご連絡先', 'disclaimer' => 'これはイベントのご依頼であり、契約ではありません。空き状況と料金はイベントチームが最終確認します。現時点でお支払いは発生しません。',
                'available_instead' => 'ご希望の日程で空いている会場：', 'done_title' => 'ご依頼を受け付けました', 'reference' => '受付番号',
                'awaiting' => 'イベントチームの確認待ち', 'done_hint' => 'ご依頼をスタッフに送ると、確認がスムーズです。',
                'send_whatsapp' => 'WhatsAppで送る', 'call_venue' => 'イベントチームに電話', 'email_venue' => 'イベントチームにメール', 'new_request' => '新しいご依頼',
                'error_network' => '接続に問題があります。入力内容は保存されています。もう一度お試しいただくか、イベントチームにご連絡ください。',
                'error_generic' => 'エラーが発生しました。もう一度お試しいただくか、イベントチームにご連絡ください。', 'select_space' => '会場を選択してください。',
                'contact_staff' => 'イベントチームに連絡',
            ],
            default => [
                'title' => 'Permintaan acara', 'intro' => 'Hanya beberapa langkah singkat. Ketersediaan dan tarif final dikonfirmasi oleh tim event kami.',
                'step_of' => 'Langkah :current dari :total', 'steps' => ['Tanggal', 'Acara', 'Ruang', 'Data Anda', 'Ringkasan'],
                'cal_prev' => 'Bulan sebelumnya', 'cal_next' => 'Bulan berikutnya', 'cal_pick_start' => 'Pilih hari pertama acara Anda.', 'cal_pick_end' => 'Sekarang pilih hari terakhir — ketuk hari yang sama lagi untuk acara satu hari.', 'cal_none' => 'Belum ada tanggal yang dibuka untuk permintaan online. Tanyakan ke planner atau tim event.', 'cal_full' => 'Penuh', 'cal_choose' => 'Pilih tanggal',
                'event_start' => 'Hari pertama', 'event_end' => 'Hari terakhir', 'days' => 'hari', 'event_type' => 'Jenis acara', 'guests' => 'Jumlah tamu', 'setup_style' => 'Susunan ruang',
                'choose_space' => 'Pilih ruang', 'fits' => 'Hingga :count tamu', 'too_small' => 'Tidak muat untuk jumlah tamu Anda pada susunan ini',
                'guests_unit' => 'tamu', 'catering_short' => 'katering', 'catering' => 'Tambah katering in-house', 'catering_each' => '+:price per tamu, per hari', 'name' => 'Nama lengkap', 'contact_method' => 'Bagaimana kami menghubungi Anda?',
                'whatsapp' => 'WhatsApp', 'phone' => 'Telepon', 'email' => 'Email', 'contact_value' => 'Nomor atau alamat email',
                'special' => 'Permintaan khusus (opsional)', 'special_placeholder' => 'mis. gladi resik sehari sebelumnya, katering luar, panggung untuk band live',
                'next' => 'Lanjut', 'back' => 'Kembali', 'edit' => 'Ubah', 'submit' => 'Kirim permintaan acara', 'sending' => 'Mengirim…',
                'checking' => 'Memeriksa ketersediaan…', 'estimated_total' => 'Perkiraan total', 'subtotal' => 'Subtotal', 'discount' => 'Diskon acara',
                'summary_dates' => 'Tanggal', 'summary_event' => 'Acara', 'summary_space' => 'Ruang',
                'contact' => 'Kontak', 'disclaimer' => 'Ini adalah permintaan acara, bukan kontrak. Ketersediaan dan tarif final dikonfirmasi oleh tim event. Belum ada pembayaran yang diambil.',
                'available_instead' => 'Tersedia di tanggal Anda:', 'done_title' => 'Permintaan diterima', 'reference' => 'Nomor referensi',
                'awaiting' => 'Menunggu konfirmasi tim event', 'done_hint' => 'Kirim permintaan ke tim kami agar konfirmasi lebih cepat.',
                'send_whatsapp' => 'Kirim ke WhatsApp', 'call_venue' => 'Telepon tim event', 'email_venue' => 'Email tim event', 'new_request' => 'Permintaan baru',
                'error_network' => 'Koneksi bermasalah. Data Anda tersimpan — coba lagi atau hubungi tim event.',
                'error_generic' => 'Terjadi kesalahan. Coba lagi atau hubungi tim event.', 'select_space' => 'Silakan pilih ruang.',
                'contact_staff' => 'Hubungi tim event',
            ],
        }];
    }

    /**
     * @return array<string, string>
     */
    private function foyerLabels(string $locale): array
    {
        return match ($locale) {
            'en' => [
                'welcome' => 'Your event,', 'welcome_em' => 'beautifully staged',
                'intro' => 'Ballrooms, gardens and meeting suites for weddings, galas and conferences. Step through the curtain into any space — your AI event planner walks the room with you.',
                'home' => 'Foyer', 'explore' => 'Run of show', 'reservation' => 'Plan your event', 'cue' => 'Cue',
                'reservation_intro' => 'Tell your AI event planner the date, the kind of event and how many guests are coming. We will help you find a space and submit an event request.',
                'reservation_q' => 'I would like to book a space for an event. Please help me check availability.',
                'start_booking' => 'Plan my event', 'available' => 'Event planner on duty, 24/7',
                'assistant' => 'Your AI event planner', 'illustration' => 'AI illustration',
                'staff_intro' => 'Need a person? Message us and the events team — sales, coordination and technical crew — will pick it up.',
                'empty' => 'Ask your AI event planner for more information.', 'back' => 'Back to the foyer',
                'load_in' => 'Load-in from', 'curfew' => 'Event ends by', 'location' => 'Find the venue',
                'connection_error' => 'Chat could not connect. Please reload to try again.',
                'spaces_empty' => 'Space information will be available soon. Please ask our team.',
                'menu_info' => 'Venue information', 'info_about' => 'About the venue', 'info_policies' => 'House rules', 'info_faq' => 'Frequently asked questions', 'info_tab_about' => 'About', 'info_tab_faq' => 'FAQ',
                'info_address' => 'Address', 'info_hours' => 'Load-in / curfew', 'info_contact' => 'Contact', 'info_map' => 'Open in Maps', 'info_ask' => 'Ask about the venue',
                'facility_counter' => 'Facility', 'prev_facility' => 'Previous', 'next_facility' => 'Next', 'ask_facility' => 'Ask about this facility', 'back_facilities' => 'All facilities', 'open_facility' => 'Read more', 'ask_facility_q' => 'Tell me more about :name.',
                'loading' => 'Raising the curtain…',
                'sound_on' => 'Sound on', 'sound_off' => 'Sound off',
                'space_scene' => 'Space details', 'space_counter' => 'Space', 'gallery' => 'Photo gallery', 'photo' => 'Photo',
                'no_photo' => 'Photos coming soon', 'prev_space' => 'Previous', 'next_space' => 'Next',
                'ask_space' => 'Ask about this space', 'reserve_space' => 'Request this space',
                'size' => 'Size', 'capacity' => 'Capacity', 'ceiling' => 'Ceiling', 'level' => 'Level', 'setting' => 'Setting', 'av' => 'Sound & lighting', 'catering' => 'Catering', 'layouts' => 'Capacity by setup', 'guests' => 'guests',
                'av_yes' => 'Included', 'av_no' => 'On request', 'catering_yes' => 'In-house', 'catering_no' => 'Not offered', 'amenities' => 'Included in the space',
                'availability_note' => 'Final availability and rates are confirmed by the events team.',
                'discount_note' => 'Weekday events (Mon–Thu) :weekday% off · events of :days+ days :multiday% off',
                'ask_space_q' => 'Tell me more about :name.',
                'cta_spaces' => 'See the event spaces', 'stat_from' => 'From, per event day', 'stat_capacity' => 'Up to', 'stat_capacity_value' => ':count guests',
                'stat_discount' => 'Weekday events', 'stat_discount_value' => ':percent% off', 'stat_load_in' => 'Load-in',
            ],
            'ja' => [
                'welcome' => 'あなたのイベントを、', 'welcome_em' => '美しく演出します',
                'intro' => '結婚式、ガラ、カンファレンスのためのボールルーム、ガーデン、会議室。幕を抜けてお好きな会場へ — AIイベントプランナーがご一緒します。',
                'home' => 'フォワイエ', 'explore' => '進行表', 'reservation' => 'イベントを計画する', 'cue' => 'キュー',
                'reservation_intro' => '日程、イベントの種類、ご来場人数をAIイベントプランナーにお伝えください。会場探しとイベントのご依頼をお手伝いします。',
                'reservation_q' => 'イベントの会場を予約したいです。空き状況を確認してください。',
                'start_booking' => 'イベントを計画する', 'available' => 'プランナー 24時間対応',
                'assistant' => 'AIイベントプランナー', 'illustration' => 'AIイラスト',
                'staff_intro' => 'スタッフと話したい場合は、メッセージをお送りください。営業・進行管理・技術クルーのイベントチームが対応します。',
                'empty' => '詳しくはAIイベントプランナーにお尋ねください。', 'back' => 'フォワイエに戻る',
                'load_in' => '搬入開始', 'curfew' => '終了時刻', 'location' => 'アクセス',
                'connection_error' => 'チャットに接続できませんでした。再読み込みしてください。',
                'spaces_empty' => '会場の情報は準備中です。スタッフにお尋ねください。',
                'menu_info' => '会場のご案内', 'info_about' => '会場について', 'info_policies' => 'ハウスルール', 'info_faq' => 'よくあるご質問', 'info_tab_about' => '概要', 'info_tab_faq' => 'FAQ',
                'info_address' => '所在地', 'info_hours' => '搬入 / 終了', 'info_contact' => 'お問い合わせ', 'info_map' => '地図で開く', 'info_ask' => '会場について聞く',
                'facility_counter' => '施設', 'prev_facility' => '前へ', 'next_facility' => '次へ', 'ask_facility' => 'この施設について聞く', 'back_facilities' => '施設一覧', 'open_facility' => '詳しく見る', 'ask_facility_q' => ':name について詳しく教えてください。',
                'loading' => '幕を上げています…',
                'sound_on' => 'サウンドオン', 'sound_off' => 'サウンドオフ',
                'space_scene' => '会場のご案内', 'space_counter' => '会場', 'gallery' => 'フォトギャラリー', 'photo' => '写真',
                'no_photo' => '写真は準備中です', 'prev_space' => '前へ', 'next_space' => '次へ',
                'ask_space' => 'この会場について聞く', 'reserve_space' => 'この会場をリクエスト',
                'size' => '広さ', 'capacity' => '収容人数', 'ceiling' => '天井高', 'level' => 'フロア', 'setting' => '環境', 'av' => '音響・照明', 'catering' => 'ケータリング', 'layouts' => 'レイアウト別収容人数', 'guests' => '名',
                'av_yes' => '含む', 'av_no' => 'オプション', 'catering_yes' => '館内対応', 'catering_no' => '対応なし', 'amenities' => '会場の設備',
                'availability_note' => '空き状況と料金は、イベントチームが最終確認いたします。',
                'discount_note' => '平日（月〜木）開催は:weekday%オフ · :days日以上のイベントは:multiday%オフ',
                'ask_space_q' => ':name について詳しく教えてください。',
                'cta_spaces' => 'イベントスペースを見る', 'stat_from' => '1日あたり', 'stat_capacity' => '最大', 'stat_capacity_value' => ':count名',
                'stat_discount' => '平日開催', 'stat_discount_value' => ':percent%オフ', 'stat_load_in' => '搬入開始',
            ],
            default => [
                'welcome' => 'Acara Anda,', 'welcome_em' => 'dipentaskan dengan indah',
                'intro' => 'Ballroom, taman, dan ruang rapat untuk pernikahan, gala, dan konferensi. Masuk lewat tirai ke ruang mana pun — AI Event Planner ikut menemani.',
                'home' => 'Foyer', 'explore' => 'Rundown acara', 'reservation' => 'Rencanakan acara', 'cue' => 'Cue',
                'reservation_intro' => 'Ceritakan tanggal, jenis acara, dan jumlah tamu kepada AI Event Planner. Kami bantu carikan ruang hingga pengajuan permintaan acara.',
                'reservation_q' => 'Saya ingin memesan ruang untuk acara. Bantu saya cek ketersediaan.',
                'start_booking' => 'Rencanakan acara saya', 'available' => 'Event planner siaga 24 jam',
                'assistant' => 'AI Event Planner Anda', 'illustration' => 'Ilustrasi AI',
                'staff_intro' => 'Butuh bicara dengan orang? Kirim pesan dan tim event — sales, koordinator, dan kru teknis — akan menindaklanjuti.',
                'empty' => 'Tanyakan informasi selengkapnya kepada AI Event Planner.', 'back' => 'Kembali ke foyer',
                'load_in' => 'Load-in mulai', 'curfew' => 'Acara selesai', 'location' => 'Lokasi venue',
                'connection_error' => 'Chat belum tersambung. Muat ulang halaman untuk mencoba lagi.',
                'spaces_empty' => 'Informasi ruang segera tersedia. Silakan tanyakan kepada tim kami.',
                'menu_info' => 'Informasi venue', 'info_about' => 'Tentang venue', 'info_policies' => 'Aturan venue', 'info_faq' => 'Pertanyaan yang sering diajukan', 'info_tab_about' => 'Tentang', 'info_tab_faq' => 'FAQ',
                'info_address' => 'Alamat', 'info_hours' => 'Load-in / jam malam', 'info_contact' => 'Kontak', 'info_map' => 'Buka di Maps', 'info_ask' => 'Tanya tentang venue',
                'facility_counter' => 'Fasilitas', 'prev_facility' => 'Sebelumnya', 'next_facility' => 'Berikutnya', 'ask_facility' => 'Tanya tentang fasilitas ini', 'back_facilities' => 'Semua fasilitas', 'open_facility' => 'Selengkapnya', 'ask_facility_q' => 'Ceritakan lebih banyak tentang :name.',
                'loading' => 'Membuka tirai…',
                'sound_on' => 'Suara aktif', 'sound_off' => 'Suara mati',
                'space_scene' => 'Detail ruang', 'space_counter' => 'Ruang', 'gallery' => 'Galeri foto', 'photo' => 'Foto',
                'no_photo' => 'Foto segera tersedia', 'prev_space' => 'Sebelumnya', 'next_space' => 'Berikutnya',
                'ask_space' => 'Tanya tentang ruang ini', 'reserve_space' => 'Ajukan ruang ini',
                'size' => 'Luas', 'capacity' => 'Kapasitas', 'ceiling' => 'Plafon', 'level' => 'Lantai', 'setting' => 'Suasana', 'av' => 'Sound & lighting', 'catering' => 'Katering', 'layouts' => 'Kapasitas per susunan', 'guests' => 'tamu',
                'av_yes' => 'Termasuk', 'av_no' => 'Opsional', 'catering_yes' => 'Tersedia in-house', 'catering_no' => 'Tidak tersedia', 'amenities' => 'Termasuk di ruang ini',
                'availability_note' => 'Ketersediaan dan tarif final dikonfirmasi oleh tim event.',
                'discount_note' => 'Acara hari kerja (Sen–Kam) hemat :weekday% · acara :days+ hari hemat :multiday%',
                'ask_space_q' => 'Ceritakan lebih banyak tentang :name.',
                'cta_spaces' => 'Lihat ruang acara', 'stat_from' => 'Mulai per hari acara', 'stat_capacity' => 'Hingga', 'stat_capacity_value' => ':count tamu',
                'stat_discount' => 'Acara hari kerja', 'stat_discount_value' => 'hemat :percent%', 'stat_load_in' => 'Load-in',
            ],
        };
    }

    /**
     * @return array<string, string>
     */
    private function labels(string $locale): array
    {
        return match ($locale) {
            'en' => [
                'from' => 'from',
                'per_day' => '/ event day',
                'ask_ai' => 'Ask the AI Event Planner',
                'spaces_heading' => 'Event spaces',
                'chat_heading' => 'AI Event Planner',
                'chat_subtitle' => 'Ask about spaces, capacity or catering — on duty 24/7.',
                'chat_placeholder' => 'Ask about a hall, a date or a house rule…',
                'chat_send' => 'Send',
                'chat_open' => 'Talk to the AI Event Planner',
                'chat_close' => 'Close conversation',
                'chat_intro' => "Hi! I'm the AI Event Planner of this venue. Tell me about your event — the date, the guests and the occasion — or ask me about spaces, catering and house rules.",
                'av_included' => 'Sound & lighting included',
                'max_guests' => 'guests',
                'chat_av_extra' => 'AV on request',
                'chat_days' => 'day(s)',
                'chat_discount' => 'event discount',
                'chat_no_availability' => 'Not available on those dates.',
                'chat_booking_received' => 'Event request received',
                'chat_reference' => 'Ref',
                'handed_over' => 'A member of the events team has joined this conversation and will reply shortly.',
                'chat_status_sent' => 'Message sent',
                'chat_status_waiting' => 'Waiting for staff reply',
                'chat_status_replied' => 'Staff has replied',
                'chat_draft_title' => 'Unsaved message',
                'chat_draft_body' => 'This message has not been sent. Keep it as a draft?',
                'chat_draft_keep' => 'Keep typing',
                'chat_draft_discard' => 'Discard draft',
                'thinking' => 'Thinking…',
                'chat_error' => "I'm having trouble responding right now. Please try again or contact the events team.", 'chat_slow' => 'You are sending messages very quickly. Please wait a moment and try again.', 'chat_retry' => 'Retry',
                'view_details' => 'View space',
                'space_details_question' => 'Show me more details and photos of :space',
                'book_now' => 'Request this space',
                'menu_heading' => 'Start here',
                'menu_spaces' => 'Find a space',
                'menu_spaces_q' => 'I would like to see the event spaces and their capacity.',
                'menu_facilities' => 'Facilities & services',
                'menu_facilities_q' => 'What facilities and catering options does the venue offer?',
                'menu_policies' => 'House rules & FAQ',
                'menu_policies_q' => 'What are the load-in, curfew, house rules and cancellation policies?',
                'menu_staff' => 'Talk to the team',
                'menu_staff_q' => 'I would like to speak with the events team.',
            ],
            'ja' => [
                'from' => '',
                'per_day' => '〜 / 1日',
                'ask_ai' => 'AIイベントプランナーに聞く',
                'spaces_heading' => 'イベントスペース',
                'chat_heading' => 'AIイベントプランナー',
                'chat_subtitle' => '会場・収容人数・ケータリングについて24時間いつでもどうぞ。',
                'chat_placeholder' => 'ホール、日程、ハウスルールについて質問…',
                'chat_send' => '送信',
                'chat_open' => 'AIイベントプランナーに相談',
                'chat_close' => '会話を閉じる',
                'chat_intro' => 'こんにちは。この会場のAIイベントプランナーです。日程、人数、イベントの内容をお聞かせください。会場、ケータリング、ハウスルールのご質問もどうぞ。',
                'av_included' => '音響・照明付き',
                'max_guests' => '名まで',
                'chat_av_extra' => '音響・照明はオプション',
                'chat_days' => '日間',
                'chat_discount' => 'イベント割引',
                'chat_no_availability' => 'ご希望の日程にはご利用いただけません。',
                'chat_booking_received' => 'イベントのご依頼を受け付けました',
                'chat_reference' => '受付番号',
                'handed_over' => 'イベントチームがこの会話に参加しました。まもなく返信いたします。',
                'chat_status_sent' => 'メッセージを送信しました',
                'chat_status_waiting' => 'スタッフの返信を待っています',
                'chat_status_replied' => 'スタッフが返信しました',
                'chat_draft_title' => '未送信のメッセージ',
                'chat_draft_body' => 'このメッセージはまだ送信されていません。下書きとして残しますか？',
                'chat_draft_keep' => '入力を続ける',
                'chat_draft_discard' => '下書きを破棄',
                'thinking' => '入力中…',
                'chat_error' => '現在うまくお答えできません。もう一度お試しいただくか、イベントチームにご連絡ください。', 'chat_slow' => 'メッセージが多すぎます。少し待ってからもう一度お試しください。', 'chat_retry' => '再試行',
                'view_details' => '会場を見る',
                'space_details_question' => ':space の詳細と写真を見せてください',
                'book_now' => 'この会場をリクエスト',
                'menu_heading' => 'ここから始める',
                'menu_spaces' => '会場を探す',
                'menu_spaces_q' => 'イベントスペースと収容人数を見せてください。',
                'menu_facilities' => '施設・サービス',
                'menu_facilities_q' => '会場の施設とケータリングについて教えてください。',
                'menu_policies' => 'ハウスルール・FAQ',
                'menu_policies_q' => '搬入、終了時刻、ハウスルール、キャンセルのポリシーを教えてください。',
                'menu_staff' => 'スタッフに相談',
                'menu_staff_q' => 'イベントチームと話したいです。',
            ],
            default => [
                'from' => 'mulai dari',
                'per_day' => '/ hari acara',
                'ask_ai' => 'Tanya AI Event Planner',
                'spaces_heading' => 'Ruang Acara',
                'chat_heading' => 'AI Event Planner',
                'chat_subtitle' => 'Tanyakan ruang, kapasitas, atau katering — siaga 24 jam.',
                'chat_placeholder' => 'Tanya soal ruang, tanggal, atau aturan venue…',
                'chat_send' => 'Kirim',
                'chat_open' => 'Ngobrol dengan AI Event Planner',
                'chat_close' => 'Tutup percakapan',
                'chat_intro' => 'Halo! Saya AI Event Planner venue ini. Ceritakan acara Anda — tanggal, jumlah tamu, dan jenis acaranya — atau tanyakan soal ruang, katering, dan aturan venue.',
                'av_included' => 'Termasuk sound & lighting',
                'max_guests' => 'tamu',
                'chat_av_extra' => 'AV opsional',
                'chat_days' => 'hari',
                'chat_discount' => 'diskon acara',
                'chat_no_availability' => 'Tidak tersedia pada tanggal tersebut.',
                'chat_booking_received' => 'Permintaan acara diterima',
                'chat_reference' => 'Ref',
                'handed_over' => 'Tim event telah bergabung dalam percakapan ini dan akan segera membalas.',
                'chat_status_sent' => 'Pesan terkirim',
                'chat_status_waiting' => 'Menunggu balasan staf',
                'chat_status_replied' => 'Staf telah membalas',
                'chat_draft_title' => 'Pesan belum dikirim',
                'chat_draft_body' => 'Pesan ini belum dikirim. Simpan sebagai draft?',
                'chat_draft_keep' => 'Lanjut mengetik',
                'chat_draft_discard' => 'Buang draft',
                'thinking' => 'Sedang mengetik…',
                'chat_error' => 'Saya sedang kesulitan menjawab. Silakan coba lagi atau hubungi tim event.', 'chat_slow' => 'Pesan terlalu cepat. Mohon tunggu sebentar lalu coba lagi.', 'chat_retry' => 'Coba lagi',
                'view_details' => 'Lihat ruang',
                'space_details_question' => 'Tunjukkan detail dan foto lengkap :space',
                'book_now' => 'Ajukan ruang ini',
                'menu_heading' => 'Mulai dari sini',
                'menu_spaces' => 'Cari ruang',
                'menu_spaces_q' => 'Saya ingin melihat ruang acara dan kapasitasnya.',
                'menu_facilities' => 'Fasilitas & layanan',
                'menu_facilities_q' => 'Fasilitas dan opsi katering apa saja yang tersedia di venue ini?',
                'menu_policies' => 'Aturan & FAQ',
                'menu_policies_q' => 'Apa kebijakan load-in, jam malam, aturan venue, dan pembatalan?',
                'menu_staff' => 'Bicara dengan tim',
                'menu_staff_q' => 'Saya ingin bicara dengan tim event.',
            ],
        };
    }
}
