/**
 * @copyright Copyright (C) 2026 Vasilyev Alexander. All rights reserved.
 * @license GNU General Public License version 2 or later; see LICENSE.txt
 */

(() => {
    'use strict';

    const padNumber = (value) => String(value).padStart(2, '0');

    const parseDate = (value) => {
        const parts = value.split('-');

        return {
            day: Number.parseInt(parts[0], 10),
            month: Number.parseInt(parts[1], 10),
        };
    };

    const requestedDateFromUrl = () => {
        const url = new URL(window.location.href);

        return url.searchParams.get('date');
    };

    const currentDate = () => {
        const now = new Date();

        return `${padNumber(now.getDate())}-${padNumber(now.getMonth() + 1)}`;
    };

    const navigateToDate = (date) => {
        const url = new URL(window.location.href);
        url.searchParams.set('date', date);
        window.location.href = url.toString();
    };

    const preserveDateInLanguageSwitcher = (date) => {
        document.querySelectorAll('.mod-languages a[href]').forEach((link) => {
            const url = new URL(link.href, window.location.origin);

            if (url.origin !== window.location.origin) {
                return;
            }

            url.searchParams.set('date', date);
            link.href = url.toString();
        });
    };

    const updateEditLink = (articleId) => {
        const editLink = document.querySelector(
            'a[href*="task=article.edit"][href*="a_id="]',
        );

        if (!editLink) {
            return;
        }

        const editUrl = new URL(editLink.href, window.location.origin);
        editUrl.searchParams.set('a_id', String(articleId));
        editLink.href = editUrl.toString();
    };

    const loadStylesheet = (href) => {
        if (!href || document.querySelector(`link[href="${href}"]`)) {
            return;
        }

        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = href;
        document.head.appendChild(link);
    };

    const loadScript = (src, isReady) => new Promise((resolve, reject) => {
        if (isReady()) {
            resolve();
            return;
        }

        const absoluteSrc = new URL(src, window.location.origin).href;
        const existingScript = Array.from(document.scripts).find(
            (script) => script.src === absoluteSrc,
        );

        const handleLoad = () => {
            if (isReady()) {
                resolve();
            } else {
                reject(new Error(`Script did not initialize: ${absoluteSrc}`));
            }
        };

        if (existingScript) {
            existingScript.remove();
        }

        const script = document.createElement('script');
        script.src = absoluteSrc;
        script.async = false;
        script.addEventListener('load', handleLoad, { once: true });
        script.addEventListener(
            'error',
            () => reject(new Error(`Script failed to load: ${absoluteSrc}`)),
            { once: true },
        );
        document.head.appendChild(script);
    });

    const ensureJCommentsLibraries = async (container) => {
        loadStylesheet(container.dataset.jcommentsStylesheet);

        await loadScript(
            container.dataset.jcommentsCore,
            () => typeof window.JComments === 'function',
        );
        await loadScript(
            container.dataset.jcommentsAjax,
            () => typeof window.jtajax !== 'undefined',
        );
    };

    const bindDynamicCommentsForm = (container) => {
        const form = container.querySelector('#comments-form');

        if (!form || form.dataset.bslSubmitBound === 'true') {
            return;
        }

        form.dataset.bslSubmitBound = 'true';
        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            try {
                await window.jcomments.saveCommentAsync();
                window.jcomments.showPage(
                    window.jcomments.oi,
                    window.jcomments.og,
                    0,
                );
            } catch (error) {
                console.error('BSL Daily Content could not save the comment:', error);
            }
        });
    };

    const bindDynamicCommentsControls = (container) => {
        if (container.dataset.bslCommentsControlsBound === 'true') {
            return;
        }

        container.dataset.bslCommentsControlsBound = 'true';
        container.addEventListener('click', (event) => {
            const target = event.target instanceof Element
                ? event.target.closest(
                    '#addcomments, #comments-form-captcha-image, '
                    + '#cmd-captcha-reload, .cmd-subscribe',
                )
                : null;

            if (!target || !container.contains(target)) {
                return;
            }

            if (
                target.matches('.cmd-subscribe')
                && typeof window.Joomla?.request === 'function'
            ) {
                event.preventDefault();
                const href = target.getAttribute('href');

                if (!href) {
                    return;
                }

                window.Joomla.request({
                    url: `${href}${href.includes('?') ? '&' : '?'}format=json`,
                    onSuccess: (response) => {
                        const result = JSON.parse(response);

                        if (result.success) {
                            target.setAttribute('href', result.data.href);
                            target.setAttribute('title', result.data.title);
                            target.innerHTML = '<span aria-hidden="true" '
                                + 'class="icon-mail icon-fw"></span> '
                                + result.data.title;
                            window.Joomla.renderMessages(
                                { message: [result.message] },
                                '.comments-list-footer',
                            );
                        } else {
                            window.Joomla.renderMessages(
                                { warning: [result.message] },
                                '.comments-list-footer',
                            );
                        }
                    },
                    onError: (xhr) => {
                        const result = JSON.parse(xhr.responseText);
                        window.Joomla.renderMessages(
                            { error: [result.message] },
                            '.comments-list-footer',
                        );
                    },
                });
                return;
            }

            if (!window.jcomments) {
                return;
            }

            if (target.id === 'addcomments') {
                event.preventDefault();
                window.jcomments.showForm(
                    target.dataset.object_id || window.jcomments.oi,
                    target.dataset.object_group || window.jcomments.og,
                    'comments-form-link',
                );
                return;
            }

            event.preventDefault();
            window.jcomments.clear('captcha');
        });
    };

    const initializeDynamicCommentsForm = (container, strings) => {
        if (
            typeof window.JCommentsEditor !== 'function'
            || typeof window.JCommentsForm !== 'function'
        ) {
            console.error(strings.unavailable || 'JComments form library is not available.');
            return;
        }

        const editor = new window.JCommentsEditor('comments-form-comment', true);

        editor.addButton('b', strings.bold, strings.formatPrompt);
        editor.addButton('i', strings.italic, strings.formatPrompt);
        editor.addButton('u', strings.underline, strings.formatPrompt);
        editor.addButton('s', strings.strike, strings.formatPrompt);
        editor.addButton('img', strings.image, strings.imagePrompt);
        editor.addButton('url', strings.link, strings.urlPrompt);
        editor.addButton('hide', strings.hidden, strings.hiddenPrompt);
        editor.addButton('quote', strings.quote, strings.quotePrompt);
        editor.addButton('list', strings.list, strings.listPrompt);

        editor.initSmiles(container.dataset.smiliesBase);
        editor.addSmile(':D', 'laugh.png');
        editor.addSmile(':lol:', 'grin.png');
        editor.addSmile(':-)', 'smile.png');
        editor.addSmile(';-)', 'wink.png');
        editor.addSmile('8)', 'cool.png');
        editor.addSmile(':-|', 'normal.png');
        editor.addSmile(':-*', 'whistling.png');
        editor.addSmile(':oops:', 'blush.png');
        editor.addSmile(':sad:', 'sad.png');
        editor.addSmile(':cry:', 'cry.png');
        editor.addSmile(':o', 'surprised.png');
        editor.addSmile(':-?', 'confused.png');
        editor.addSmile(':-x', 'sick.png');
        editor.addSmile(':eek:', 'shocked.png');
        editor.addSmile(':zzz', 'sleeping.png');
        editor.addSmile(':P', 'tongue.png');
        editor.addSmile(':roll:', 'rolleyes.gif');
        editor.addSmile(':sigh:', 'unsure.png');
        editor.addSmile('<3', 'love.png');
        editor.addSmile('>:(', 'angry.png');
        editor.addSmile(':doh:', 'doh.png');
        editor.addSmile(':dry:', 'dry.png');
        editor.addSmile('^_^', 'happy.png');
        editor.addSmile(':huh:', 'huh.png');
        editor.addSmile('=]', 'ironic.png');
        editor.addSmile(':kiss:', 'kiss.png');
        editor.addSmile(':ninja:', 'ninja.png');
        editor.addSmile(':rofl:', 'rofl.png');
        editor.addSmile('8-s', 'wacko.png');
        editor.addSmile('0:-)', 'innocent.png');

        window.jcomments.setForm(
            new window.JCommentsForm('comments-form', editor),
        );
    };

    const loadComments = async (container, articleId, strings) => {
        const commentsContainer = container.querySelector('[data-bsl-comments]');
        const listContainer = container.querySelector('#comments-list');
        const formContainer = container.querySelector('#comments-form-container');

        if (!commentsContainer || !listContainer || !formContainer) {
            return;
        }

        listContainer.replaceChildren();
        formContainer.replaceChildren();

        try {
            await ensureJCommentsLibraries(container);
        } catch (error) {
            commentsContainer.hidden = true;
            console.error(strings.unavailable || 'JComments library is not available.');
            console.error('BSL Daily Content could not load JComments assets:', error);
            return;
        }

        commentsContainer.hidden = false;
        bindDynamicCommentsControls(container);

        const observer = new MutationObserver(() => {
            if (!formContainer.querySelector('#comments-form')) {
                return;
            }

            observer.disconnect();

            window.setTimeout(() => {
                if (window.jcomments.form_id) {
                    bindDynamicCommentsForm(container);
                    return;
                }

                if (typeof window.JCommentsInitializeForm === 'function') {
                    window.JCommentsInitializeForm();
                    bindDynamicCommentsForm(container);
                    return;
                }

                initializeDynamicCommentsForm(container, strings);
                bindDynamicCommentsForm(container);
            }, 200);
        });

        observer.observe(formContainer, {
            childList: true,
            subtree: true,
        });

        window.jcomments = new window.JComments(
            articleId,
            'com_content',
            container.dataset.jcommentsEndpoint,
        );

        window.jcomments.setList('comments-list');
        window.jcomments.showPage(articleId, 'com_content', 0);
        window.jcomments.showForm(articleId, 'com_content', 'comments-form-container');
    };

    const appendArticleContent = (container, data) => {
        const heading = document.createElement('h2');
        heading.textContent = data.title || '';
        container.replaceChildren(heading);

        if (data.introtext) {
            container.insertAdjacentHTML('beforeend', data.introtext);
        }

        if (data.fulltext) {
            container.insertAdjacentHTML('beforeend', `<hr>${data.fulltext}`);
        }
    };

    const initialize = (container) => {
        const daySelect = container.querySelector('[data-bsl-day]');
        const monthSelect = container.querySelector('[data-bsl-month]');
        const showButton = container.querySelector('[data-bsl-show]');
        const previousButton = container.querySelector('[data-bsl-previous]');
        const nextButton = container.querySelector('[data-bsl-next]');
        const content = container.querySelector('[data-bsl-content]');
        const months = JSON.parse(container.dataset.months || '[]');
        const commentsStrings = JSON.parse(container.dataset.commentsStrings || '{}');

        if (!daySelect || !monthSelect || !showButton || !previousButton || !nextButton || !content) {
            return;
        }

        for (let day = 1; day <= 31; day++) {
            const option = document.createElement('option');
            option.value = String(day);
            option.textContent = String(day);
            daySelect.appendChild(option);
        }

        months.forEach((monthName, index) => {
            const option = document.createElement('option');
            option.value = String(index + 1);
            option.textContent = monthName;
            monthSelect.appendChild(option);
        });

        const selectedDate = requestedDateFromUrl() || currentDate();
        const parsedDate = parseDate(selectedDate);
        daySelect.value = String(parsedDate.day);
        monthSelect.value = String(parsedDate.month);
        preserveDateInLanguageSwitcher(selectedDate);

        const setNavigation = (button, date) => {
            button.hidden = !date;

            if (date) {
                button.dataset.date = date;
            } else {
                delete button.dataset.date;
            }
        };

        const loadDate = async (date) => {
            content.textContent = container.dataset.loadingMessage || '';

            try {
                const endpoint = new URL(container.dataset.endpoint, window.location.origin);
                endpoint.searchParams.set('date', date);
                endpoint.searchParams.set('format', 'json');

                const response = await fetch(endpoint.toString(), {
                    headers: { Accept: 'application/json' },
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const payload = await response.json();
                const data = Array.isArray(payload.data)
                    ? payload.data[0]
                    : payload;

                if (!data || typeof data.success !== 'boolean') {
                    throw new Error('Invalid BSL Daily Content response.');
                }

                if (!data.success) {
                    content.textContent = container.dataset.notFoundMessage || '';
                    setNavigation(previousButton, null);
                    setNavigation(nextButton, null);
                    const commentsContainer = container.querySelector('[data-bsl-comments]');

                    if (commentsContainer) {
                        commentsContainer.hidden = true;
                    }

                    return;
                }

                appendArticleContent(content, data);
                setNavigation(previousButton, data.prev_date || null);
                setNavigation(nextButton, data.next_date || null);

                if (data.article_id) {
                    updateEditLink(data.article_id);
                    await loadComments(container, data.article_id, commentsStrings);
                }
            } catch (error) {
                content.textContent = container.dataset.loadErrorMessage || '';
                console.error('BSL Daily Content request failed:', error);
            }
        };

        showButton.addEventListener('click', () => {
            navigateToDate(`${padNumber(daySelect.value)}-${padNumber(monthSelect.value)}`);
        });

        previousButton.addEventListener('click', () => {
            if (previousButton.dataset.date) {
                navigateToDate(previousButton.dataset.date);
            }
        });

        nextButton.addEventListener('click', () => {
            if (nextButton.dataset.date) {
                navigateToDate(nextButton.dataset.date);
            }
        });

        loadDate(selectedDate);
    };

    const initializeAll = () => {
        document.querySelectorAll('[data-bsl-daily-content]').forEach(initialize);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeAll, { once: true });
    } else {
        initializeAll();
    }
})();
