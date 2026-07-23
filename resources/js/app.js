const feedbackSelector = '[data-feedback-form]';
const prefersReducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

document.querySelectorAll('[data-preset-catalog]').forEach((catalog) => {
    const form = catalog.closest('[data-preset-form]');
    const search = catalog.querySelector('[data-preset-search]');
    const cards = [...catalog.querySelectorAll('[data-preset-card]')];
    const filters = [...catalog.querySelectorAll('[data-preset-category]')];
    const empty = catalog.querySelector('[data-preset-empty]');
    const status = catalog.querySelector('[data-preset-status]');
    let activeCategory = 'all';

    const applyFilters = () => {
        const query = search.value.trim().toLocaleLowerCase();
        let visible = 0;

        cards.forEach((card) => {
            const matchesCategory = activeCategory === 'all'
                || card.dataset.presetCategoryName === activeCategory;
            const matchesSearch = !query
                || card.dataset.presetSearchText.toLocaleLowerCase().includes(query);
            card.hidden = !(matchesCategory && matchesSearch);

            if (!card.hidden) {
                visible += 1;
            }
        });

        empty.hidden = visible > 0;
    };

    search.addEventListener('input', applyFilters);

    catalog.addEventListener('click', (event) => {
        const categoryButton = event.target.closest('[data-preset-category]');
        if (categoryButton) {
            activeCategory = categoryButton.dataset.presetCategory;
            filters.forEach((button) => {
                const selected = button === categoryButton;
                button.classList.toggle('is-active', selected);
                button.setAttribute('aria-pressed', String(selected));
            });
            applyFilters();
            return;
        }

        if (event.target.closest('[data-preset-clear]')) {
            search.value = '';
            activeCategory = 'all';
            filters.forEach((button) => {
                const selected = button.dataset.presetCategory === 'all';
                button.classList.toggle('is-active', selected);
                button.setAttribute('aria-pressed', String(selected));
            });
            applyFilters();
            search.focus();
            return;
        }

        const selectButton = event.target.closest('[data-preset-select]');
        if (!selectButton || !form) {
            return;
        }

        const kind = catalog.dataset.presetKind;
        const values = kind === 'topic'
            ? {
                name: selectButton.dataset.presetName,
                brief: selectButton.dataset.presetBrief,
            }
            : {
                body: selectButton.dataset.presetBody,
                strength: selectButton.dataset.presetStrength,
            };
        const targets = Object.fromEntries(
            Object.keys(values).map((key) => [key, form.querySelector(`[data-preset-target="${key}"]`)]),
        );
        const hasUserText = kind === 'topic'
            ? ['name', 'brief'].some((key) => targets[key]?.value.trim())
            : Boolean(targets.body?.value.trim());
        const differs = Object.entries(values)
            .some(([key, value]) => targets[key]?.value !== value);

        if (
            hasUserText
            && differs
            && !window.confirm('Replace the text you entered with this preset?')
        ) {
            return;
        }

        Object.entries(values).forEach(([key, value]) => {
            if (!targets[key]) {
                return;
            }
            targets[key].value = value;
            targets[key].dispatchEvent(new Event('input', { bubbles: true }));
            targets[key].dispatchEvent(new Event('change', { bubbles: true }));
        });

        catalog.querySelectorAll('[data-preset-select]').forEach((button) => {
            const selected = button === selectButton;
            button.classList.toggle('is-selected', selected);
            button.setAttribute('aria-pressed', String(selected));
        });

        const label = selectButton.closest('[data-preset-card]')?.querySelector('h4')?.textContent.trim();
        status.textContent = `${label || 'Preset'} added to the form. Review or customize it before saving.`;

        const firstTarget = kind === 'topic' ? targets.name : targets.body;
        window.requestAnimationFrame(() => {
            firstTarget?.scrollIntoView({
                behavior: prefersReducedMotion() ? 'auto' : 'smooth',
                block: 'center',
            });
            firstTarget?.focus({ preventScroll: true });
        });
    });
});

const videoEmbedUrl = (provider, id) => {
    if (provider === 'youtube') {
        return `https://www.youtube-nocookie.com/embed/${encodeURIComponent(id)}`;
    }

    if (provider === 'vimeo') {
        return `https://player.vimeo.com/video/${encodeURIComponent(id)}?dnt=1`;
    }

    return null;
};

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-load-video]');

    if (!button) {
        return;
    }

    const container = button.closest('[data-video-provider]');
    const src = videoEmbedUrl(container?.dataset.videoProvider, container?.dataset.videoId);

    if (!container || !src) {
        return;
    }

    const iframe = document.createElement('iframe');
    iframe.src = src;
    iframe.title = container.dataset.videoTitle || 'Embedded story video';
    iframe.loading = 'lazy';
    iframe.referrerPolicy = 'strict-origin-when-cross-origin';
    iframe.allow = 'accelerometer; encrypted-media; picture-in-picture; fullscreen';
    iframe.sandbox = 'allow-scripts allow-same-origin allow-presentation';
    iframe.allowFullscreen = true;
    container.replaceChildren(iframe);
});

document.addEventListener('error', (event) => {
    const asset = event.target.closest?.('[data-media-asset]');

    if (!asset) {
        return;
    }

    asset.hidden = true;
    const fallback = asset.closest('.media-item')?.querySelector('[data-media-fallback]');
    if (fallback) {
        fallback.hidden = false;
    }
}, true);

const setFeedbackStatus = (form, message, isError = false) => {
    const status = form.querySelector('[data-form-status]');

    if (!status) {
        return;
    }

    status.textContent = message;
    status.classList.toggle('is-error', isError);
};

const validationMessage = (payload) => {
    if (!payload?.errors) {
        return payload?.message || 'Unable to save feedback.';
    }

    return Object.values(payload.errors).flat().join(' ');
};

document.addEventListener('submit', async (event) => {
    const form = event.target.closest(feedbackSelector);

    if (!form || !window.fetch) {
        return;
    }

    event.preventDefault();

    const button = form.querySelector('button[type="submit"]');
    const originalLabel = button?.textContent;

    if (button) {
        button.disabled = true;
        button.textContent = 'Saving…';
    }

    setFeedbackStatus(form, 'Saving…');

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            body: new FormData(form),
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });
        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            setFeedbackStatus(form, validationMessage(payload), true);
            return;
        }

        setFeedbackStatus(form, payload.message || 'Feedback saved.');

        const summaryLabel = form.closest('details')?.querySelector('summary > span:first-child');
        if (summaryLabel) {
            summaryLabel.textContent = 'Update your feedback';
        }
    } catch {
        setFeedbackStatus(form, 'Could not save. Check your connection and try again.', true);
    } finally {
        if (button) {
            button.disabled = false;
            button.textContent = originalLabel;
        }
    }
});

const liveFeed = document.querySelector('[data-live-feed]');

if (liveFeed) {
    const storyList = liveFeed.querySelector('[data-story-list]');
    const banner = liveFeed.querySelector('[data-new-stories]');
    const total = liveFeed.querySelector('[data-story-total]');
    const endpoint = liveFeed.dataset.updatesUrl;
    let cursor = {
        publishedAt: liveFeed.dataset.afterPublishedAt,
        id: liveFeed.dataset.afterId,
    };
    let pending = null;
    let polling = false;

    const setBanner = (payload) => {
        pending = payload;
        banner.textContent = `${payload.count}${payload.has_more ? '+' : ''} new ${payload.count === 1 ? 'story' : 'stories'} — show now`;
        banner.hidden = false;
    };

    const poll = async () => {
        if (
            polling
            || pending
            || document.visibilityState !== 'visible'
            || !navigator.onLine
            || !cursor.publishedAt
            || !cursor.id
        ) {
            return;
        }

        polling = true;

        try {
            const url = new URL(endpoint, window.location.origin);
            url.searchParams.set('after_published_at', cursor.publishedAt);
            url.searchParams.set('after_id', cursor.id);

            const response = await fetch(url, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                return;
            }

            const payload = await response.json();
            if (payload.count > 0 && payload.cursor) {
                setBanner(payload);
            }
        } catch {
            // Polling is best-effort. The next interval retries automatically.
        } finally {
            polling = false;
        }
    };

    banner.addEventListener('click', () => {
        if (!pending) {
            return;
        }

        const template = document.createElement('template');
        template.innerHTML = pending.html;
        const incoming = [...template.content.querySelectorAll('[data-story-id]')];
        const insertedStories = [];

        incoming.forEach((story) => {
            if (document.getElementById(story.id)) {
                story.remove();
                return;
            }

            insertedStories.push(story);
        });

        storyList.prepend(template.content);
        storyList.querySelector('[data-empty-state]')?.remove();

        if (total && insertedStories.length > 0) {
            total.textContent = String(
                (Number.parseInt(total.textContent, 10) || 0) + insertedStories.length,
            );
        }

        cursor = {
            publishedAt: pending.cursor.published_at,
            id: pending.cursor.id,
        };
        const hasMore = pending.has_more;
        pending = null;
        banner.hidden = true;

        const newestStory = insertedStories[0];
        if (newestStory) {
            newestStory.tabIndex = -1;
            newestStory.classList.add('is-new-story');
            window.requestAnimationFrame(() => {
                newestStory.scrollIntoView({
                    behavior: prefersReducedMotion() ? 'auto' : 'smooth',
                    block: 'start',
                });
                newestStory.focus({ preventScroll: true });
            });
            window.setTimeout(() => {
                newestStory.classList.remove('is-new-story');
            }, 2800);
        }

        if (hasMore) {
            window.setTimeout(poll, 100);
        }
    });

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            poll();
        }
    });
    window.addEventListener('online', poll);
    window.setInterval(poll, 30_000);
}
