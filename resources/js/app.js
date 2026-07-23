const feedbackSelector = '[data-feedback-form]';

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
        let inserted = 0;

        incoming.forEach((story) => {
            if (document.getElementById(story.id)) {
                story.remove();
                return;
            }

            inserted += 1;
        });

        storyList.prepend(template.content);
        storyList.querySelector('[data-empty-state]')?.remove();

        if (total && inserted > 0) {
            total.textContent = String((Number.parseInt(total.textContent, 10) || 0) + inserted);
        }

        cursor = {
            publishedAt: pending.cursor.published_at,
            id: pending.cursor.id,
        };
        const hasMore = pending.has_more;
        pending = null;
        banner.hidden = true;

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
