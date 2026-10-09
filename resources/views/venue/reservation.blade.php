<section class="foyer-content wizard-panel stage-panel-right @container" aria-label="{{ $wizard['title'] }}">
    <a href="{{ route('venue.show', ['venueSlug' => $venue->slug, 'lang' => $locale]) }}" data-stage-exit data-tour-line="{{ $narration['tour_foyer'] }}" class="panel-close" aria-label="{{ $foyer['back'] }}"><span aria-hidden="true">×</span></a>
    <p class="foyer-eyebrow">{{ $foyer['cue'] }} {{ $cue }} · {{ $venue->name }}</p>
    <h2>{{ $wizard['title'] }}</h2>

    <div class="wizard" data-wizard
        data-quote-url="{{ route('reservation.quote', $venue->slug) }}"
        data-availability-url="{{ route('reservation.availability', $venue->slug) }}"
        data-submit-url="{{ route('reservation.store', $venue->slug) }}"
        data-venue-slug="{{ $venue->slug }}"
        data-locale="{{ $locale }}"
        data-currency="{{ $venue->currency }}"
        data-today="{{ $today }}"
        data-max-days="{{ \App\Services\Reservation\ReservationService::MAX_DAYS }}"
        data-preselect-space="{{ $preselectedSpace ?? '' }}"
    >
        <div data-wizard-flow>
            <p class="mt-3 max-w-xl text-sm leading-relaxed text-stone-600">{{ $wizard['intro'] }}</p>

            <div class="wizard-progress" aria-label="{{ $wizard['title'] }}">
                <p class="wizard-step-label" data-wizard-step-label aria-live="polite"></p>
                <ol>
                    @foreach ($wizard['steps'] as $stepLabel)
                        <li data-progress-step><span>{{ $stepLabel }}</span></li>
                    @endforeach
                </ol>
            </div>

            <form data-wizard-form novalidate autocomplete="on">
                <div data-step="1" class="wizard-step wizard-step-wide">
                    <input type="hidden" name="event_start" required>
                    <input type="hidden" name="event_end" required>

                    <div class="wizard-dates" data-date-picker>
                        <div class="wizard-slots">
                            <div class="wizard-slot" data-slot="event_start"><span>{{ $wizard['event_start'] }}</span><strong data-slot-value>{{ $wizard['cal_choose'] }}</strong></div>
                            <div class="wizard-slot" data-slot="event_end"><span>{{ $wizard['event_end'] }}</span><strong data-slot-value>{{ $wizard['cal_choose'] }}</strong></div>
                        </div>
                        <div class="wizard-cal">
                            <div class="wizard-cal-head">
                                <button type="button" data-cal-prev aria-label="{{ $wizard['cal_prev'] }}">←</button>
                                <strong data-cal-title aria-live="polite"></strong>
                                <button type="button" data-cal-next aria-label="{{ $wizard['cal_next'] }}">→</button>
                            </div>
                            <div class="wizard-cal-week" data-cal-weekdays aria-hidden="true"></div>
                            <div class="wizard-cal-grid" data-cal-grid></div>
                        </div>
                        <div class="wizard-dates-extra">
                            <p class="wizard-hint" data-days-hint aria-live="polite"></p>
                            @if ($wizard['discount_hint'])
                                <p class="wizard-discount-hint"><span aria-hidden="true">%</span>{{ $wizard['discount_hint'] }}</p>
                            @endif
                        </div>
                    </div>
                </div>

                <div data-step="2" class="wizard-step" hidden>
                    <label class="wizard-field wizard-field-wide">
                        <span>{{ $wizard['event_type'] }}</span>
                        <select name="event_type" required>
                            @foreach (\App\Models\Booking::EVENT_TYPES as $type)
                                <option value="{{ $type }}">{{ $spaceTerms['event'][$type] }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="wizard-field">
                        <span>{{ $wizard['guests'] }}</span>
                        <input type="number" name="guests" min="1" max="5000" value="100" inputmode="numeric" required>
                    </label>
                    <label class="wizard-field">
                        <span>{{ $wizard['setup_style'] }}</span>
                        <select name="setup_style" required>
                            @foreach (\App\Models\Space::LAYOUTS as $layout)
                                <option value="{{ $layout }}">{{ $spaceTerms['layout'][$layout] }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <div data-step="3" class="wizard-step wizard-step-wide" hidden>
                    <fieldset>
                        <legend>{{ $wizard['choose_space'] }}</legend>
                        <div class="wizard-spaces">
                            @foreach ($spaces as $space)
                                <label class="wizard-space" data-space-option
                                    data-name="{{ $space->translatedName($locale) }}"
                                    data-layouts='@json($space->layouts ?? [])'
                                    data-max-guests="{{ $space->maxGuests() }}"
                                    data-catering="{{ $space->catering_available ? 1 : 0 }}"
                                    data-catering-price="{{ (float) $space->catering_price }}"
                                >
                                    <input type="radio" name="space_slug" value="{{ $space->slug }}">
                                    <span class="wizard-space-body">
                                        <strong>{{ $space->translatedName($locale) }}</strong>
                                        <span>{{ $spaceTerms['type'][$space->space_type] ?? '' }}</span>
                                        <span>{{ str_replace(':count', number_format($space->maxGuests(), 0, ',', '.'), $wizard['fits']) }}</span>
                                        <span class="wizard-space-price">{{ $labels['from'] }} {{ $venue->currency }} {{ number_format((float) $space->base_price, 0, ',', '.') }} {{ $labels['per_day'] }}</span>
                                        <span class="wizard-space-hint" data-space-hint></span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    <label class="wizard-check" data-catering-field hidden>
                        <input type="checkbox" name="catering" value="1">
                        <span>{{ $wizard['catering'] }} <small data-catering-price-label></small></span>
                    </label>
                </div>

                <div data-step="4" class="wizard-step" hidden>
                    <label class="wizard-field wizard-field-wide">
                        <span>{{ $wizard['name'] }}</span>
                        <input type="text" name="guest_name" maxlength="100" autocomplete="name" required>
                    </label>
                    <fieldset class="wizard-field-wide">
                        <legend>{{ $wizard['contact_method'] }}</legend>
                        <div class="wizard-pills">
                            @foreach (['whatsapp', 'phone', 'email'] as $method)
                                <label><input type="radio" name="contact_type" value="{{ $method }}" @checked($method === 'whatsapp')><span>{{ $wizard[$method] }}</span></label>
                            @endforeach
                        </div>
                    </fieldset>
                    <label class="wizard-field wizard-field-wide">
                        <span>{{ $wizard['contact_value'] }}</span>
                        <input type="text" name="contact_value" maxlength="120" autocomplete="tel" required>
                    </label>
                    <label class="wizard-field wizard-field-wide">
                        <span>{{ $wizard['special'] }}</span>
                        <textarea name="special_request" rows="2" maxlength="500" placeholder="{{ $wizard['special_placeholder'] }}"></textarea>
                    </label>
                </div>

                <div data-step="5" class="wizard-step wizard-step-wide" hidden>
                    <dl class="wizard-summary" data-summary></dl>
                    <p class="wizard-discount" data-summary-discount hidden><span>{{ $wizard['discount'] }} <b data-summary-discount-percent></b></span> <strong data-summary-discount-amount></strong></p>
                    <p class="wizard-total"><span>{{ $wizard['estimated_total'] }}</span> <strong data-summary-total></strong></p>
                    <p class="wizard-hint">{{ $wizard['disclaimer'] }}</p>
                </div>

                <div class="wizard-error" data-wizard-error role="alert" hidden>
                    <p data-wizard-error-text></p>
                    <div class="wizard-alternatives" data-wizard-alternatives hidden>
                        <p>{{ $wizard['available_instead'] }}</p>
                        <ul></ul>
                    </div>
                </div>

                <div class="wizard-actions">
                    <button type="button" class="wizard-secondary" data-wizard-back hidden>{{ $wizard['back'] }}</button>
                    <button type="button" class="foyer-action" data-wizard-next>{{ $wizard['next'] }} <span aria-hidden="true">→</span></button>
                    <button type="submit" class="foyer-action" data-wizard-submit hidden>{{ $wizard['submit'] }}</button>
                </div>
            </form>
        </div>

        <div class="wizard-done" data-wizard-done hidden>
            <p class="foyer-eyebrow">{{ $wizard['done_title'] }}</p>
            <p class="wizard-reference-label">{{ $wizard['reference'] }}</p>
            <p class="wizard-reference" data-done-reference></p>
            <p class="wizard-status">{{ $wizard['awaiting'] }}</p>
            <p class="mt-4 max-w-md text-sm leading-relaxed text-stone-600">{{ $wizard['done_hint'] }}</p>
            <div class="mt-6 flex flex-wrap gap-3">
                <a class="foyer-action" data-done-whatsapp target="_blank" rel="noopener" hidden>{{ $wizard['send_whatsapp'] }} <span aria-hidden="true">↗</span></a>
                <a class="wizard-secondary" data-done-phone hidden>{{ $wizard['call_venue'] }}</a>
                <a class="wizard-secondary" data-done-email hidden>{{ $wizard['email_venue'] }}</a>
            </div>
            <div class="mt-6 flex flex-wrap gap-3">
                <a href="{{ route('venue.show', ['venueSlug' => $venue->slug, 'lang' => $locale]) }}" data-stage-exit class="wizard-secondary">{{ $foyer['back'] }}</a>
                <button type="button" class="wizard-secondary" data-wizard-reset>{{ $wizard['new_request'] }}</button>
            </div>
        </div>

        <script type="application/json" data-wizard-labels>@json($wizard)</script>
    </div>
</section>
