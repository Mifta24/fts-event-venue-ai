/**
 * Full-screen stage chrome shared by the opening screen and every cue:
 * raises the curtain once the photo is ready, runs the cue display through
 * every cue between the last scene and this one, and drops the curtain again
 * before following a link — so moving between pages feels like a change of
 * scene on stage rather than loading a page.
 *
 * A link may carry `data-topic`: the question the guest picked from the menu.
 * It is handed to the AI planner, which raises it once the next scene is up
 * (or straight away when the guest is already in that scene).
 */
const TOPIC_KEY = 'planner_pending_topic';
const CUE_KEY = 'venue_last_cue';
const CUE_ORDER = ['F', '01', '02', '03', '04', '05'];

/**
 * The cue display counts through every cue between the last one the guest
 * was on and this one, counting up or down in the order of the run of show.
 */
function runCueDisplay(stage, reducedMotion) {
    const current = stage.dataset.cue;
    const code = stage.querySelector('[data-cue-code]');
    let previous = null;

    try {
        previous = sessionStorage.getItem(CUE_KEY);
        sessionStorage.setItem(CUE_KEY, current);
    } catch { /* private mode: the display just shows the current cue */ }

    if (!code || !previous || previous === current) return;

    const from = CUE_ORDER.indexOf(previous);
    const to = CUE_ORDER.indexOf(current);
    if (from < 0 || to < 0) return;

    if (reducedMotion) return;

    const step = to > from ? 1 : -1;
    let index = from;
    code.textContent = CUE_ORDER[index];

    const timer = window.setInterval(() => {
        index += step;
        code.textContent = CUE_ORDER[index];
        if (index === to) window.clearInterval(timer);
    }, 180);
}

function initStage() {
    const stage = document.querySelector('.stage');
    if (!stage) return;

    const loader = stage.querySelector('[data-stage-loader]');
    const image = stage.querySelector('.stage-image');
    const tour = stage.querySelector('[data-stage-tour]');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const showScene = () => loader?.classList.add('is-ready');

    runCueDisplay(stage, reducedMotion);

    // On phones the rundown is a scrolling strip; keep the lit cue in view.
    stage.querySelector('.rundown-item[aria-current]')?.scrollIntoView({ block: 'nearest', inline: 'center' });

    if (!image || image.complete) {
        showScene();
    } else {
        image.addEventListener('load', showScene, { once: true });
        image.addEventListener('error', showScene, { once: true });
    }

    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            leaving = false;
            stage.classList.remove('is-leaving');
            tour?.classList.remove('is-visible');
        }
    });

    let leaving = false;

    /** Ease the stage out, then walk the guest over to `href`. */
    function leave(href, line = null, topic = null) {
        if (leaving) return;
        leaving = true;

        try {
            if (topic) sessionStorage.setItem(TOPIC_KEY, topic);
            else sessionStorage.removeItem(TOPIC_KEY);
        } catch { /* private mode: the scene still changes, only the topic is dropped */ }

        // The planner announces the cue while the curtain falls.
        if (line && tour) {
            tour.textContent = line;
            tour.classList.add('is-visible');
        }

        stage.classList.add('is-leaving');

        const chime = window.venueSound?.isEnabled() ?? false;
        if (chime) window.venueSound.play('enter');

        const hold = reducedMotion ? 0 : line ? 900 : 520;
        window.setTimeout(() => { window.location.href = href; }, hold);
    }

    document.querySelectorAll('[data-stage-exit]').forEach((link) => {
        link.addEventListener('click', (event) => {
            const modified = event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0;
            if (event.defaultPrevented || modified || link.target === '_blank') return;

            event.preventDefault();

            // Already in this scene: no walk needed, the planner just takes
            // the topic up where the guest is standing.
            const samePlace = link.pathname === window.location.pathname;
            if (samePlace && link.dataset.topic) {
                window.dispatchEvent(new CustomEvent('planner:ask', { detail: { message: link.dataset.topic } }));
                return;
            }
            if (samePlace) return;

            leave(link.href, link.dataset.tourLine, link.dataset.topic);
        });
    });

    window.venueStage = { leave };
}

/** The topic the guest picked on the previous scene, if any (read once). */
window.takePendingPlannerTopic = () => {
    try {
        const topic = sessionStorage.getItem(TOPIC_KEY);
        sessionStorage.removeItem(TOPIC_KEY);
        return topic;
    } catch {
        return null;
    }
};

document.addEventListener('DOMContentLoaded', initStage);
