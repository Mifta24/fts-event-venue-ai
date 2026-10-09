<?php

namespace Tests\Feature;

use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class VenueFoyerTest extends TestCase
{
    use RefreshDatabase;

    public function test_opening_screen_links_to_the_only_published_venue(): void
    {
        Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);
        Venue::create(['name' => 'Draft', 'slug' => 'draft']);

        $this->get('/?lang=en')
            ->assertOk()
            ->assertSee('FTS Event Venue AI')
            ->assertSee('Enter Demo')
            ->assertSee('href="'.route('venue.spaces', ['venueSlug' => 'demo', 'lang' => 'en']).'"', false)
            ->assertSee('href="'.route('venue.facilities', ['venueSlug' => 'demo', 'lang' => 'en']).'"', false)
            ->assertSee('href="'.route('venue.reservation', ['venueSlug' => 'demo', 'lang' => 'en']).'"', false)
            ->assertSee('href="'.route('venue.staff', ['venueSlug' => 'demo', 'lang' => 'en']).'"', false)
            ->assertDontSee('Draft');
    }

    public function test_opening_screen_lists_every_published_venue_when_there_are_several(): void
    {
        Venue::create(['name' => 'First Venue', 'slug' => 'first', 'public_status' => 'published']);
        Venue::create(['name' => 'Second Venue', 'slug' => 'second', 'public_status' => 'published']);
        Venue::create(['name' => 'Hidden Venue', 'slug' => 'hidden']);

        $this->get('/')->assertOk()->assertSee('First Venue')->assertSee('Second Venue')->assertDontSee('Hidden Venue');
    }

    public function test_opening_screen_explains_when_no_venue_is_published(): void
    {
        $this->get('/?lang=en')->assertOk()->assertSee('The virtual foyer is being prepared');
    }

    public function test_chat_has_separate_open_and_close_controls_without_submitting_the_composer(): void
    {
        Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);

        $this->get('/demo?lang=en')
            ->assertOk()
            ->assertSee('data-chat-launcher', false)
            ->assertSee('data-chat-composer', false)
            ->assertSee('type="button" data-chat-close', false)
            ->assertSee('aria-controls="planner-chat-log"', false);
    }

    public function test_chat_exposes_consistent_status_draft_and_loading_copy_for_each_locale(): void
    {
        Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);

        $this->get('/demo?lang=en')
            ->assertOk()
            ->assertSee('data-label-status-sent="Message sent"', false)
            ->assertSee('data-label-status-waiting="Waiting for staff reply"', false)
            ->assertSee('data-label-status-replied="Staff has replied"', false)
            ->assertSee('data-label-draft-title="Unsaved message"', false)
            ->assertSee('data-label-draft-keep="Keep typing"', false)
            ->assertSee('data-label-draft-discard="Discard draft"', false)
            ->assertSee('data-thinking-indicator', false)
            ->assertSee('data-chat-status', false)
            ->assertSee('data-draft-dialog', false);

        $this->get('/demo?lang=ja')
            ->assertOk()
            ->assertSee('data-label-status-sent="メッセージを送信しました"', false)
            ->assertSee('data-label-status-waiting="スタッフの返信を待っています"', false)
            ->assertSee('data-label-status-replied="スタッフが返信しました"', false)
            ->assertSee('data-label-draft-title="未送信のメッセージ"', false)
            ->assertSee('data-label-draft-keep="入力を続ける"', false)
            ->assertSee('data-label-draft-discard="下書きを破棄"', false);

        $this->get('/demo?lang=id')
            ->assertOk()
            ->assertSee('data-label-status-sent="Pesan terkirim"', false)
            ->assertSee('data-label-status-waiting="Menunggu balasan staf"', false)
            ->assertSee('data-label-status-replied="Staf telah membalas"', false)
            ->assertSee('data-label-draft-title="Pesan belum dikirim"', false)
            ->assertSee('data-label-draft-keep="Lanjut mengetik"', false)
            ->assertSee('data-label-draft-discard="Buang draft"', false);
    }

    public function test_the_facilities_page_lists_only_active_facilities_of_the_current_venue(): void
    {
        $venue = Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);
        $venue->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Garden pool', 'body' => 'Open until 8pm.', 'is_active' => true, 'image_url' => 'https://example.test/pool.jpg']);
        $venue->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Hidden spa', 'body' => 'Private.', 'is_active' => false]);
        $other = Venue::create(['name' => 'Other', 'slug' => 'other', 'public_status' => 'published']);
        $other->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Other pool', 'body' => 'Other venue.', 'is_active' => true]);

        $this->get('/demo/facilities')
            ->assertOk()
            ->assertSee('Garden pool')
            ->assertDontSee('Hidden spa')
            ->assertDontSee('Other pool')
            // photo cards replace the menu card, which would cover the right-hand panel
            ->assertSee('class="facility-card"', false)
            ->assertSee('https://example.test/pool.jpg', false)
            ->assertSee('class="foyer-content stage-panel-right', false)
            ->assertDontSee('class="foyer-navigation"', false);
        $this->get('/demo?lang=en')->assertOk()->assertSee('Your event,')->assertSee('beautifully staged');
        $this->get('/demo?lang=ja')->assertOk()->assertSee('美しく演出します');
    }

    public function test_unpublished_foyer_is_not_accessible(): void
    {
        Venue::create(['name' => 'Draft', 'slug' => 'draft']);
        $this->get('/draft')->assertNotFound();
    }

    public function test_the_spaces_page_lists_only_active_spaces_of_the_current_venue(): void
    {
        $venue = Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR']);
        $first = $venue->spaces()->create([
            'name' => 'Grand Ballroom', 'slug' => 'grand-ballroom', 'base_price' => 85000000, 'space_type' => 'indoor', 'size_sqm' => 900,
            'layouts' => ['banquet' => 500, 'theatre' => 800], 'amenities' => ['stage', 'sound_system'], 'level_label' => 'Level 2', 'is_active' => true, 'sort_order' => 0,
        ]);
        $first->images()->create(['image_url' => 'https://example.test/one.jpg', 'alt_text' => 'Ballroom', 'sort_order' => 0]);
        $first->images()->create(['image_url' => 'https://example.test/two.jpg', 'alt_text' => 'Stage', 'sort_order' => 1]);
        $venue->spaces()->create(['name' => 'Garden Terrace', 'slug' => 'garden-terrace', 'base_price' => 38000000, 'space_type' => 'outdoor', 'layouts' => ['banquet' => 300], 'is_active' => true, 'sort_order' => 1]);
        $venue->spaces()->create(['name' => 'Retired Space', 'slug' => 'retired-space', 'base_price' => 1, 'layouts' => ['banquet' => 10], 'is_active' => false]);
        $other = Venue::create(['name' => 'Other', 'slug' => 'other', 'public_status' => 'published']);
        $other->spaces()->create(['name' => 'Foreign Space', 'slug' => 'foreign-space', 'base_price' => 1, 'layouts' => ['banquet' => 10], 'is_active' => true]);

        $this->get('/demo/spaces?lang=en')
            ->assertOk()
            ->assertSee('Grand Ballroom')
            ->assertSee('Garden Terrace')
            // the scene itself is the view now: the planner greets over the
            // backdrop and the index on the right carries the spaces
            ->assertDontSee('data-space-card', false)
            ->assertSee('narrator-on-stage', false)
            ->assertSee('class="space-nav-thumb"', false)
            ->assertSee('href="'.route('venue.space', ['venueSlug' => 'demo', 'spaceSlug' => 'grand-ballroom', 'lang' => 'en']).'"', false)
            ->assertDontSee('Retired Space')
            ->assertDontSee('Foreign Space');
    }

    public function test_a_space_page_shows_its_details_gallery_and_neighbouring_spaces(): void
    {
        $venue = Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR', 'weekday_discount_percent' => 15, 'multiday_discount_percent' => 10]);
        $first = $venue->spaces()->create([
            'name' => 'Grand Ballroom', 'slug' => 'grand-ballroom', 'base_price' => 85000000, 'space_type' => 'indoor', 'size_sqm' => 900, 'ceiling_height_m' => 8,
            'level_label' => 'Level 2', 'layouts' => ['banquet' => 500, 'theatre' => 800, 'cocktail' => 1000], 'av_included' => true, 'catering_available' => true, 'catering_price' => 385000,
            'amenities' => ['stage', 'bridal_suite'], 'is_active' => true, 'sort_order' => 0,
        ]);
        $first->images()->create(['image_url' => 'https://example.test/one.jpg', 'alt_text' => 'Ballroom', 'sort_order' => 0]);
        $first->images()->create(['image_url' => 'https://example.test/two.jpg', 'alt_text' => 'Stage', 'sort_order' => 1]);
        $venue->spaces()->create(['name' => 'Garden Terrace', 'slug' => 'garden-terrace', 'base_price' => 38000000, 'layouts' => ['banquet' => 300], 'is_active' => true, 'sort_order' => 1]);

        $terraceUrl = route('venue.space', ['venueSlug' => 'demo', 'spaceSlug' => 'garden-terrace', 'lang' => 'en']);

        $this->get('/demo/spaces/grand-ballroom?lang=en')
            ->assertOk()
            ->assertSee('Space 01 / 02')
            ->assertSee('Indoor hall')
            ->assertSee('1.000 guests')
            ->assertSee('900 m²')
            ->assertSee('8 m')
            ->assertSee('Level 2')
            ->assertSee('Stage')
            ->assertSee('Bridal suite')
            ->assertSee('Capacity by setup')
            ->assertSee('Cocktail (standing)')
            ->assertSee('Weekday events (Mon–Thu) 15% off · events of 3+ days 10% off')
            ->assertSee('https://example.test/two.jpg', false)
            ->assertSee('Final availability and rates are confirmed by the events team.')
            // both neighbours wrap around to the only other space
            ->assertSee('rel="prev"', false)
            ->assertSee($terraceUrl, false);

        $this->get('/demo/spaces/retired-or-unknown')->assertNotFound();
        $this->get('/demo/spaces/grand-ballroom?lang=id')->assertOk()->assertSee('Ruang indoor')->assertSee('Kapasitas per susunan');
    }

    public function test_spaces_pages_follow_the_venue_publication_and_space_state(): void
    {
        $venue = Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);
        $venue->spaces()->create(['name' => 'Hidden', 'slug' => 'hidden', 'base_price' => 1, 'layouts' => ['banquet' => 10], 'is_active' => false]);
        Venue::create(['name' => 'Draft', 'slug' => 'draft']);

        $this->get('/demo/spaces?lang=en')->assertOk()->assertSee('Space information will be available soon.');
        $this->get('/demo/spaces/hidden')->assertNotFound();
        $this->get('/draft/spaces')->assertNotFound();
    }

    public function test_the_foyer_walks_the_guest_to_the_spaces_page(): void
    {
        $venue = Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);
        $venue->spaces()->create(['name' => 'Grand Hall', 'slug' => 'grand-hall', 'base_price' => 9500000, 'layouts' => ['banquet' => 200], 'is_active' => true]);

        $this->get('/demo?lang=id')
            ->assertOk()
            ->assertSee('href="'.route('venue.spaces', ['venueSlug' => 'demo', 'lang' => 'id']).'"', false)
            ->assertSee('data-tour-line="Membuka tirai untuk ruang-ruang acara kami."', false)
            ->assertSee('href="'.route('venue.facilities', ['venueSlug' => 'demo', 'lang' => 'id']).'"', false)
            ->assertSee('data-tour-line="Menuju belakang panggung: fasilitas dan layanan."', false)
            ->assertDontSee('data-foyer-panel="spaces"', false)
            ->assertDontSee('data-foyer-panel="facilities"', false);

        $this->get('/demo/spaces?lang=id')
            ->assertOk()
            ->assertSee('data-tour-line="Kembali ke foyer."', false)
            ->assertSee('data-scene="spaces"', false)
            ->assertSee('data-cue="01"', false);
    }

    public function test_reservation_wizard_and_staff_channels_render_from_venue_data(): void
    {
        $venue = Venue::create([
            'name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'whatsapp' => '+62 812-0000-1111',
            'phone' => '+62 361 000', 'email' => 'front@demo.test',
        ]);
        $venue->spaces()->create(['name' => 'Grand Hall', 'slug' => 'grand-hall', 'base_price' => 9500000, 'layouts' => ['banquet' => 200], 'is_active' => true]);

        $this->get('/demo?lang=en')
            ->assertOk()
            ->assertSee('href="'.route('venue.reservation', ['venueSlug' => 'demo', 'lang' => 'en']).'"', false)
            ->assertSee('data-tour-line="Going to the planning table to shape your event."', false)
            ->assertSee('href="'.route('venue.staff', ['venueSlug' => 'demo', 'lang' => 'en']).'"', false)
            ->assertSee('data-tour-line="Going to meet the events team."', false);

        $this->get('/demo/reservation?lang=en')
            ->assertOk()
            ->assertSee('data-scene="reservation"', false)
            ->assertSee('data-tour-line="Returning to the foyer."', false)
            ->assertSee('data-step="5"', false)
            ->assertSee('value="grand-hall"', false)
            ->assertSee('Step :current of :total', false)
            ->assertSee(route('reservation.store', 'demo'), false);

        $this->get('/demo/reservation?lang=id')->assertOk()->assertSee('Langkah :current dari :total', false);

        $this->get('/demo/reservation?lang=en&space=grand-hall')
            ->assertOk()
            ->assertSee('data-preselect-space="grand-hall"', false);

        $this->get('/demo/spaces/grand-hall?lang=en')
            ->assertOk()
            ->assertSee('href="'.e(route('venue.reservation', ['venueSlug' => 'demo', 'lang' => 'en', 'space' => 'grand-hall'])).'"', false);

        $this->get('/demo/staff?lang=en')
            ->assertOk()
            ->assertSee('data-scene="staff"', false)
            ->assertSee('data-tour-line="Returning to the foyer."', false)
            ->assertSee('https://wa.me/6281200001111?text=', false)
            ->assertSee('tel:+62361000', false)
            ->assertSee('mailto:front@demo.test', false);

        $this->get('/draft/staff')->assertNotFound();
        $this->get('/draft/reservation')->assertNotFound();
    }

    public function test_every_cue_shows_the_rundown_with_its_own_button_lit(): void
    {
        Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);

        foreach (['' => 'F', '/spaces' => '01', '/facilities' => '02', '/info' => '03', '/reservation' => '04', '/staff' => '05'] as $path => $cue) {
            $page = $this->get('/demo'.$path.'?lang=en')
                ->assertOk()
                ->assertSee('class="rundown"', false)
                ->assertSee('data-cue="'.$cue.'"', false)
                ->assertSee('<span class="cue-display-code" data-cue-code>'.$cue.'</span>', false)
                ->getContent();

            $panel = Str::between($page, 'class="rundown"', '</nav>');
            $this->assertSame(1, substr_count($panel, 'aria-current="page"'));
            $this->assertMatchesRegularExpression('/aria-current="page"\s*>\s*<span class="rundown-key" aria-hidden="true">'.$cue.'<\/span>/', $panel);
        }
    }

    public function test_venue_information_and_team_cues_use_narrow_sheets(): void
    {
        Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);

        $this->get('/demo/staff?lang=en')->assertOk()->assertSee('class="foyer-content staff-panel', false);
        $this->get('/demo/info?lang=en')->assertOk()->assertSee('class="foyer-content info-panel', false);
    }

    public function test_every_stage_scene_uses_the_same_chat_controls_and_dock_contract(): void
    {
        $venue = Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);
        $venue->spaces()->create(['name' => 'Grand Hall', 'slug' => 'grand-hall', 'base_price' => 9500000, 'layouts' => ['banquet' => 200], 'is_active' => true]);
        $venue->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Pool', 'body' => 'Open all day.', 'is_active' => true]);

        foreach (['/demo', '/demo/info', '/demo/spaces', '/demo/facilities', '/demo/reservation', '/demo/staff'] as $path) {
            $this->get($path.'?lang=en')
                ->assertOk()
                ->assertSee('data-chat-dock="', false)
                ->assertSee('data-chat-launcher', false)
                ->assertSee('data-chat-composer', false)
                ->assertSee('aria-controls="planner-chat-log"', false);
        }
    }

    public function test_each_facility_has_its_own_page_with_neighbours_and_an_index(): void
    {
        $venue = Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);
        $pool = $venue->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Garden pool', 'body' => 'Open 07:00 to 20:00.', 'is_active' => true, 'sort_order' => 0]);
        $gym = $venue->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Gym', 'body' => 'Open 24 hours.', 'is_active' => true, 'sort_order' => 1]);
        $hidden = $venue->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Hidden spa', 'body' => 'Private.', 'is_active' => false]);

        $url = fn ($item) => route('venue.facility', ['venueSlug' => 'demo', 'facilityId' => $item->id, 'lang' => 'en']);

        $this->get('/demo/facilities?lang=en')
            ->assertOk()
            ->assertSee('data-scene="facilities"', false)
            ->assertSee($url($pool), false)
            ->assertSee($url($gym), false)
            ->assertDontSee($url($hidden), false);

        $this->get($url($pool))
            ->assertOk()
            ->assertSee('Facility 1 / 2')
            ->assertSee('Open 07:00 to 20:00.')
            ->assertSee('Ask about this facility')
            // both neighbours wrap around to the only other facility
            ->assertSee('rel="prev"', false)
            ->assertSee($url($gym), false);

        $this->get($url($hidden))->assertNotFound();
        $this->get('/demo/facilities/999999')->assertNotFound();
        $this->get('/draft/facilities')->assertNotFound();
        $this->get('/draft/info')->assertNotFound();
    }

    public function test_the_venue_information_scene_lists_only_approved_about_policy_and_faq_entries(): void
    {
        $venue = Venue::create([
            'name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'address' => 'Jl. Pantai 8', 'city' => 'Bali', 'country' => 'Indonesia',
            'load_in_time' => '06:30', 'curfew_time' => '22:00', 'description' => 'A beachfront venue.', 'latitude' => -8.8, 'longitude' => 115.23,
        ]);
        $venue->knowledgeItems()->create(['category' => 'policies', 'title' => 'Cancellation', 'body' => 'Free until 48 hours before the event.', 'is_active' => true]);
        $venue->knowledgeItems()->create(['category' => 'faq', 'title' => 'Is parking free?', 'body' => 'Yes, for guests.', 'is_active' => true]);
        $venue->knowledgeItems()->create(['category' => 'policies', 'title' => 'Draft policy', 'body' => 'Not approved.', 'is_active' => false]);
        $venue->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Pool', 'body' => 'Belongs to facilities.', 'is_active' => true]);

        $this->get('/demo?lang=en')
            ->assertOk()
            ->assertSee('href="'.route('venue.info', ['venueSlug' => 'demo', 'lang' => 'en']).'"', false)
            ->assertSee('data-tour-line="Going to the venue information desk."', false)
            ->assertDontSee('data-foyer-panel="info"', false)
            ->assertDontSee('Free until 48 hours before the event.');

        $this->get('/demo/info?lang=en')
            ->assertOk()
            ->assertSee('data-scene="info"', false)
            ->assertSee('data-tour-line="Returning to the foyer."', false)
            ->assertSee('Free until 48 hours before the event.')
            ->assertSee('Is parking free?')
            ->assertSee('class="info-hours-time"', false)
            ->assertSeeInOrder(['06:30', '22:00'])
            ->assertSee('https://www.google.com/maps?q=-8.8000000,115.2300000', false)
            ->assertDontSee('Not approved.');
    }

    public function test_the_foyer_shows_a_localised_loader_and_the_opening_screen_too(): void
    {
        Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);

        $this->get('/demo?lang=id')->assertOk()->assertSee('data-stage-loader', false)->assertSee('Membuka tirai…');
        $this->get('/?lang=en')->assertOk()->assertSee('data-stage-exit', false)->assertSee('Raising the curtain…');
    }

    public function test_the_planner_introduces_spaces_using_only_stored_venue_data(): void
    {
        $venue = Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR']);
        $venue->spaces()->create([
            'name' => 'Grand Hall', 'slug' => 'grand-hall', 'description' => 'A calm hall with a garden outlook', 'base_price' => 9500000, 'size_sqm' => 820, 'ceiling_height_m' => 6.5,
            'level_label' => 'Level 2', 'space_type' => 'indoor', 'layouts' => ['banquet' => 200, 'theatre' => 300, 'cocktail' => 400], 'is_active' => true, 'sort_order' => 0,
        ]);
        $venue->spaces()->create(['name' => 'Garden', 'slug' => 'garden', 'base_price' => 6500000, 'space_type' => 'outdoor', 'layouts' => ['banquet' => 120], 'is_active' => true, 'sort_order' => 1]);

        $this->get('/demo/spaces?lang=en')
            ->assertOk()
            ->assertSee('data-narrator', false)
            ->assertSee('These are our event spaces: 2 in all, from IDR 6.500.000 per event day.');

        $this->get('/demo/spaces/grand-hall?lang=en')
            ->assertOk()
            ->assertSee('Grand Hall. A calm hall with a garden outlook. Indoor hall of 820 m², with a 6.5 m ceiling, on Level 2. It seats up to 400 guests, from Cocktail (standing) 400, Theatre 300, Banquet 200. Rental starts from IDR 9.500.000 per event day');

        $this->get('/demo/spaces/garden?lang=en')
            ->assertOk()
            ->assertSee('Garden. Open-air space. It seats up to 120 guests, from Banquet 120. Rental starts from IDR 6.500.000 per event day. Catering and discounts are worked into your quote, and final availability is confirmed by our events team.');

        $this->get('/demo/spaces?lang=id')->assertOk()->assertSee('Ini ruang-ruang acara kami: total 2, mulai dari IDR 6.500.000 per hari acara.');
    }

    public function test_no_spaces_narration_is_shown_when_the_venue_has_no_spaces(): void
    {
        Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);

        $this->get('/demo/spaces?lang=en')->assertOk()->assertDontSee('data-key="spaces"', false)->assertDontSee('These are our event spaces');
    }

    public function test_the_planner_introduces_facilities_and_venue_information_from_stored_data(): void
    {
        $venue = Venue::create([
            'name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'city' => 'Bali', 'country' => 'Indonesia',
            'load_in_time' => '07:00', 'curfew_time' => '23:00',
        ]);
        $venue->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Garden pool', 'body' => "Open 07:00 to 20:00.\nTowels included.", 'is_active' => true, 'sort_order' => 0]);
        $venue->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Gym', 'body' => 'Open 24 hours.', 'is_active' => true, 'sort_order' => 1]);
        $venue->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Hidden spa', 'body' => 'Private.', 'is_active' => false, 'sort_order' => 2]);

        $pool = $venue->knowledgeItems()->where('title', 'Garden pool')->firstOrFail();

        $this->get('/demo/facilities?lang=en')
            ->assertOk()
            ->assertSee('We offer 2 facilities and services, including Garden pool; Gym.')
            ->assertDontSee('including Garden pool; Gym; Hidden spa');

        $this->get(route('venue.facility', ['venueSlug' => 'demo', 'facilityId' => $pool->id, 'lang' => 'en']))
            ->assertOk()
            ->assertSee('data-key="facility-', false)
            ->assertSee('data-text="Open 07:00 to 20:00.', false);

        $this->get('/demo/info?lang=en')
            ->assertOk()
            ->assertSee('Welcome to Demo. The venue is in Bali, Indonesia. Load-in is from 07:00 and every event wraps up by 23:00.');

        $this->get('/demo/info?lang=id')->assertOk()->assertSee('Selamat datang di Demo. Venue kami berada di Bali, Indonesia.');
    }

    public function test_venue_information_narration_skips_missing_data(): void
    {
        Venue::create(['name' => 'Bare', 'slug' => 'bare', 'public_status' => 'published']);

        $this->get('/bare/info?lang=en')
            ->assertOk()
            ->assertSee('Welcome to Bare. Load-in is from 07:00 and every event wraps up by 23:00. Below you will find the address')
            ->assertDontSee('The venue is in');
    }

    public function test_the_sound_toggle_is_available_on_the_opening_screen_and_the_foyer_in_every_language(): void
    {
        Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);

        $this->get('/?lang=en')->assertOk()->assertSee('data-sound-toggle', false)->assertSee('Sound on')->assertSee('Sound off');
        $this->get('/?lang=id')->assertOk()->assertSee('Suara aktif')->assertSee('Suara mati');
        $this->get('/demo?lang=en')->assertOk()->assertSee('data-sound-toggle', false)->assertSee('data-label-off="Sound off"', false);
        $this->get('/demo/info?lang=ja')
            ->assertOk()
            ->assertSee('サウンドオン')
            ->assertSee('data-lang="ja-JP"', false)
            ->assertSee('data-voice-preference="female"', false);
    }

    public function test_the_spaces_cue_shows_a_directory_of_spaces(): void
    {
        $venue = Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR']);
        $venue->spaces()->create(['name' => 'Grand Hall', 'slug' => 'grand-hall', 'base_price' => 9500000, 'layouts' => ['banquet' => 200], 'is_active' => true, 'sort_order' => 0]);
        $venue->spaces()->create(['name' => 'Garden Terrace', 'slug' => 'garden-terrace', 'base_price' => 22000000, 'layouts' => ['banquet' => 300], 'is_active' => true, 'sort_order' => 1]);
        $venue->spaces()->create(['name' => 'Retired Space', 'slug' => 'retired', 'base_price' => 1, 'layouts' => ['banquet' => 10], 'is_active' => false]);

        $this->get('/demo/spaces/garden-terrace?lang=id')
            ->assertOk()
            ->assertSee('class="space-directory"', false)
            ->assertSee('class="space-nav"', false)
            ->assertSee('mulai dari IDR&nbsp;9.500.000 / hari acara', false)
            ->assertDontSee('Retired Space')
            // the rundown still takes the guest back to the foyer
            ->assertSee('href="'.route('venue.show', ['venueSlug' => 'demo', 'lang' => 'id']).'" class="rundown-item"', false);

        // the open space is marked as current in the index, the others are not
        $index = $this->get('/demo/spaces/garden-terrace?lang=id')->getContent();
        $navigation = Str::between($index, '<nav class="space-nav">', '</nav>');
        $this->assertSame(1, substr_count($navigation, 'aria-current'));

        // the foyer keeps the ordinary menu
        $this->get('/demo?lang=id')
            ->assertOk()
            ->assertSee('href="'.route('venue.reservation', ['venueSlug' => 'demo', 'lang' => 'id']).'"', false)
            ->assertDontSee('class="space-nav"', false);
    }

    public function test_each_cue_opens_onto_its_own_photograph_with_the_planner_posed_in_front(): void
    {
        $venue = Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR']);
        $venue->spaces()->create(['name' => 'Grand Hall', 'slug' => 'grand-hall', 'base_price' => 9500000, 'layouts' => ['banquet' => 200], 'is_active' => true]);

        $this->get('/demo')
            ->assertOk()
            ->assertSee('photo-1549895058-36748fa6c6a7', false)
            ->assertSee('class="stage-character" data-pose="standing"', false);
        $this->get('/demo/spaces')
            ->assertOk()
            ->assertSee('photo-1712314947761-a8d718bd8c32', false)
            ->assertSee('class="stage-character" data-pose="presenting"', false);
        $this->get('/demo/info')
            ->assertOk()
            ->assertSee('photo-1560585810-44ce3741dd3b', false)
            ->assertSee('class="stage-character" data-pose="standing"', false)
            ->assertSee('images/character/character avatar.jpg', false);
        $this->get('/demo/staff')->assertOk()->assertSee('class="stage-character" data-pose="consulting"', false);
        $this->get('/demo/reservation')->assertOk()->assertSee('class="stage-character" data-pose="consulting"', false);

        // the space page is too full to share with her, so only her avatar stays
        $this->get('/demo/spaces/grand-hall')
            ->assertOk()
            ->assertSee('photo-1712314947761-a8d718bd8c32', false)
            ->assertSee('images/character/character avatar.jpg', false)
            ->assertDontSee('class="stage-character"', false);
    }

    public function test_facility_icons_follow_what_the_entry_is_about(): void
    {
        $venue = Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);
        $icon = fn (string $title, array $tags = []) => $venue->knowledgeItems()->make(['category' => 'facilities', 'title' => $title, 'body' => '-', 'tags' => $tags])->iconName();

        $this->assertSame('stage', $icon('Panggung modular'));
        $this->assertSame('sound', $icon('Sound system'));
        $this->assertSame('lighting', $icon('Lighting rig'));
        $this->assertSame('catering', $icon('Katering in-house'));
        $this->assertSame('parking', $icon('Parkir dan valet'));
        $this->assertSame('transit', $icon('MRT dan LRT'));
        $this->assertSame('transport', $icon('Antar-jemput bandara'));
        $this->assertSame('bridal', $icon('Bridal suite'));
        $this->assertSame('power', $icon('Genset cadangan'));
        $this->assertSame('place', $icon('Atraksi terdekat'));
        $this->assertSame('wifi', $icon('Internet', ['wifi']));
        $this->assertSame('star', $icon('Kids club'));
    }

    public function test_every_rundown_item_leads_to_its_cue_with_a_topic_for_the_planner(): void
    {
        Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);

        $scenes = [
            'venue.spaces' => 'Saya ingin melihat ruang acara dan kapasitasnya.',
            'venue.facilities' => 'Fasilitas dan opsi katering apa saja yang tersedia di venue ini?',
            'venue.info' => 'Apa kebijakan load-in, jam malam, aturan venue, dan pembatalan?',
            'venue.reservation' => 'Saya ingin memesan ruang untuk acara. Bantu saya cek ketersediaan.',
            'venue.staff' => 'Saya ingin bicara dengan tim event.',
        ];

        $response = $this->get('/demo?lang=id')->assertOk();

        foreach ($scenes as $route => $topic) {
            $url = route($route, ['venueSlug' => 'demo', 'lang' => 'id']);

            $response->assertSee('href="'.$url.'"', false)
                ->assertSeeInOrder(['class="rundown"', 'href="'.$url.'"', 'data-stage-exit', 'data-topic="'.$topic.'"'], false);
            $this->get($url)->assertOk();
        }
    }

    public function test_foyer_offers_the_spaces_and_an_event_request_as_the_two_main_actions_in_the_guest_language(): void
    {
        $venue = Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR']);
        $venue->spaces()->create(['name' => 'Grand Hall', 'slug' => 'grand-hall', 'base_price' => 9500000, 'layouts' => ['banquet' => 200], 'is_active' => true]);

        $this->get('/demo?lang=id')
            ->assertOk()
            ->assertSee('class="foyer-action foyer-action-signal" data-stage-exit', false)
            ->assertSee('href="'.route('venue.spaces', ['venueSlug' => 'demo', 'lang' => 'id']).'"', false)
            ->assertSee('Lihat ruang acara')
            ->assertSee('href="'.route('venue.reservation', ['venueSlug' => 'demo', 'lang' => 'id']).'"', false)
            ->assertSee('Rencanakan acara saya');

        $this->get('/demo?lang=en')->assertOk()->assertSee('See the event spaces')->assertSee('Plan my event');
        $this->get('/demo?lang=ja')->assertOk()->assertSee('イベントスペースを見る');
    }

    public function test_foyer_without_any_space_shows_no_actions_that_lead_nowhere(): void
    {
        Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);

        $this->get('/demo?lang=en')->assertOk()->assertDontSee('foyer-cta', false)->assertDontSee('See the event spaces');
    }

    public function test_foyer_stats_show_the_price_from_the_largest_capacity_and_the_weekday_saving(): void
    {
        $venue = Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR', 'weekday_discount_percent' => 15]);
        $venue->spaces()->create(['name' => 'Small', 'slug' => 'small', 'base_price' => 6500000, 'layouts' => ['banquet' => 80], 'is_active' => true]);
        $venue->spaces()->create(['name' => 'Large', 'slug' => 'large', 'base_price' => 20000000, 'layouts' => ['banquet' => 500, 'cocktail' => 1000], 'is_active' => true]);

        $this->get('/demo?lang=en')->assertOk()->assertSee('IDR 6.500.000')->assertSee('1.000 guests')->assertSee('15% off');
    }

    public function test_foyer_stats_fall_back_to_the_load_in_time_without_a_weekday_discount(): void
    {
        $venue = Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR', 'load_in_time' => '06:30']);
        $venue->spaces()->create(['name' => 'Small', 'slug' => 'small', 'base_price' => 6500000, 'layouts' => ['banquet' => 80], 'is_active' => true]);

        $this->get('/demo?lang=en')->assertOk()->assertSee('Load-in')->assertSee('06:30')->assertDontSee('Weekday events');
    }
}
