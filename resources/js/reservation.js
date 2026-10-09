import { createDatePicker } from './date-picker';

/**
 * Guided event request wizard on the planning cue: five short steps — dates,
 * event, space, contact, summary — then a reference number and
 * WhatsApp / phone / email hand-over. Progress survives leaving the page and
 * switching language (sessionStorage), and every rule is enforced again on the
 * server, which is the only source of price and availability.
 */
function initReservationWizard() {
    const root = document.querySelector('[data-wizard]');
    if (!root) return;

    const labels = JSON.parse(root.querySelector('[data-wizard-labels]').textContent);
    const form = root.querySelector('[data-wizard-form]');
    const flow = root.querySelector('[data-wizard-flow]');
    const done = root.querySelector('[data-wizard-done]');
    const steps = [...root.querySelectorAll('[data-step]')];
    const progress = [...root.querySelectorAll('[data-progress-step]')];
    const stepLabel = root.querySelector('[data-wizard-step-label]');
    const daysHint = root.querySelector('[data-days-hint]');
    const backButton = root.querySelector('[data-wizard-back]');
    const nextButton = root.querySelector('[data-wizard-next]');
    const submitButton = root.querySelector('[data-wizard-submit]');
    const errorBox = root.querySelector('[data-wizard-error]');
    const errorText = root.querySelector('[data-wizard-error-text]');
    const alternativesBox = root.querySelector('[data-wizard-alternatives]');
    const spaceOptions = [...root.querySelectorAll('[data-space-option]')];
    const cateringField = root.querySelector('[data-catering-field]');
    const cateringPriceLabel = root.querySelector('[data-catering-price-label]');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    const config = {
        quoteUrl: root.dataset.quoteUrl,
        submitUrl: root.dataset.submitUrl,
        locale: root.dataset.locale,
        currency: root.dataset.currency,
        today: root.dataset.today,
        maxDays: Number(root.dataset.maxDays),
        draftKey: `reservation_draft_${root.dataset.venueSlug}`,
        tokenKey: `planner_token_${root.dataset.venueSlug}`,
    };

    const fieldStep = {
        event_start: 1, event_end: 1, event_type: 2, guests: 2, setup_style: 2, space_slug: 3, catering: 3,
        guest_name: 4, contact_type: 4, contact_value: 4, special_request: 4,
    };

    let current = 1;
    let quote = null;
    let busy = false;

    const text = (template, values) => Object.entries(values).reduce((out, [key, value]) => out.replace(`:${key}`, value), template);
    const money = (value) => `${config.currency} ${new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(Number(value) || 0)}`;

    function formatDate(iso) {
        const [year, month, day] = iso.split('-').map(Number);
        return new Intl.DateTimeFormat(config.locale, { day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(year, month - 1, day));
    }

    const datePicker = createDatePicker({
        root: root.querySelector('[data-date-picker]'),
        eventStart: form.elements.event_start,
        eventEnd: form.elements.event_end,
        config,
        labels,
        formatDate,
        onChange: () => {
            clearError();
            form.dispatchEvent(new Event('input', { bubbles: true }));
        },
    });

    /** Event days, both ends included: a one-day event is one day. */
    function daysBetween(start, end) {
        if (!start || !end) return 0;
        const [y1, m1, d1] = start.split('-').map(Number);
        const [y2, m2, d2] = end.split('-').map(Number);
        return Math.round((Date.UTC(y2, m2 - 1, d2) - Date.UTC(y1, m1 - 1, d1)) / 86400000) + 1;
    }

    function values() {
        const data = new FormData(form);
        return {
            event_start: data.get('event_start') || '',
            event_end: data.get('event_end') || '',
            event_type: data.get('event_type') || 'social',
            guests: Number(data.get('guests')) || 0,
            setup_style: data.get('setup_style') || 'banquet',
            space_slug: data.get('space_slug') || '',
            catering: form.elements.catering.checked && !cateringField.hidden,
            guest_name: (data.get('guest_name') || '').trim(),
            contact_type: data.get('contact_type') || 'whatsapp',
            contact_value: (data.get('contact_value') || '').trim(),
            special_request: (data.get('special_request') || '').trim(),
        };
    }

    function saveDraft() {
        try {
            sessionStorage.setItem(config.draftKey, JSON.stringify({ step: current, values: values() }));
        } catch { /* storage unavailable: the wizard still works without it */ }
    }

    function clearDraft() {
        try {
            sessionStorage.removeItem(config.draftKey);
        } catch { /* nothing to clear */ }
    }

    function restoreDraft() {
        try {
            const draft = JSON.parse(sessionStorage.getItem(config.draftKey) || 'null');
            if (!draft) return;
            const { values: saved } = draft;
            ['event_start', 'event_end', 'event_type', 'guests', 'setup_style', 'guest_name', 'contact_value', 'special_request'].forEach((name) => {
                if (saved[name] !== undefined && saved[name] !== '' && saved[name] !== 0) form.elements[name].value = saved[name];
            });
            if (saved.contact_type) form.elements.contact_type.value = saved.contact_type;
            if (saved.space_slug) form.elements.space_slug.value = saved.space_slug;
            form.elements.catering.checked = Boolean(saved.catering);
            current = Math.min(Math.max(Number(draft.step) || 1, 1), steps.length);
        } catch { /* ignore a corrupt draft */ }
    }

    function clearError() {
        errorBox.hidden = true;
        errorText.textContent = '';
        alternativesBox.hidden = true;
        alternativesBox.querySelector('ul').replaceChildren();
    }

    function showError(message, alternatives = []) {
        errorText.textContent = message;
        errorBox.hidden = false;

        const list = alternativesBox.querySelector('ul');
        list.replaceChildren();
        alternatives.forEach((alternative) => {
            const item = document.createElement('li');
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'wizard-secondary';
            button.textContent = `${alternative.name} · ${money(alternative.total)}`;
            button.addEventListener('click', () => {
                form.elements.space_slug.value = alternative.slug;
                refreshSpaces();
                clearError();
                saveDraft();
            });
            item.appendChild(button);
            list.appendChild(item);
        });
        alternativesBox.hidden = alternatives.length === 0;
    }

    function selectedOption() {
        const slug = form.elements.space_slug.value;
        return spaceOptions.find((option) => option.querySelector('input').value === slug) || null;
    }

    /** Whether the space seats the party in the chosen setup. */
    function fits(option, party) {
        const capacity = JSON.parse(option.dataset.layouts || '{}')[party.setup_style];
        return Boolean(capacity) && party.guests >= 1 && party.guests <= Number(capacity);
    }

    function refreshSpaces() {
        const party = values();

        spaceOptions.forEach((option) => {
            const input = option.querySelector('input');
            const suitable = fits(option, party);
            input.disabled = !suitable;
            option.classList.toggle('is-disabled', !suitable);
            option.querySelector('[data-space-hint]').textContent = suitable ? '' : labels.too_small;
            if (!suitable && input.checked) input.checked = false;
        });

        const chosen = selectedOption();
        cateringField.hidden = !chosen || chosen.dataset.catering !== '1';
        if (cateringField.hidden) form.elements.catering.checked = false;
        else cateringPriceLabel.textContent = text(labels.catering_each, { price: money(chosen.dataset.cateringPrice) });
    }

    function refreshDays() {
        const start = form.elements.event_start.value;
        const days = daysBetween(start, form.elements.event_end.value);

        if (days > 0) daysHint.textContent = `${days} ${labels.days}`;
        else if (!datePicker.hasOpenDays) daysHint.textContent = labels.cal_none;
        else daysHint.textContent = start ? labels.cal_pick_end : labels.cal_pick_start;
    }

    function validate(step) {
        const data = values();

        if (step === 1) {
            if (!data.event_start) return { field: 'event_start', message: labels.cal_pick_start };
            if (data.event_start < config.today) return { field: 'event_start', message: labels.start_past };
            if (!data.event_end) return { field: 'event_end', message: labels.cal_pick_end };
            if (data.event_end < data.event_start) return { field: 'event_end', message: labels.end_after };
            if (daysBetween(data.event_start, data.event_end) > config.maxDays) return { field: 'event_end', message: text(labels.too_long, { max: config.maxDays }) };
        }

        if (step === 2) {
            if (data.guests < 1) return { field: 'guests', message: labels.invalid };
        }

        if (step === 3) {
            const chosen = selectedOption();
            if (!chosen) return { field: 'space_slug', message: labels.select_space };
            if (!fits(chosen, data)) return { field: 'space_slug', message: labels.too_small };
        }

        if (step === 4) {
            if (!data.guest_name) return { field: 'guest_name', message: labels.invalid };
            const valid = data.contact_type === 'email'
                ? /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.contact_value)
                : /^\+?[0-9\s\-().]{6,20}$/.test(data.contact_value);
            if (!valid) return { field: 'contact_value', message: data.contact_type === 'email' ? labels.contact_email : labels.contact_phone };
        }

        return null;
    }

    function setBusy(value, label = null) {
        busy = value;
        [backButton, nextButton, submitButton].forEach((button) => { button.disabled = value; });
        if (label) nextButton.firstChild.textContent = `${label} `;
        else nextButton.firstChild.textContent = `${labels.next} `;
    }

    function showStep(step, focus = false) {
        current = step;
        steps.forEach((element, index) => { element.hidden = index + 1 !== step; });
        progress.forEach((element, index) => {
            element.classList.toggle('is-current', index + 1 === step);
            element.classList.toggle('is-done', index + 1 < step);
            if (index + 1 === step) element.setAttribute('aria-current', 'step');
            else element.removeAttribute('aria-current');
        });
        stepLabel.textContent = `${text(labels.step_of, { current: step, total: steps.length })} · ${labels.steps[step - 1]}`;
        backButton.hidden = step === 1;
        nextButton.hidden = step === steps.length;
        submitButton.hidden = step !== steps.length;

        if (step === 2 || step === 3) refreshSpaces();
        if (step === 3 && spaceOptions.every((option) => option.classList.contains('is-disabled'))) showError(labels.capacity);
        if (step === steps.length) renderSummary();
        if (focus) steps[step - 1].querySelector('input:not([type="hidden"]):not([disabled]), select, textarea, [data-cal-focus]')?.focus({ preventScroll: true });
        saveDraft();
    }

    function goToField(field) {
        showStep(fieldStep[field] || current, true);
    }

    async function post(url, payload) {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken || '' },
            body: JSON.stringify(payload),
        });
        const body = await response.json().catch(() => ({}));
        return { ok: response.ok, status: response.status, body };
    }

    function eventPayload() {
        const { event_start, event_end, event_type, guests, setup_style, space_slug, catering } = values();
        return { event_start, event_end, event_type, guests, setup_style, space_slug, catering, locale: config.locale };
    }

    function handleFailure(result) {
        if (result.status === 422 && result.body.errors) {
            const [field, messages] = Object.entries(result.body.errors)[0];
            showError(messages[0], result.body.alternatives || []);
            if (fieldStep[field] && fieldStep[field] !== current) showStep(fieldStep[field]);
            return;
        }
        showError(labels.error_generic);
    }

    async function next() {
        if (busy || current >= steps.length) return;
        clearError();

        const problem = validate(current);
        if (problem) {
            showError(problem.message);
            form.elements[problem.field]?.focus?.();
            return;
        }

        if (current === 3) {
            setBusy(true, labels.checking);
            try {
                const result = await post(config.quoteUrl, eventPayload());
                if (!result.ok) {
                    handleFailure(result);
                    return;
                }
                quote = result.body;
            } catch {
                showError(labels.error_network);
                return;
            } finally {
                setBusy(false);
            }
        }

        showStep(current + 1, true);
    }

    function summaryRow(label, value, step) {
        const wrapper = document.createElement('div');
        const term = document.createElement('dt');
        term.textContent = label;
        const detail = document.createElement('dd');
        const span = document.createElement('span');
        span.textContent = value;
        const edit = document.createElement('button');
        edit.type = 'button';
        edit.textContent = labels.edit;
        edit.addEventListener('click', () => showStep(step, true));
        detail.append(span, edit);
        wrapper.append(term, detail);
        return wrapper;
    }

    function renderSummary() {
        const data = values();
        const chosen = selectedOption();
        const days = daysBetween(data.event_start, data.event_end);
        const range = days > 1 ? `${formatDate(data.event_start)} → ${formatDate(data.event_end)} (${days} ${labels.days})` : formatDate(data.event_start);

        const space = `${chosen ? chosen.dataset.name : ''}${data.catering ? ` + ${labels.catering_short}` : ''}`;
        const rows = [
            summaryRow(labels.summary_dates, range, 1),
            summaryRow(labels.summary_event, `${labels.event_names[data.event_type]} · ${data.guests.toLocaleString(config.locale)} ${labels.guests_unit} · ${labels.layout_names[data.setup_style]}`, 2),
            summaryRow(labels.summary_space, space, 3),
            summaryRow(labels.contact, `${data.guest_name} · ${labels[data.contact_type]}: ${data.contact_value}`, 4),
        ];
        if (data.special_request) rows.push(summaryRow(labels.special, data.special_request, 4));

        root.querySelector('[data-summary]').replaceChildren(...rows);
        root.querySelector('[data-summary-total]').textContent = quote ? money(quote.grand_total) : '';

        const discount = root.querySelector('[data-summary-discount]');
        const hasDiscount = Boolean(quote && quote.discount_percent > 0);
        discount.hidden = !hasDiscount;
        if (hasDiscount) {
            discount.querySelector('[data-summary-discount-percent]').textContent = `−${quote.discount_percent}%`;
            discount.querySelector('[data-summary-discount-amount]').textContent = `−${money(quote.discount_total)}`;
        }
    }

    function showDone(result) {
        flow.hidden = true;
        done.hidden = false;
        root.querySelector('[data-done-reference]').textContent = result.reference;

        [['whatsapp', 'whatsapp_url'], ['phone', 'phone_url'], ['email', 'email_url']].forEach(([name, key]) => {
            const link = root.querySelector(`[data-done-${name}]`);
            const url = result.handover?.[key];
            link.hidden = !url;
            if (url) link.href = url;
        });
    }

    async function submit(event) {
        event.preventDefault();
        if (busy || current !== steps.length) return;
        clearError();

        const problem = [1, 2, 3, 4].map(validate).find(Boolean);
        if (problem) {
            showError(problem.message);
            goToField(problem.field);
            return;
        }

        setBusy(true);
        submitButton.textContent = labels.sending;

        try {
            const result = await post(config.submitUrl, {
                ...eventPayload(),
                guest_name: values().guest_name,
                contact_type: values().contact_type,
                contact_value: values().contact_value,
                special_request: values().special_request,
                guest_token: localStorage.getItem(config.tokenKey) || undefined,
            });

            if (!result.ok) {
                handleFailure(result);
                return;
            }

            clearDraft();
            showDone(result.body);
        } catch {
            showError(labels.error_network);
        } finally {
            setBusy(false);
            submitButton.textContent = labels.submit;
        }
    }

    function reset() {
        form.reset();
        datePicker.clear();
        quote = null;
        clearError();
        done.hidden = true;
        flow.hidden = false;
        clearDraft();
        refreshDays();
        showStep(1, true);
    }

    function preselect(slug) {
        if (!flow.hidden) {
            refreshSpaces();
            const option = spaceOptions.find((candidate) => candidate.querySelector('input').value === slug);
            if (option && !option.querySelector('input').disabled) {
                form.elements.space_slug.value = slug;
                refreshSpaces();
                saveDraft();
            }
        }
    }

    form.addEventListener('input', () => { refreshDays(); if (current <= 3) refreshSpaces(); saveDraft(); });
    form.addEventListener('change', () => { refreshSpaces(); saveDraft(); });
    form.addEventListener('submit', submit);
    nextButton.addEventListener('click', next);
    backButton.addEventListener('click', () => { clearError(); showStep(Math.max(1, current - 1), true); });
    root.querySelector('[data-wizard-reset]').addEventListener('click', reset);
    form.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && event.target.tagName !== 'TEXTAREA' && current < steps.length) {
            event.preventDefault();
            next();
        }
    });

    restoreDraft();
    datePicker.show();
    refreshDays();

    if (root.dataset.availabilityUrl) {
        fetch(root.dataset.availabilityUrl, { headers: { Accept: 'application/json' } })
            .then((response) => (response.ok ? response.json() : Promise.reject(new Error('availability unavailable'))))
            .then((availability) => {
                datePicker.setAvailability(availability.dates);
                refreshDays();
            })
            .catch(() => { /* the server still checks every day when the guest continues */ });
    }

    refreshSpaces();
    showStep(current);
    preselect(root.dataset.preselectSpace || '');
}

document.addEventListener('DOMContentLoaded', initReservationWizard);
