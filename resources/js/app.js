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

const copyPreparedPost = async (text) => {
    if (navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(text);
        return;
    }

    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.setAttribute('readonly', '');
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.append(textarea);
    textarea.select();
    const copied = document.execCommand('copy');
    textarea.remove();

    if (!copied) {
        throw new Error('Clipboard unavailable');
    }
};

const shareViaDevice = async (share) => {
    if (!navigator.share) {
        throw new Error('Native sharing unavailable');
    }

    await navigator.share({
        title: share.title,
        text: share.short_text,
        url: share.url,
    });
};

const platformLabels = {
    whatsapp: 'WhatsApp',
    x: 'X',
    facebook: 'Facebook',
    linkedin: 'LinkedIn',
    telegram: 'Telegram',
    reddit: 'Reddit',
    threads: 'Threads',
    bluesky: 'Bluesky',
    email: 'Email',
};

const shareDialog = document.querySelector('[data-share-dialog]');

if (shareDialog) {
    const loading = shareDialog.querySelector('[data-share-loading]');
    const ready = shareDialog.querySelector('[data-share-ready]');
    const platforms = shareDialog.querySelector('[data-share-platforms]');
    const nativeButton = shareDialog.querySelector('[data-share-native]');
    const copyButton = shareDialog.querySelector('[data-share-copy]');
    const revokeButton = shareDialog.querySelector('[data-share-revoke]');
    const retryButton = shareDialog.querySelector('[data-share-retry]');
    const status = shareDialog.querySelector('[data-share-status]');
    const instagramGuidance = shareDialog.querySelector('[data-share-instagram-guidance]');
    let activeTrigger = null;
    let activeShare = null;

    const setShareStatus = (message, isError = false) => {
        status.textContent = message;
        status.classList.toggle('is-error', isError);
    };

    const resetShareDialog = () => {
        activeShare = null;
        loading.hidden = false;
        ready.hidden = true;
        retryButton.hidden = true;
        platforms.replaceChildren();
        setShareStatus('');
    };

    const renderShare = (share) => {
        activeShare = share;
        loading.hidden = true;
        ready.hidden = false;
        retryButton.hidden = true;
        nativeButton.hidden = !navigator.share;
        instagramGuidance.textContent = navigator.share
            ? 'Choose Instagram or any other installed app from your device share sheet.'
            : 'Instagram requires a device share sheet. On this browser, use “Copy prepared post” instead.';
        platforms.replaceChildren(...Object.entries(share.platforms).map(([platform, url]) => {
            const link = document.createElement('a');
            link.href = url;
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
            link.dataset.sharePlatform = platform;
            link.textContent = platformLabels[platform] || platform;

            return link;
        }));
        activeTrigger?.classList.add('is-shared');
        setShareStatus('Your public story is ready to share.');
        (navigator.share ? nativeButton : copyButton).focus();
    };

    const prepareShare = async () => {
        if (!activeTrigger) {
            return;
        }

        resetShareDialog();

        try {
            const response = await fetch(activeTrigger.dataset.shareEndpoint, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
            });
            const payload = await response.json().catch(() => ({}));

            if (!response.ok || !payload.share) {
                throw new Error(payload.message || 'Unable to create the public link.');
            }

            renderShare(payload.share);
        } catch (error) {
            loading.hidden = true;
            retryButton.hidden = false;
            setShareStatus(
                navigator.onLine
                    ? error.message || 'Unable to prepare sharing. Try again.'
                    : 'You appear to be offline. Reconnect and try again.',
                true,
            );
            retryButton.focus();
        }
    };

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-share-story]');
        if (!trigger) {
            return;
        }

        activeTrigger = trigger;
        resetShareDialog();

        if (typeof shareDialog.showModal === 'function') {
            shareDialog.showModal();
        } else {
            shareDialog.setAttribute('open', '');
        }

        prepareShare();
    });

    shareDialog.querySelector('[data-share-close]').addEventListener('click', () => {
        shareDialog.close();
    });

    shareDialog.addEventListener('close', () => {
        activeTrigger?.focus();
    });

    retryButton.addEventListener('click', prepareShare);

    nativeButton.addEventListener('click', async () => {
        if (!activeShare) {
            return;
        }

        try {
            await shareViaDevice(activeShare);
            setShareStatus('Shared successfully.');
        } catch (error) {
            if (error.name === 'AbortError') {
                setShareStatus('Sharing canceled.');
                return;
            }

            setShareStatus('The device share sheet is unavailable. Use a shortcut or copy the prepared post.', true);
        }
    });

    copyButton.addEventListener('click', async () => {
        if (!activeShare) {
            return;
        }

        try {
            await copyPreparedPost(activeShare.full_text);
            setShareStatus('Prepared post copied.');
        } catch {
            setShareStatus('Clipboard access was denied. Use one of the share shortcuts instead.', true);
        }
    });

    revokeButton.addEventListener('click', async () => {
        if (
            !activeTrigger
            || !window.confirm('Disable this public link? Anyone using it will receive a not-found page.')
        ) {
            return;
        }

        revokeButton.disabled = true;

        try {
            const response = await fetch(activeTrigger.dataset.revokeEndpoint, {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
            });
            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                throw new Error(payload.message || 'Unable to disable the public link.');
            }

            activeTrigger.classList.remove('is-shared');
            const cardStatus = activeTrigger.closest('[data-story-id]')?.querySelector('[data-share-card-status]');
            if (cardStatus) {
                cardStatus.textContent = 'Public share link disabled.';
            }
            shareDialog.close();
        } catch (error) {
            setShareStatus(error.message || 'Unable to disable the public link.', true);
        } finally {
            revokeButton.disabled = false;
        }
    });
}

document.querySelectorAll('[data-public-share]').forEach((container) => {
    const share = {
        title: container.dataset.shareTitle,
        url: container.dataset.shareUrl,
        short_text: container.dataset.shareShortText,
        full_text: container.dataset.shareFullText,
    };
    const nativeButton = container.querySelector('[data-public-share-native]');
    const copyButton = container.querySelector('[data-public-share-copy]');
    const status = container.querySelector('[data-public-share-status]');
    const guidance = container.querySelector('[data-public-instagram-guidance]');

    nativeButton.hidden = !navigator.share;
    guidance.textContent = navigator.share
        ? 'Choose Instagram or another installed app from your device share sheet.'
        : 'Instagram requires a device share sheet. Copy the prepared post on this browser.';

    nativeButton.addEventListener('click', async () => {
        try {
            await shareViaDevice(share);
            status.textContent = 'Shared successfully.';
            status.classList.remove('is-error');
        } catch (error) {
            status.textContent = error.name === 'AbortError'
                ? 'Sharing canceled.'
                : 'The device share sheet is unavailable. Use a shortcut or copy the prepared post.';
            status.classList.toggle('is-error', error.name !== 'AbortError');
        }
    });

    copyButton.addEventListener('click', async () => {
        try {
            await copyPreparedPost(share.full_text);
            status.textContent = 'Prepared post copied.';
            status.classList.remove('is-error');
        } catch {
            status.textContent = 'Clipboard access was denied. Use one of the share shortcuts instead.';
            status.classList.add('is-error');
        }
    });
});

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
