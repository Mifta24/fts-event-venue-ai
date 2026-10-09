<?php

namespace Database\Seeders;

use App\Models\Space;
use App\Models\SpaceInventory;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueKnowledgeItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoVenueSeeder extends Seeder
{
    /** Events are booked months ahead, so the demo opens about half a year of dates. */
    private const INVENTORY_DAYS = 180;

    private const VENUE_NAME = 'Aurelia Event Hall';

    public function run(): void
    {
        $owner = User::firstOrCreate(
            ['email' => 'owner@ftsvenue.test'],
            [
                'name' => 'Owner',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $description = [
            'id' => 'Venue acara seluas 1,2 hektare di kawasan Senayan, Jakarta Pusat, dengan ballroom tanpa pilar, paviliun kaca, taman, rooftop, dan ruang rapat. Tim event in-house mendampingi pernikahan, gala dinner, konferensi, dan acara perusahaan dari rapat 20 orang sampai perayaan 1.000 tamu.',
            'en' => 'A 1.2-hectare event venue in Senayan, Central Jakarta, with a pillar-free ballroom, a glass pavilion, a garden terrace, a rooftop and meeting rooms. An in-house events team looks after weddings, gala dinners, conferences and corporate events, from a 20-person meeting to a 1,000-guest celebration.',
            'ja' => 'ジャカルタ中心部スナヤンにある1.2ヘクタールのイベント会場。無柱のボールルーム、ガラスのパビリオン、ガーデンテラス、ルーフトップ、会議室を備えています。専任のイベントチームが、20名の会議から1,000名の祝宴まで、結婚式、ガラディナー、カンファレンス、企業イベントをサポートします。',
        ];

        $venue = Venue::updateOrCreate(
            ['name' => self::VENUE_NAME],
            [
                'slug' => Venue::where('name', self::VENUE_NAME)->value('slug') ?? Venue::generateUniqueSlug(self::VENUE_NAME),
                'description' => $description['id'],
                'translations' => collect($description)->map(fn (string $text) => ['description' => $text])->all(),
                'address' => 'Jl. Asia Afrika No. 18, Gelora',
                'city' => 'Jakarta Pusat',
                'country' => 'Indonesia',
                'latitude' => -6.2185,
                'longitude' => 106.8036,
                'phone' => '+62 21 5098 7700',
                'whatsapp' => '6281234567890',
                'email' => 'events@ftsvenue.test',
                'timezone' => 'Asia/Jakarta',
                'currency' => 'IDR',
                'default_locale' => 'id',
                'load_in_time' => '07:00',
                'curfew_time' => '23:00',
                'weekday_discount_percent' => 15,
                'multiday_discount_percent' => 10,
                'cover_path' => 'https://images.unsplash.com/photo-1549895058-36748fa6c6a7?auto=format&fit=crop&w=1920&q=80',
                'public_status' => 'published',
            ]
        );

        $venue->users()->syncWithoutDetaching([
            $owner->id => ['role' => 'owner', 'status' => 'active'],
        ]);

        $sort = 0;
        foreach ($this->spaceDefinitions() as $definition) {
            $images = $definition['images'];
            $totalRooms = $definition['total_units'];
            unset($definition['images'], $definition['total_units']);

            $space = $venue->spaces()->updateOrCreate(
                ['slug' => $definition['slug']],
                [...$definition, 'sort_order' => $sort++, 'is_active' => true]
            );

            $space->images()->delete();
            foreach ($images as $imageSort => $image) {
                $space->images()->create([
                    'image_url' => $image['url'],
                    'tags' => $image['tags'],
                    'alt_text' => $image['alt'],
                    'sort_order' => $imageSort,
                ]);
            }

            $this->seedInventory($space, $totalRooms);
        }

        $this->seedKnowledgeBase($venue);

        $this->command?->info('Demo login: owner@ftsvenue.test — password: password');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function spaceDefinitions(): array
    {
        $photo = fn (string $id) => "https://images.unsplash.com/photo-{$id}?auto=format&fit=crop&w=1200&q=80";

        return [
            [
                'slug' => 'grand-ballroom',
                'name' => 'Grand Ballroom',
                'description' => 'Ballroom tanpa pilar dengan tiga chandelier kristal, panggung modular, dan lantai dansa. Pilihan utama untuk pernikahan besar dan gala dinner.',
                'translations' => [
                    'id' => ['name' => 'Grand Ballroom', 'description' => 'Ballroom tanpa pilar dengan tiga chandelier kristal, panggung modular, dan lantai dansa. Pilihan utama untuk pernikahan besar dan gala dinner.'],
                    'en' => ['name' => 'Grand Ballroom', 'description' => 'A pillar-free ballroom with three crystal chandeliers, a modular stage and a dance floor. The first choice for large weddings and gala dinners.'],
                    'ja' => ['name' => 'グランドボールルーム', 'description' => '3基のクリスタルシャンデリア、モジュール式ステージ、ダンスフロアを備えた無柱のボールルーム。大規模な結婚式やガラディナーに最適です。'],
                ],
                'space_type' => 'indoor',
                'size_sqm' => 900,
                'ceiling_height_m' => 8.0,
                'level_label' => 'Level 2',
                'layouts' => ['banquet' => 500, 'theatre' => 800, 'classroom' => 450, 'cocktail' => 1000],
                'av_included' => true,
                'catering_available' => true,
                'catering_price' => 385000,
                'base_price' => 85000000,
                'amenities' => ['stage', 'led_wall', 'sound_system', 'lighting_rig', 'dance_floor', 'bridal_suite', 'red_carpet', 'air_conditioning', 'wifi', 'generator'],
                'total_units' => 1,
                'images' => [
                    ['url' => $photo('1690812304029-bce8cef581f6'), 'tags' => ['chandelier', 'hall'], 'alt' => 'Grand Ballroom dengan chandelier kristal'],
                    ['url' => $photo('1666617710768-425d2d9088f8'), 'tags' => ['banquet', 'wedding'], 'alt' => 'Grand Ballroom dengan susunan banquet pernikahan'],
                    ['url' => $photo('1759477274116-e3cb02d2b9d8'), 'tags' => ['banquet', 'empty'], 'alt' => 'Grand Ballroom kosong dengan meja bundar'],
                ],
            ],
            [
                'slug' => 'crystal-pavilion',
                'name' => 'Crystal Pavilion',
                'description' => 'Paviliun kaca semi-terbuka di tepi taman, terang alami di siang hari dan berkilau dengan lampu festoon di malam hari. Cocok untuk akad, resepsi intim, dan peluncuran produk.',
                'translations' => [
                    'id' => ['name' => 'Crystal Pavilion', 'description' => 'Paviliun kaca semi-terbuka di tepi taman, terang alami di siang hari dan berkilau dengan lampu festoon di malam hari. Cocok untuk akad, resepsi intim, dan peluncuran produk.'],
                    'en' => ['name' => 'Crystal Pavilion', 'description' => 'A semi-outdoor glass pavilion at the edge of the garden, bright with daylight by day and glittering with festoon lights at night. Made for ceremonies, intimate receptions and product launches.'],
                    'ja' => ['name' => 'クリスタルパビリオン', 'description' => '庭園に面した半屋外のガラスパビリオン。昼は自然光があふれ、夜はフェストゥーンライトが輝きます。挙式、少人数のレセプション、製品発表会に。'],
                ],
                'space_type' => 'semi_outdoor',
                'size_sqm' => 450,
                'ceiling_height_m' => 6.5,
                'level_label' => 'Ground level',
                'layouts' => ['banquet' => 250, 'theatre' => 400, 'classroom' => 220, 'cocktail' => 500],
                'av_included' => true,
                'catering_available' => true,
                'catering_price' => 365000,
                'base_price' => 45000000,
                'amenities' => ['fairy_lights', 'sound_system', 'air_conditioning', 'rain_cover', 'garden_view', 'chiavari_chairs', 'round_tables'],
                'total_units' => 1,
                'images' => [
                    ['url' => $photo('1695128680283-35cd46428673'), 'tags' => ['pavilion', 'night'], 'alt' => 'Crystal Pavilion bercahaya di malam hari'],
                    ['url' => $photo('1695128881922-16fcb9dddcc3'), 'tags' => ['reception', 'night'], 'alt' => 'Resepsi di Crystal Pavilion'],
                    ['url' => $photo('1527359443443-84a48aec73d2'), 'tags' => ['ceremony', 'entrance'], 'alt' => 'Gazebo dan tirai di Crystal Pavilion'],
                ],
            ],
            [
                'slug' => 'garden-terrace',
                'name' => 'Garden Terrace',
                'description' => 'Teras taman terbuka dengan pohon-pohon tua, jalur pengantin, dan kolam refleksi. Ideal untuk pemberkatan di luar ruangan dan garden party saat sore hari.',
                'translations' => [
                    'id' => ['name' => 'Garden Terrace', 'description' => 'Teras taman terbuka dengan pohon-pohon tua, jalur pengantin, dan kolam refleksi. Ideal untuk pemberkatan di luar ruangan dan garden party saat sore hari.'],
                    'en' => ['name' => 'Garden Terrace', 'description' => 'An open garden terrace with mature trees, an aisle path and a reflecting pool. Ideal for outdoor ceremonies and late-afternoon garden parties.'],
                    'ja' => ['name' => 'ガーデンテラス', 'description' => '古木、バージンロード、リフレクティングプールのある開放的なガーデンテラス。屋外での挙式や夕方のガーデンパーティーに最適です。'],
                ],
                'space_type' => 'outdoor',
                'size_sqm' => 600,
                'ceiling_height_m' => null,
                'level_label' => 'Ground level',
                'layouts' => ['banquet' => 300, 'theatre' => 450, 'cocktail' => 600],
                'av_included' => false,
                'catering_available' => true,
                'catering_price' => 345000,
                'base_price' => 38000000,
                'amenities' => ['garden_view', 'fairy_lights', 'chiavari_chairs', 'rain_cover', 'generator'],
                'total_units' => 1,
                'images' => [
                    ['url' => $photo('1523438885200-e635ba2c371e'), 'tags' => ['garden', 'gazebo'], 'alt' => 'Gazebo di Garden Terrace'],
                    ['url' => $photo('1607861884586-c7cfaed16290'), 'tags' => ['ceremony', 'aisle'], 'alt' => 'Jalur pengantin di Garden Terrace'],
                    ['url' => $photo('1670529776180-60e4132ab90c'), 'tags' => ['ceremony', 'decor'], 'alt' => 'Gerbang bunga dan kursi emas'],
                ],
            ],
            [
                'slug' => 'skyline-rooftop',
                'name' => 'Skyline Rooftop',
                'description' => 'Rooftop di lantai 12 dengan panorama kota, lounge, dan bar. Pas untuk cocktail party, pesta ulang tahun, dan jamuan perusahaan saat matahari terbenam.',
                'translations' => [
                    'id' => ['name' => 'Skyline Rooftop', 'description' => 'Rooftop di lantai 12 dengan panorama kota, lounge, dan bar. Pas untuk cocktail party, pesta ulang tahun, dan jamuan perusahaan saat matahari terbenam.'],
                    'en' => ['name' => 'Skyline Rooftop', 'description' => 'A 12th-floor rooftop with a city panorama, a lounge and a bar. Perfect for cocktail parties, birthdays and corporate receptions at sunset.'],
                    'ja' => ['name' => 'スカイラインルーフトップ', 'description' => '12階のルーフトップからは街のパノラマが一望できます。ラウンジとバー付き。カクテルパーティー、誕生日、夕暮れの企業レセプションに最適です。'],
                ],
                'space_type' => 'outdoor',
                'size_sqm' => 320,
                'ceiling_height_m' => null,
                'level_label' => 'Level 12',
                'layouts' => ['banquet' => 120, 'theatre' => 160, 'cocktail' => 250],
                'av_included' => true,
                'catering_available' => true,
                'catering_price' => 395000,
                'base_price' => 28000000,
                'amenities' => ['city_view', 'sound_system', 'lighting_rig', 'dance_floor', 'valet', 'security'],
                'total_units' => 1,
                'images' => [
                    ['url' => $photo('1752766074168-44afdbaaf390'), 'tags' => ['dinner', 'rooftop'], 'alt' => 'Meja makan malam di Skyline Rooftop'],
                    ['url' => $photo('1758272133693-d2124dbe00de'), 'tags' => ['party', 'view'], 'alt' => 'Pesta di Skyline Rooftop dengan pemandangan kota'],
                    ['url' => $photo('1752766074098-07192c3b60ba'), 'tags' => ['setup', 'welcome'], 'alt' => 'Penataan meja di Skyline Rooftop'],
                ],
            ],
            [
                'slug' => 'executive-suite',
                'name' => 'Executive Meeting Suite',
                'description' => 'Dua ruang rapat identik dengan layar interaktif, video conference, dan akses lounge. Untuk rapat direksi, pelatihan, dan workshop.',
                'translations' => [
                    'id' => ['name' => 'Executive Meeting Suite', 'description' => 'Dua ruang rapat identik dengan layar interaktif, video conference, dan akses lounge. Untuk rapat direksi, pelatihan, dan workshop.'],
                    'en' => ['name' => 'Executive Meeting Suite', 'description' => 'Two identical meeting rooms with interactive screens, video conferencing and lounge access. For board meetings, training sessions and workshops.'],
                    'ja' => ['name' => 'エグゼクティブミーティングスイート', 'description' => 'インタラクティブスクリーン、ビデオ会議設備、ラウンジを備えた同じ仕様の会議室が2室。役員会議、研修、ワークショップに。'],
                ],
                'space_type' => 'indoor',
                'size_sqm' => 80,
                'ceiling_height_m' => 3.2,
                'level_label' => 'Level 3',
                'layouts' => ['boardroom' => 24, 'classroom' => 40, 'theatre' => 60, 'cocktail' => 70],
                'av_included' => true,
                'catering_available' => true,
                'catering_price' => 215000,
                'base_price' => 7500000,
                'amenities' => ['video_conference', 'projector', 'whiteboard', 'wifi', 'air_conditioning'],
                'total_units' => 2,
                'images' => [
                    ['url' => $photo('1431540015161-0bf868a2d407'), 'tags' => ['boardroom'], 'alt' => 'Ruang rapat dengan meja oval'],
                    ['url' => $photo('1517502884422-41eaead166d4'), 'tags' => ['boardroom', 'table'], 'alt' => 'Meja rapat kayu'],
                    ['url' => $photo('1573167507387-6b4b98cb7c13'), 'tags' => ['meeting'], 'alt' => 'Rapat di Executive Meeting Suite'],
                ],
            ],
        ];
    }

    /**
     * Opens inventory with weekend rates a fifth higher, and books a
     * deterministic share of days so the demo calendar shows real gaps.
     */
    private function seedInventory(Space $space, int $totalRooms): void
    {
        $start = now()->startOfDay();

        for ($i = 0; $i < self::INVENTORY_DAYS; $i++) {
            $date = $start->copy()->addDays($i);
            $weekend = $date->isFriday() || $date->isSaturday() || $date->isSunday();

            $price = $weekend ? (float) $space->base_price * 1.2 : (float) $space->base_price;

            $fullyBooked = $weekend
                ? ($i + $space->id) % 4 === 0
                : ($i * 5 + $space->id * 3) % 13 === 0;

            // Match on the same value the date cast stores, so re-seeding
            // updates the existing day instead of colliding with it.
            SpaceInventory::updateOrCreate(
                ['space_id' => $space->id, 'event_date' => $date],
                [
                    'total_units' => $totalRooms,
                    'booked_units' => $fullyBooked ? $totalRooms : 0,
                    'price' => round($price, -3),
                ]
            );
        }
    }

    private function seedKnowledgeBase(Venue $venue): void
    {
        $photo = fn (string $id) => "https://images.unsplash.com/photo-{$id}?auto=format&fit=crop&w=800&q=80";

        $items = [
            [
                'category' => VenueKnowledgeItem::CATEGORY_GENERAL,
                'title' => 'Tentang Aurelia Event Hall',
                'body' => 'Aurelia Event Hall adalah venue acara di Senayan, Jakarta Pusat, dengan lima ruang: Grand Ballroom (hingga 1.000 tamu), Crystal Pavilion, Garden Terrace, Skyline Rooftop, dan Executive Meeting Suite. Tim event in-house kami mendampingi Anda dari kunjungan lokasi sampai pembongkaran akhir.',
                'translations' => [
                    'en' => ['title' => 'About Aurelia Event Hall', 'body' => 'Aurelia Event Hall is an event venue in Senayan, Central Jakarta, with five spaces: the Grand Ballroom (up to 1,000 guests), the Crystal Pavilion, the Garden Terrace, the Skyline Rooftop and the Executive Meeting Suite. Our in-house events team supports you from the site visit to the final load-out.'],
                    'ja' => ['title' => 'アウレリア イベントホールについて', 'body' => 'アウレリア イベントホールは、ジャカルタ中心部スナヤンのイベント会場です。グランドボールルーム（最大1,000名）、クリスタルパビリオン、ガーデンテラス、スカイラインルーフトップ、エグゼクティブミーティングスイートの5つのスペースがあります。専任のイベントチームが下見から撤収まで伴走します。'],
                ],
                'tags' => ['overview', 'introduction', 'event venue'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_POLICIES,
                'title' => 'Load-in, jam acara, dan jam malam',
                'body' => 'Load-in dan dekorasi dimulai pukul 07:00 pada hari acara; untuk dekorasi besar, load-in sehari sebelumnya bisa dipesan dengan tarif 30% dari harga sewa. Setiap acara harus selesai pukul 23:00 dan pembongkaran paling lambat pukul 01:00. Musik live dan sound system di ruang terbuka dihentikan pukul 22:00.',
                'translations' => [
                    'en' => ['title' => 'Load-in, event hours and curfew', 'body' => 'Load-in and decoration start at 7:00 AM on the event day; for large decoration, load-in the day before can be booked at 30% of the rental rate. Every event must finish by 11:00 PM and load-out by 1:00 AM at the latest. Live music and sound systems in the open-air spaces stop at 10:00 PM.'],
                    'ja' => ['title' => '搬入・開催時間・終了時刻', 'body' => '搬入と装飾は当日の7:00から可能です。大規模な装飾の場合は、前日搬入を会場料金の30%でご予約いただけます。イベントは23:00まで、撤収は遅くとも深夜1:00までに終了してください。屋外スペースでの生演奏と音響は22:00に停止します。'],
                ],
                'tags' => ['load-in', 'curfew', 'jam malam', 'decoration', 'dekorasi', 'load-out', 'hours'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_POLICIES,
                'title' => 'Uang muka, pembayaran, dan pembatalan',
                'body' => 'Tanggal ditahan setelah uang muka 30% dibayarkan dalam 7 hari sejak konfirmasi tim event; pelunasan paling lambat 14 hari sebelum acara. Pembatalan lebih dari 90 hari sebelum acara dikembalikan 70% dari uang muka; 30–90 hari sebelum acara uang muka tidak dikembalikan tetapi bisa dijadwalkan ulang satu kali dalam 12 bulan.',
                'translations' => [
                    'en' => ['title' => 'Deposit, payment and cancellation', 'body' => 'Your date is held once a 30% deposit is paid within 7 days of the events team confirming; the balance is due 14 days before the event. Cancellations more than 90 days before the event get 70% of the deposit back; between 30 and 90 days the deposit is not refunded but can be rescheduled once within 12 months.'],
                    'ja' => ['title' => '手付金・お支払い・キャンセル', 'body' => 'イベントチームの確認から7日以内に30%の手付金をお支払いいただくと、日程が確保されます。残金はイベントの14日前までにお支払いください。90日より前のキャンセルは手付金の70%を返金、30〜90日前は返金不可ですが、12か月以内に1回に限り日程変更が可能です。'],
                ],
                'tags' => ['deposit', 'uang muka', 'payment', 'pembayaran', 'cancellation', 'pembatalan', 'refund'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_POLICIES,
                'title' => 'Dekorasi, vendor luar, dan aturan suara',
                'body' => 'Dekorator, florist, dan fotografer luar boleh bekerja di venue dengan menyerahkan daftar vendor 7 hari sebelumnya dan asuransi tanggung gugat. Dilarang memaku atau menempel pada dinding dan chandelier; gunakan rangka yang disediakan tim kami. Konfeti, kembang api dalam ruangan, dan api terbuka tidak diperbolehkan.',
                'translations' => [
                    'en' => ['title' => 'Decoration, outside vendors and noise rules', 'body' => 'Outside decorators, florists and photographers are welcome to work at the venue with a vendor list sent 7 days ahead and liability insurance. Nails and adhesives on walls and chandeliers are not allowed; use the rigging provided by our team. Confetti, indoor fireworks and open flames are not permitted.'],
                    'ja' => ['title' => '装飾・外部業者・音量ルール', 'body' => '外部の装飾業者、フローリスト、カメラマンは、7日前までの業者リストと賠償責任保険のご提出で入場できます。壁やシャンデリアへの釘打ち・接着は禁止で、当会場のリギングをご利用ください。紙吹雪、屋内の花火、裸火は使用できません。'],
                ],
                'tags' => ['decoration', 'dekorasi', 'vendor', 'florist', 'photographer', 'fotografer', 'outside', 'confetti', 'noise'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_FACILITIES,
                'title' => 'Panggung, sound, dan lighting',
                'image_url' => $photo('1576514129883-2f1d47a65da6'),
                'body' => 'Grand Ballroom dilengkapi panggung modular 12 × 6 m, video wall LED, sound system line-array, dan rig pencahayaan dengan operator teknis. Crystal Pavilion, Skyline Rooftop, dan Executive Meeting Suite sudah termasuk sound system dasar; Garden Terrace dapat disewa dengan paket sound tambahan.',
                'translations' => [
                    'en' => ['title' => 'Stage, sound and lighting', 'body' => 'The Grand Ballroom comes with a 12 × 6 m modular stage, an LED video wall, a line-array sound system and a lighting rig with a technical operator. The Crystal Pavilion, Skyline Rooftop and Executive Meeting Suite include a basic sound system; the Garden Terrace can add a sound package.'],
                    'ja' => ['title' => 'ステージ・音響・照明', 'body' => 'グランドボールルームには、12×6mのモジュール式ステージ、LEDビデオウォール、ラインアレイ音響、技術オペレーター付きの照明設備が備わっています。クリスタルパビリオン、スカイラインルーフトップ、エグゼクティブミーティングスイートには基本音響が含まれ、ガーデンテラスは音響パッケージを追加できます。'],
                ],
                'tags' => ['stage', 'panggung', 'sound', 'audio', 'lighting', 'led', 'av'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_FACILITIES,
                'title' => 'Bridal suite dan green room',
                'image_url' => $photo('1741311178735-e65b6e9048b7'),
                'body' => 'Bridal suite 60 m² di lantai 2 dengan ruang rias, kamar mandi, dan sofa untuk 10 orang tersedia gratis pada hari pernikahan di Grand Ballroom atau Crystal Pavilion. Dua green room untuk pengisi acara dan VIP ada di dekat panggung dan bisa dipakai di semua acara.',
                'translations' => [
                    'en' => ['title' => 'Bridal suite and green rooms', 'body' => 'A 60 m² bridal suite on level 2, with a make-up area, bathroom and seating for 10, is free on the day of a wedding in the Grand Ballroom or the Crystal Pavilion. Two green rooms for performers and VIPs sit near the stage and can be used for any event.'],
                    'ja' => ['title' => 'ブライズルームと控室', 'body' => '2階の60m²のブライズルーム（メイク用スペース、バスルーム、10名分のソファ付き）は、グランドボールルームまたはクリスタルパビリオンでの結婚式当日は無料でご利用いただけます。出演者やVIP用の控室2室がステージ近くにあり、すべてのイベントでご利用いただけます。'],
                ],
                'tags' => ['bridal', 'pengantin', 'green room', 'vip', 'dressing'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_FACILITIES,
                'title' => 'Listrik, genset, dan Wi-Fi',
                'image_url' => $photo('1471877325906-aee7c2240b5f'),
                'body' => 'Seluruh venue didukung genset cadangan sehingga acara tidak terhenti saat listrik padam. Daya tambahan untuk pameran atau panggung besar bisa dipesan dengan tarif per kVA. Wi-Fi fiber 1 Gbps tersedia gratis di semua ruang, dan jaringan khusus acara bisa disiapkan oleh tim teknis.',
                'translations' => [
                    'en' => ['title' => 'Power, generator and Wi-Fi', 'body' => 'The whole venue is backed by a standby generator so an event never stops in a blackout. Extra power for exhibitions or large stages can be ordered per kVA. 1 Gbps fibre Wi-Fi is free in every space, and a dedicated event network can be set up by our technical team.'],
                    'ja' => ['title' => '電源・発電機・Wi-Fi', 'body' => '会場全体が非常用発電機でバックアップされており、停電でもイベントが止まりません。展示会や大型ステージ向けの追加電源はkVA単位でご注文いただけます。1Gbpsの光Wi-Fiは全スペースで無料、イベント専用ネットワークも技術チームが構築できます。'],
                ],
                'tags' => ['power', 'listrik', 'generator', 'genset', 'wifi', 'wi-fi', 'internet'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_CATERING,
                'title' => 'Katering in-house',
                'image_url' => $photo('1555244162-803834f70033'),
                'body' => 'Dapur katering kami menyajikan menu prasmanan dan plated dengan halal certified. Harga katering dihitung per tamu per hari acara dan sudah ada di penawaran Anda; menu tasting untuk 4 orang gratis bagi acara di atas 150 tamu. Minuman beralkohol disajikan hanya oleh bartender kami di area yang berizin.',
                'translations' => [
                    'en' => ['title' => 'In-house catering', 'body' => 'Our halal-certified catering kitchen serves buffet and plated menus. Catering is priced per guest per event day and is already part of your quote; a tasting for 4 is free for events above 150 guests. Alcoholic drinks are served only by our bartenders in licensed areas.'],
                    'ja' => ['title' => '館内ケータリング', 'body' => 'ハラール認証のケータリングキッチンで、ビュッフェとプレートのメニューをご提供します。料金は1名・1日あたりで、見積もりに含まれます。150名を超えるイベントでは4名分の試食が無料です。アルコール類は、許可されたエリアで当店のバーテンダーのみがご提供します。'],
                ],
                'tags' => ['catering', 'katering', 'menu', 'halal', 'buffet', 'prasmanan', 'alcohol', 'tasting'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_CATERING,
                'title' => 'Katering luar dan corkage',
                'image_url' => $photo('1519167758481-83f550bb49b3'),
                'body' => 'Katering luar diperbolehkan hanya di Garden Terrace dan Executive Meeting Suite dengan biaya fasilitas dapur sebesar Rp15.000.000 per hari. Untuk ruang lain wajib memakai katering in-house. Kue pengantin, kue ulang tahun, dan minuman dari luar dikenai corkage Rp150.000 per botol atau Rp25.000 per slice.',
                'translations' => [
                    'en' => ['title' => 'Outside caterers and corkage', 'body' => 'Outside caterers are allowed only in the Garden Terrace and the Executive Meeting Suite, with a kitchen facility fee of IDR 15,000,000 per day. Other spaces must use in-house catering. Outside wedding or birthday cakes and drinks carry a corkage fee of IDR 150,000 per bottle or IDR 25,000 per slice.'],
                    'ja' => ['title' => '外部ケータリングと持ち込み料', 'body' => '外部ケータリングは、ガーデンテラスとエグゼクティブミーティングスイートのみ、キッチン使用料1日1,500万ルピアで可能です。その他のスペースは館内ケータリングのご利用が必須です。ウェディングケーキやバースデーケーキ、飲料の持ち込みは、1本15万ルピアまたは1カット2.5万ルピアの持ち込み料がかかります。'],
                ],
                'tags' => ['outside caterer', 'katering luar', 'corkage', 'cake', 'kue', 'external'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_TRANSPORT,
                'title' => 'Parkir dan valet',
                'image_url' => $photo('1750810908078-a4729905bf4b'),
                'body' => 'Basement menampung 420 mobil dan area valet menangani kedatangan di drop-off utama. Acara di atas 300 tamu mendapat 150 slot parkir gratis dan petugas lalu lintas; slot tambahan Rp25.000 per mobil. Bus rombongan bisa menurunkan tamu di drop-off dan parkir di lahan sebelah.',
                'translations' => [
                    'en' => ['title' => 'Parking and valet', 'body' => 'The basement holds 420 cars and valet staff handle arrivals at the main drop-off. Events above 300 guests get 150 free parking slots and traffic marshals; extra slots are IDR 25,000 per car. Coach groups can drop guests at the drop-off and park on the adjoining lot.'],
                    'ja' => ['title' => '駐車場とバレーパーキング', 'body' => '地下駐車場は420台収容で、正面の車寄せではバレースタッフがご案内します。300名を超えるイベントでは150台分の駐車が無料で、交通整理スタッフも配置されます。追加は1台2.5万ルピアです。団体バスは車寄せで降車後、隣接の駐車場をご利用いただけます。'],
                ],
                'tags' => ['parking', 'parkir', 'valet', 'car', 'bus', 'drop-off'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_TRANSPORT,
                'title' => 'MRT, LRT, dan hotel terdekat',
                'image_url' => $photo('1587825140708-dfaf72ae4b04'),
                'body' => 'Stasiun MRT Istora Mandiri berjarak 6 menit jalan kaki dan Stasiun LRT Senayan 9 menit; Bandara Soekarno-Hatta sekitar 50 menit berkendara. Tiga hotel bintang 4–5 dalam radius 800 m menawarkan tarif blok untuk tamu acara, dan kami menyediakan shuttle gratis untuk acara di atas 200 tamu.',
                'translations' => [
                    'en' => ['title' => 'MRT, LRT and nearby hotels', 'body' => 'Istora Mandiri MRT station is a 6-minute walk and Senayan LRT station 9 minutes; Soekarno-Hatta Airport is about a 50-minute drive. Three 4–5 star hotels within 800 m offer block rates for event guests, and we provide a free shuttle for events above 200 guests.'],
                    'ja' => ['title' => 'MRT・LRTと周辺ホテル', 'body' => 'MRTイストラ・マンディリ駅まで徒歩6分、LRTスナヤン駅まで9分、スカルノ・ハッタ空港までは車で約50分です。800m圏内の4〜5つ星ホテル3軒がイベントゲスト向けのブロック料金をご用意しており、200名を超えるイベントでは無料シャトルを運行します。'],
                ],
                'tags' => ['mrt', 'lrt', 'train', 'transit', 'hotel', 'airport', 'bandara', 'shuttle', 'accommodation'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_FAQ,
                'title' => 'Bagaimana jika hujan di acara outdoor?',
                'body' => 'Garden Terrace dan Skyline Rooftop menyediakan tenda cadangan dan pelindung hujan yang sudah termasuk dalam sewa. Keputusan memindahkan acara ke Crystal Pavilion atau Grand Ballroom dibuat 48 jam sebelumnya bersama tim event, selama ruang tersebut masih kosong pada tanggal yang sama.',
                'translations' => [
                    'en' => ['title' => 'What if it rains at an outdoor event?', 'body' => 'The Garden Terrace and Skyline Rooftop include a backup tent and rain cover in the rental. The decision to move to the Crystal Pavilion or Grand Ballroom is made 48 hours ahead with the events team, as long as that space is still free on the same date.'],
                    'ja' => ['title' => '屋外イベントが雨の場合は？', 'body' => 'ガーデンテラスとスカイラインルーフトップには、予備のテントと雨よけが利用料金に含まれています。クリスタルパビリオンまたはグランドボールルームへの変更は、同じ日に空きがある場合に限り、48時間前にイベントチームと決定します。'],
                ],
                'tags' => ['rain', 'hujan', 'outdoor', 'weather', 'backup', 'tent'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_FAQ,
                'title' => 'Apakah bisa survei lokasi?',
                'body' => 'Survei lokasi gratis setiap hari pukul 10:00–17:00 dengan janji temu minimal 24 jam sebelumnya lewat tim event. Pada survei Anda bisa melihat semua ruang, mencicipi menu, dan membahas susunan ruang. Untuk acara di atas 300 tamu, kami sarankan survei kedua bersama dekorator Anda.',
                'translations' => [
                    'en' => ['title' => 'Can we visit the venue first?', 'body' => 'Site visits are free every day from 10:00 AM to 5:00 PM, by appointment at least 24 hours ahead through the events team. On a visit you can see every space, taste the menu and talk through your layout. For events above 300 guests we suggest a second visit with your decorator.'],
                    'ja' => ['title' => '事前に下見はできますか？', 'body' => '下見は毎日10:00〜17:00に無料で承ります。24時間前までにイベントチームへご予約ください。すべてのスペースのご見学、メニューの試食、レイアウトのご相談が可能です。300名を超えるイベントでは、装飾業者さまとの2回目の下見をおすすめします。'],
                ],
                'tags' => ['site visit', 'survei', 'tour', 'visit', 'tasting', 'appointment'],
            ],
        ];

        $sort = 0;
        foreach ($items as $item) {
            $venue->knowledgeItems()->updateOrCreate(
                ['title' => $item['title']],
                [
                    'category' => $item['category'],
                    'body' => $item['body'],
                    'translations' => $item['translations'],
                    'tags' => $item['tags'],
                    'image_url' => $item['image_url'] ?? null,
                    'is_active' => true,
                    'sort_order' => $sort++,
                ]
            );
        }
    }
}
