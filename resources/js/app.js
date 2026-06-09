import './bootstrap';

const errorSummary = document.querySelector('[data-error-summary]');

if (errorSummary) {
    errorSummary.focus();
}

const siteHeader = document.querySelector('.site-header');

if (siteHeader) {
    const isDesktop = window.matchMedia('(min-width: 981px)');
    let condensed = isDesktop.matches;

    const updateCondensed = () => {
        let shouldCondense;

        if (isDesktop.matches) {
            shouldCondense = true;
        } else {
            const scrollY = window.scrollY;
            shouldCondense = condensed ? scrollY > 40 : scrollY > 80;
        }

        condensed = shouldCondense;
        siteHeader.classList.toggle('is-condensed', condensed);
    };

    window.addEventListener('scroll', updateCondensed, { passive: true });
    isDesktop.addEventListener('change', updateCondensed);
    updateCondensed();
}

const navRoot = document.querySelector('[data-nav-root]');

if (navRoot) {
    const navToggle = navRoot.querySelector('[data-nav-toggle]');
    const navPanel = navRoot.querySelector('[data-nav-panel]');
    const mobileNav = window.matchMedia('(max-width: 980px)');

    const navItems = () => [navToggle, ...(navPanel?.querySelectorAll('a') ?? [])]
        .filter(Boolean);

    const setNavState = (open) => {
        navRoot.classList.toggle('is-nav-open', open);
        navToggle?.setAttribute('aria-expanded', open ? 'true' : 'false');
    };

    const openNav = () => {
        setNavState(true);

        if (mobileNav.matches) {
            navPanel?.querySelector('a')?.focus();
        }
    };

    const closeNav = ({ restoreFocus = false } = {}) => {
        const wasOpen = navRoot.classList.contains('is-nav-open');

        setNavState(false);

        if (restoreFocus && wasOpen && mobileNav.matches) {
            navToggle?.focus();
        }
    };

    navToggle?.addEventListener('click', () => {
        if (navRoot.classList.contains('is-nav-open')) {
            closeNav({ restoreFocus: true });
        } else {
            openNav();
        }
    });

    navPanel?.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => {
            if (mobileNav.matches) {
                closeNav();
            }
        });
    });

    const handleViewportChange = () => {
        if (!mobileNav.matches) {
            closeNav();
        }
    };

    if (typeof mobileNav.addEventListener === 'function') {
        mobileNav.addEventListener('change', handleViewportChange);
    } else if (typeof mobileNav.addListener === 'function') {
        mobileNav.addListener(handleViewportChange);
    }

    document.addEventListener('keydown', (event) => {
        if (!navRoot.classList.contains('is-nav-open') || !mobileNav.matches) {
            return;
        }

        if (event.key === 'Escape') {
            closeNav({ restoreFocus: true });
            return;
        }

        if (event.key === 'Tab') {
            const items = navItems();

            if (items.length === 0) {
                return;
            }

            const first = items[0];
            const last = items[items.length - 1];
            const active = document.activeElement;

            if (!navRoot.contains(active)) {
                event.preventDefault();
                first.focus();
                return;
            }

            if (event.shiftKey && active === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && active === last) {
                event.preventDefault();
                first.focus();
            }
        }
    });
}

const revealElements = document.querySelectorAll('[data-reveal]');

if (revealElements.length > 0) {
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    const showElement = (element) => {
        element.classList.add('is-visible');
    };

    const isNearViewport = (element) => {
        const rect = element.getBoundingClientRect();
        const viewportHeight = window.innerHeight || document.documentElement.clientHeight;

        return rect.top <= viewportHeight * 1.15;
    };

    if (reduceMotion.matches || !('IntersectionObserver' in window)) {
        revealElements.forEach(showElement);
    } else {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) {
                    return;
                }

                showElement(entry.target);
                observer.unobserve(entry.target);
            });
        }, {
            threshold: 0.01,
            rootMargin: '0px 0px 18% 0px',
        });

        revealElements.forEach((element) => {
            const delay = element.dataset.revealDelay;

            if (delay) {
                element.style.setProperty('--reveal-delay', `${delay}s`);
            }

            if (isNearViewport(element)) {
                showElement(element);
                return;
            }

            observer.observe(element);
        });
    }
}

const importedGalleryImages = document.querySelectorAll('.imported-gallery img, .wp-import-gallery img');

importedGalleryImages.forEach((node) => {
    if (!node.hasAttribute('tabindex')) {
        node.setAttribute('tabindex', '0');
    }

    if (!node.hasAttribute('role')) {
        node.setAttribute('role', 'button');
    }

    if (!node.hasAttribute('aria-label')) {
        node.setAttribute('aria-label', node.alt ? `View larger: ${node.alt}` : 'View larger image');
    }
});

const lightboxTriggers = document.querySelectorAll('[data-lightbox-trigger], .imported-gallery img, .wp-import-gallery img');

if (lightboxTriggers.length > 0) {
    const lightbox = document.createElement('div');
    lightbox.className = 'site-lightbox';
    lightbox.setAttribute('role', 'dialog');
    lightbox.setAttribute('aria-modal', 'true');
    lightbox.setAttribute('aria-label', 'Image viewer');
    lightbox.innerHTML = `
        <button class="site-lightbox__close" type="button" aria-label="Close image">&times;</button>
        <button class="site-lightbox__nav site-lightbox__nav--prev" type="button" aria-label="Previous image">&#8249;</button>
        <div class="site-lightbox__stage">
            <img class="site-lightbox__image" alt="">
        </div>
        <button class="site-lightbox__nav site-lightbox__nav--next" type="button" aria-label="Next image">&#8250;</button>
        <p class="site-lightbox__status visually-hidden" role="status" aria-live="polite"></p>
    `;

    document.body.appendChild(lightbox);

    const image = lightbox.querySelector('.site-lightbox__image');
    const closeButton = lightbox.querySelector('.site-lightbox__close');
    const prevButton = lightbox.querySelector('.site-lightbox__nav--prev');
    const nextButton = lightbox.querySelector('.site-lightbox__nav--next');
    const status = lightbox.querySelector('.site-lightbox__status');

    let items = [];
    let currentIndex = 0;
    let lastFocused = null;

    const focusableControls = () => [closeButton, prevButton, nextButton]
        .filter((control) => control && !control.hidden);

    const syncOrientation = () => {
        image.classList.remove('is-portrait', 'is-landscape', 'is-square');

        const { naturalWidth, naturalHeight } = image;

        if (!naturalWidth || !naturalHeight) {
            return;
        }

        if (naturalHeight > naturalWidth * 1.05) {
            image.classList.add('is-portrait');
            return;
        }

        if (naturalWidth > naturalHeight * 1.05) {
            image.classList.add('is-landscape');
            return;
        }

        image.classList.add('is-square');
    };

    const getGroupItems = (trigger) => {
        const explicitGroup = trigger.dataset.lightboxGroup;

        if (explicitGroup) {
            return Array.from(document.querySelectorAll(`[data-lightbox-trigger][data-lightbox-group="${explicitGroup}"]`));
        }

        const importedGallery = trigger.closest('.imported-gallery, .wp-import-gallery');

        if (importedGallery) {
            return Array.from(importedGallery.querySelectorAll('img'));
        }

        return [trigger];
    };

    const getItemData = (node) => {
        if (node.dataset.lightboxSrc) {
            return {
                src: node.dataset.lightboxSrc,
                alt: node.dataset.lightboxAlt || '',
            };
        }

        if (node.tagName === 'IMG') {
            return {
                src: node.currentSrc || node.src,
                alt: node.alt || '',
            };
        }

        const imageNode = node.querySelector('img');

        return {
            src: imageNode?.currentSrc || imageNode?.src || node.getAttribute('href') || '',
            alt: imageNode?.alt || '',
        };
    };

    const render = () => {
        const item = items[currentIndex];

        if (!item) {
            return;
        }

        image.classList.remove('is-portrait', 'is-landscape', 'is-square');
        image.src = item.src;
        image.alt = item.alt || '';

        if (image.complete) {
            syncOrientation();
        }

        prevButton.hidden = items.length <= 1;
        nextButton.hidden = items.length <= 1;

        if (status) {
            const position = items.length > 1 ? `Image ${currentIndex + 1} of ${items.length}` : 'Image';
            status.textContent = item.alt ? `${position}: ${item.alt}` : position;
        }
    };

    const open = (trigger) => {
        const groupItems = getGroupItems(trigger)
            .map(getItemData)
            .filter((item) => item.src);

        if (groupItems.length === 0) {
            return;
        }

        lastFocused = document.activeElement instanceof HTMLElement ? document.activeElement : null;
        items = groupItems;
        currentIndex = Math.max(0, getGroupItems(trigger).indexOf(trigger));
        render();
        lightbox.classList.add('is-open');
        document.body.classList.add('lightbox-open');
        closeButton?.focus();
    };

    const close = () => {
        if (!lightbox.classList.contains('is-open')) {
            return;
        }

        lightbox.classList.remove('is-open');
        document.body.classList.remove('lightbox-open');
        image.removeAttribute('src');
        image.alt = '';
        items = [];
        currentIndex = 0;

        if (status) {
            status.textContent = '';
        }

        if (lastFocused && document.contains(lastFocused)) {
            lastFocused.focus();
        }

        lastFocused = null;
    };

    const move = (direction) => {
        if (items.length <= 1) {
            return;
        }

        currentIndex = (currentIndex + direction + items.length) % items.length;
        render();
    };

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-lightbox-trigger], .imported-gallery img, .wp-import-gallery img');

        if (!trigger) {
            return;
        }

        event.preventDefault();
        open(trigger);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter' && event.key !== ' ' && event.key !== 'Spacebar') {
            return;
        }

        if (lightbox.classList.contains('is-open')) {
            return;
        }

        const trigger = event.target.closest('.imported-gallery img, .wp-import-gallery img');

        if (!trigger) {
            return;
        }

        event.preventDefault();
        open(trigger);
    });

    closeButton?.addEventListener('click', close);
    prevButton?.addEventListener('click', () => move(-1));
    nextButton?.addEventListener('click', () => move(1));
    image.addEventListener('load', syncOrientation);
    image.addEventListener('error', () => {
        image.classList.remove('is-portrait', 'is-landscape', 'is-square');
    });

    lightbox.addEventListener('click', (event) => {
        if (event.target === lightbox) {
            close();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (!lightbox.classList.contains('is-open')) {
            return;
        }

        if (event.key === 'Escape') {
            close();
            return;
        }

        if (event.key === 'ArrowLeft') {
            move(-1);
            return;
        }

        if (event.key === 'ArrowRight') {
            move(1);
            return;
        }

        if (event.key === 'Tab') {
            const controls = focusableControls();

            if (controls.length === 0) {
                event.preventDefault();
                return;
            }

            const first = controls[0];
            const last = controls[controls.length - 1];
            const active = document.activeElement;

            if (!lightbox.contains(active)) {
                event.preventDefault();
                first.focus();
                return;
            }

            if (event.shiftKey && active === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && active === last) {
                event.preventDefault();
                first.focus();
            }
        }
    });
}

const stickyCta = document.querySelector('[data-sticky-cta]');

if (stickyCta && 'IntersectionObserver' in window) {
    const mobileView = window.matchMedia('(max-width: 980px)');
    const pageClosing = document.querySelector('.page-closing, .home-inline-inquiry');
    let pastHero = false;
    let closingVisible = false;

    const update = () => {
        if (!mobileView.matches) {
            stickyCta.classList.remove('is-visible');
            return;
        }

        stickyCta.classList.toggle('is-visible', pastHero && !closingVisible);
    };

    if (pageClosing) {
        const closingObserver = new IntersectionObserver((entries) => {
            closingVisible = entries[0].isIntersecting;
            update();
        }, { threshold: 0.05 });

        closingObserver.observe(pageClosing);
    }

    const onScroll = () => {
        pastHero = window.scrollY > window.innerHeight * 0.5;
        update();
    };

    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    if (typeof mobileView.addEventListener === 'function') {
        mobileView.addEventListener('change', update);
    }
}

const focalPickers = document.querySelectorAll('[data-focal-picker]');

if (focalPickers.length > 0) {
    const clamp = (value) => Math.min(1, Math.max(0, value));
    const round = (value) => Math.round(value * 10000) / 10000;

    focalPickers.forEach((picker) => {
        const preview = picker.querySelector('[data-focal-preview]');
        const handle = picker.querySelector('[data-focal-handle]');
        const form = picker.closest('form');
        const xInput = form?.querySelector('[data-focal-x-input]');
        const yInput = form?.querySelector('[data-focal-y-input]');

        if (!preview || !handle || !xInput || !yInput) {
            return;
        }

        let dragging = false;

        const render = (x, y) => {
            const nextX = round(clamp(x));
            const nextY = round(clamp(y));

            picker.style.setProperty('--focal-x', `${nextX * 100}%`);
            picker.style.setProperty('--focal-y', `${nextY * 100}%`);
            preview.style.objectPosition = `${nextX * 100}% ${nextY * 100}%`;
            xInput.value = nextX.toFixed(4);
            yInput.value = nextY.toFixed(4);
        };

        const setFromPointer = (event) => {
            const rect = picker.getBoundingClientRect();
            const x = (event.clientX - rect.left) / rect.width;
            const y = (event.clientY - rect.top) / rect.height;

            render(x, y);
        };

        render(
            parseFloat(xInput.value || picker.dataset.focalX || '0.5'),
            parseFloat(yInput.value || picker.dataset.focalY || '0.25'),
        );

        picker.addEventListener('pointerdown', (event) => {
            dragging = true;
            picker.setPointerCapture(event.pointerId);
            setFromPointer(event);
        });

        picker.addEventListener('pointermove', (event) => {
            if (!dragging) {
                return;
            }

            setFromPointer(event);
        });

        picker.addEventListener('pointerup', (event) => {
            dragging = false;

            if (picker.hasPointerCapture(event.pointerId)) {
                picker.releasePointerCapture(event.pointerId);
            }
        });

        picker.addEventListener('pointercancel', () => {
            dragging = false;
        });

        xInput.addEventListener('input', () => {
            render(parseFloat(xInput.value || '0.5'), parseFloat(yInput.value || '0.25'));
        });

        yInput.addEventListener('input', () => {
            render(parseFloat(xInput.value || '0.5'), parseFloat(yInput.value || '0.25'));
        });
    });
}

// ── Venue autocomplete ──
const venueWidget = document.querySelector('[data-venue-autocomplete]');

if (venueWidget) {
    const input = venueWidget.querySelector('[data-venue-input]');
    const hiddenId = venueWidget.querySelector('[data-venue-id]');
    const list = venueWidget.querySelector('[data-venue-list]');
    const searchUrl = venueWidget.dataset.venueSearchUrl;

    let debounceTimer = null;
    let activeIndex = -1;
    let items = [];

    const render = () => {
        list.innerHTML = '';
        activeIndex = -1;

        if (items.length === 0) {
            list.hidden = true;

            return;
        }

        items.forEach((venue, index) => {
            const li = document.createElement('li');
            li.className = 'venue-autocomplete__item';
            li.dataset.index = index;
            li.textContent = venue.name;

            if (venue.city) {
                const detail = document.createElement('span');
                detail.className = 'venue-autocomplete__detail';
                detail.textContent = venue.city + (venue.state ? ', ' + venue.state : '');
                li.append(' ', detail);
            }

            li.addEventListener('pointerdown', (event) => {
                event.preventDefault();
                select(index);
            });

            list.appendChild(li);
        });

        list.hidden = false;
    };

    const select = (index) => {
        const venue = items[index];

        if (!venue) {
            return;
        }

        input.value = venue.name;
        hiddenId.value = venue.id;
        items = [];
        list.hidden = true;
    };

    const highlight = (index) => {
        const children = list.children;
        const prev = children[activeIndex];

        if (prev) {
            prev.classList.remove('is-active');
        }

        activeIndex = index;
        const next = children[activeIndex];

        if (next) {
            next.classList.add('is-active');
            next.scrollIntoView({ block: 'nearest' });
        }
    };

    const fetchVenues = (query) => {
        fetch(searchUrl + '?q=' + encodeURIComponent(query))
            .then((response) => response.json())
            .then((data) => {
                items = data;
                render();
            })
            .catch(() => {
                items = [];
                render();
            });
    };

    input.addEventListener('input', () => {
        hiddenId.value = '';

        clearTimeout(debounceTimer);

        const query = input.value.trim();

        if (query.length < 2) {
            items = [];
            render();

            return;
        }

        debounceTimer = setTimeout(() => fetchVenues(query), 200);
    });

    input.addEventListener('keydown', (event) => {
        if (list.hidden || items.length === 0) {
            return;
        }

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            highlight(activeIndex < items.length - 1 ? activeIndex + 1 : 0);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            highlight(activeIndex > 0 ? activeIndex - 1 : items.length - 1);
        } else if (event.key === 'Enter' && activeIndex >= 0) {
            event.preventDefault();
            select(activeIndex);
        } else if (event.key === 'Escape') {
            items = [];
            render();
        }
    });

    input.addEventListener('blur', () => {
        setTimeout(() => {
            items = [];
            render();
        }, 150);
    });
}

// ── Media picker ──

/**
 * Build a single library tile shared by the single- and multi-select pickers.
 *
 * The square is reserved by the in-flow <img>'s own `aspect-ratio` (see the
 * CSS), so each tile gets a real height from the bottom up rather than relying
 * on a definite height handed down from the grid — the chain iOS Safari kept
 * collapsing to slivers. The photo fades in over a shimmer skeleton once it
 * loads, and tiles stagger in for a livelier "feed loading" feel.
 */
const buildMediaTile = (media, { multi = false, selected = false, index = 0 } = {}) => {
    const tile = document.createElement('button');
    tile.type = 'button';
    tile.className = 'media-picker-tile';
    tile.style.setProperty('--enter-delay', `${Math.min(index, 16) * 28}ms`);

    if (multi) {
        tile.dataset.multiTile = '';

        if (selected) {
            tile.classList.add('is-selected');
        }
    } else {
        tile.dataset.mediaTile = '';
    }

    tile.dataset.id = String(media.id);
    tile.dataset.filename = media.filename || '';
    tile.dataset.url = media.url || '';
    tile.dataset.altText = media.alt_text || '';

    const figure = document.createElement('span');
    figure.className = 'media-picker-tile__image';

    if (media.url) {
        const img = document.createElement('img');
        img.loading = 'lazy';
        img.decoding = 'async';
        img.alt = media.alt_text || media.filename || '';

        const reveal = () => figure.classList.add('is-loaded');
        img.addEventListener('load', reveal);
        img.addEventListener('error', () => figure.classList.add('is-error'));
        img.src = media.url;

        if (img.complete && img.naturalWidth > 0) {
            reveal();
        }

        figure.appendChild(img);
    } else {
        figure.classList.add('is-error');
    }

    const idBadge = document.createElement('span');
    idBadge.className = 'media-picker-tile__id';
    idBadge.textContent = `#${media.id}`;
    figure.appendChild(idBadge);

    if (multi) {
        const check = document.createElement('span');
        check.className = 'media-picker-tile__check';
        check.textContent = '✓';
        figure.appendChild(check);
    }

    tile.appendChild(figure);

    return tile;
};

const mediaPickers = document.querySelectorAll('[data-media-picker]');

if (mediaPickers.length > 0) {
    let modal = null;
    let modalSearch = null;
    let modalGrid = null;
    let modalStatus = null;
    let modalLoadMore = null;
    let activePicker = null;
    let currentPage = 1;
    let lastPage = 1;
    let isLoading = false;
    let searchDebounce = null;
    let lastFocusedTrigger = null;

    const ensureModal = () => {
        if (modal) {
            return;
        }

        modal = document.createElement('div');
        modal.className = 'media-picker-modal';
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.setAttribute('aria-label', 'Choose media');
        modal.hidden = true;
        modal.innerHTML = `
            <div class="media-picker-modal__backdrop" data-modal-close></div>
            <div class="media-picker-modal__panel">
                <header class="media-picker-modal__header">
                    <div class="media-picker-modal__heading">
                        <p class="eyebrow">Media library</p>
                        <h2>Choose an image</h2>
                    </div>
                    <button type="button" class="media-picker-modal__close" data-modal-close aria-label="Close">&times;</button>
                </header>
                <div class="media-picker-modal__toolbar">
                    <input type="search" class="media-picker-modal__search" placeholder="Search by filename, alt text, or ID…" autocomplete="off">
                    <span class="media-picker-modal__status" aria-live="polite"></span>
                </div>
                <div class="media-picker-modal__grid" tabindex="0"></div>
                <footer class="media-picker-modal__footer">
                    <button type="button" class="cta-secondary media-picker-modal__more" hidden>Load more</button>
                </footer>
            </div>
        `;

        document.body.appendChild(modal);

        modalSearch = modal.querySelector('.media-picker-modal__search');
        modalGrid = modal.querySelector('.media-picker-modal__grid');
        modalStatus = modal.querySelector('.media-picker-modal__status');
        modalLoadMore = modal.querySelector('.media-picker-modal__more');

        modal.querySelectorAll('[data-modal-close]').forEach((node) => {
            node.addEventListener('click', closeModal);
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !modal.hidden) {
                closeModal();
            }
        });

        modalSearch.addEventListener('input', () => {
            clearTimeout(searchDebounce);
            searchDebounce = setTimeout(() => loadResults(true), 220);
        });

        modalLoadMore.addEventListener('click', () => loadResults(false));

        modalGrid.addEventListener('click', (event) => {
            const tile = event.target.closest('[data-media-tile]');

            if (!tile) {
                return;
            }

            applySelection({
                id: tile.dataset.id,
                filename: tile.dataset.filename,
                url: tile.dataset.url,
                altText: tile.dataset.altText,
            });
        });
    };

    const setLoading = (loading) => {
        isLoading = loading;
        modalLoadMore.disabled = loading;

        if (loading) {
            modalLoadMore.textContent = 'Loading…';
        } else {
            modalLoadMore.textContent = 'Load more';
        }
    };

    const renderTiles = (items, append) => {
        if (!append) {
            modalGrid.innerHTML = '';
        }

        if (items.length === 0 && !append) {
            const empty = document.createElement('p');
            empty.className = 'media-picker-modal__empty';
            empty.textContent = 'No media matched your search.';
            modalGrid.appendChild(empty);

            return;
        }

        items.forEach((media, index) => {
            modalGrid.appendChild(buildMediaTile(media, { index }));
        });
    };

    const loadResults = (reset) => {
        if (!activePicker || isLoading) {
            return;
        }

        const endpoint = activePicker.dataset.mediaPickerEndpoint;

        if (!endpoint) {
            return;
        }

        if (reset) {
            currentPage = 1;
            lastPage = 1;
            modalStatus.textContent = 'Searching…';
            modalGrid.scrollTop = 0;
        } else {
            currentPage += 1;
        }

        const params = new URLSearchParams();
        const term = modalSearch.value.trim();

        if (term !== '') {
            params.set('q', term);
        }

        params.set('page', String(currentPage));

        setLoading(true);

        fetch(`${endpoint}?${params.toString()}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Request failed');
                }

                return response.json();
            })
            .then((payload) => {
                lastPage = payload.last_page || 1;
                renderTiles(payload.data || [], !reset);
                modalLoadMore.hidden = !payload.has_more;
                modalStatus.textContent = `${payload.total} result${payload.total === 1 ? '' : 's'}`;
            })
            .catch(() => {
                modalStatus.textContent = 'Could not load media. Try again.';
            })
            .finally(() => {
                setLoading(false);
            });
    };

    const openModal = (picker) => {
        ensureModal();
        activePicker = picker;
        lastFocusedTrigger = picker.querySelector('[data-media-picker-open]');
        modal.hidden = false;
        document.body.classList.add('media-picker-modal-open');
        modalSearch.value = '';
        modalGrid.innerHTML = '';
        modalLoadMore.hidden = true;
        modalStatus.textContent = '';
        loadResults(true);
        setTimeout(() => modalSearch.focus(), 50);
    };

    function closeModal() {
        if (!modal || modal.hidden) {
            return;
        }

        modal.hidden = true;
        document.body.classList.remove('media-picker-modal-open');
        activePicker = null;
        lastFocusedTrigger?.focus();
    }

    const applySelection = (media) => {
        if (!activePicker) {
            return;
        }

        const input = activePicker.querySelector('[data-media-picker-value]');
        const surface = activePicker.querySelector('[data-media-picker-surface]');
        const preview = activePicker.querySelector('[data-media-picker-preview]');
        const filenameNode = activePicker.querySelector('[data-media-picker-filename]');
        const idNode = activePicker.querySelector('[data-media-picker-id]');
        const clearButton = activePicker.querySelector('[data-media-picker-clear]');

        if (input) {
            input.value = media.id;
        }

        surface?.classList.remove('is-empty');

        if (preview) {
            preview.innerHTML = '';

            if (media.url) {
                const img = document.createElement('img');
                img.src = media.url;
                img.alt = media.altText || media.filename || '';
                img.loading = 'lazy';
                img.dataset.mediaPickerPreviewImage = '';
                preview.appendChild(img);
            } else {
                const span = document.createElement('span');
                span.className = 'media-picker__placeholder';
                span.dataset.mediaPickerPlaceholder = '';
                span.textContent = 'No image selected';
                preview.appendChild(span);
            }
        }

        if (filenameNode) {
            filenameNode.textContent = media.filename || '—';
        }

        if (idNode) {
            idNode.textContent = media.id ? `#${media.id}` : '';
        }

        if (clearButton) {
            clearButton.hidden = false;
        }

        closeModal();
    };

    mediaPickers.forEach((picker) => {
        const openButton = picker.querySelector('[data-media-picker-open]');
        const clearButton = picker.querySelector('[data-media-picker-clear]');

        openButton?.addEventListener('click', () => openModal(picker));

        clearButton?.addEventListener('click', () => {
            const input = picker.querySelector('[data-media-picker-value]');
            const surface = picker.querySelector('[data-media-picker-surface]');
            const preview = picker.querySelector('[data-media-picker-preview]');
            const filenameNode = picker.querySelector('[data-media-picker-filename]');
            const idNode = picker.querySelector('[data-media-picker-id]');

            if (input) {
                input.value = '';
            }

            surface?.classList.add('is-empty');

            if (preview) {
                preview.innerHTML = '<span class="media-picker__placeholder" data-media-picker-placeholder>No image selected</span>';
            }

            if (filenameNode) {
                filenameNode.textContent = '—';
            }

            if (idNode) {
                idNode.textContent = '';
            }

            clearButton.hidden = true;
        });
    });
}

// ── Story / journal gallery ──
const storyGalleries = document.querySelectorAll('[data-story-gallery]');

if (storyGalleries.length > 0) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

    const jsonFetch = async (url, options = {}) => {
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                ...(options.body && !(options.body instanceof FormData) ? { 'Content-Type': 'application/json' } : {}),
                ...(options.headers || {}),
            },
            ...options,
        });

        if (!response.ok) {
            const error = new Error('Request failed');
            error.status = response.status;
            throw error;
        }

        if (response.status === 204) {
            return null;
        }

        return response.json();
    };

    // ── Multi-select picker modal (shared across all galleries) ──
    let multiModal = null;
    let multiSearch = null;
    let multiGrid = null;
    let multiStatus = null;
    let multiLoadMore = null;
    let multiConfirm = null;
    let multiSelected = new Map(); // id -> media
    let multiPage = 1;
    let multiLoading = false;
    let multiResolver = null;
    let multiSearchDebounce = null;
    let multiEndpoint = null;

    const ensureMultiModal = () => {
        if (multiModal) {
            return;
        }

        multiModal = document.createElement('div');
        multiModal.className = 'media-picker-modal';
        multiModal.setAttribute('role', 'dialog');
        multiModal.setAttribute('aria-modal', 'true');
        multiModal.setAttribute('aria-label', 'Add photos from library');
        multiModal.hidden = true;
        multiModal.innerHTML = `
            <div class="media-picker-modal__backdrop" data-multi-close></div>
            <div class="media-picker-modal__panel is-multi">
                <header class="media-picker-modal__header">
                    <div class="media-picker-modal__heading">
                        <p class="eyebrow">Library</p>
                        <h2>Add photos to gallery</h2>
                    </div>
                    <button type="button" class="media-picker-modal__close" data-multi-close aria-label="Close">&times;</button>
                </header>
                <div class="media-picker-modal__toolbar">
                    <input type="search" class="media-picker-modal__search" placeholder="Search by filename, alt text, or ID…" autocomplete="off">
                    <span class="media-picker-modal__status" aria-live="polite"></span>
                </div>
                <div class="media-picker-modal__grid" tabindex="0"></div>
                <footer class="media-picker-modal__footer">
                    <button type="button" class="cta-secondary media-picker-modal__more" hidden>Load more</button>
                    <button type="button" class="cta media-picker-modal__confirm" disabled>Add 0 photos</button>
                </footer>
            </div>
        `;

        document.body.appendChild(multiModal);

        multiSearch = multiModal.querySelector('.media-picker-modal__search');
        multiGrid = multiModal.querySelector('.media-picker-modal__grid');
        multiStatus = multiModal.querySelector('.media-picker-modal__status');
        multiLoadMore = multiModal.querySelector('.media-picker-modal__more');
        multiConfirm = multiModal.querySelector('.media-picker-modal__confirm');

        multiModal.querySelectorAll('[data-multi-close]').forEach((node) => {
            node.addEventListener('click', closeMulti);
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && multiModal && !multiModal.hidden) {
                closeMulti();
            }
        });

        multiSearch.addEventListener('input', () => {
            clearTimeout(multiSearchDebounce);
            multiSearchDebounce = setTimeout(() => loadMulti(true), 220);
        });

        multiLoadMore.addEventListener('click', () => loadMulti(false));

        multiConfirm.addEventListener('click', () => {
            if (multiSelected.size === 0) {
                return;
            }

            const result = Array.from(multiSelected.values());
            const resolver = multiResolver;
            closeMulti();
            resolver?.(result);
        });

        multiGrid.addEventListener('click', (event) => {
            const tile = event.target.closest('[data-multi-tile]');

            if (!tile) {
                return;
            }

            const id = Number(tile.dataset.id);

            if (multiSelected.has(id)) {
                multiSelected.delete(id);
                tile.classList.remove('is-selected');
            } else {
                multiSelected.set(id, {
                    id,
                    filename: tile.dataset.filename || '',
                    url: tile.dataset.url || '',
                    alt_text: tile.dataset.altText || '',
                });
                tile.classList.add('is-selected');
            }

            updateMultiConfirm();
        });
    };

    const updateMultiConfirm = () => {
        const count = multiSelected.size;
        multiConfirm.disabled = count === 0;
        multiConfirm.textContent = `Add ${count} photo${count === 1 ? '' : 's'}`;
    };

    const renderMultiTiles = (items, append) => {
        if (!append) {
            multiGrid.innerHTML = '';
        }

        if (items.length === 0 && !append) {
            const empty = document.createElement('p');
            empty.className = 'media-picker-modal__empty';
            empty.textContent = 'No media matched your search.';
            multiGrid.appendChild(empty);

            return;
        }

        items.forEach((media, index) => {
            multiGrid.appendChild(buildMediaTile(media, {
                multi: true,
                selected: multiSelected.has(media.id),
                index,
            }));
        });
    };

    const loadMulti = (reset) => {
        if (!multiEndpoint || multiLoading) {
            return;
        }

        if (reset) {
            multiPage = 1;
            multiGrid.scrollTop = 0;
            multiStatus.textContent = 'Searching…';
        } else {
            multiPage += 1;
        }

        const params = new URLSearchParams();
        const term = multiSearch.value.trim();

        if (term !== '') {
            params.set('q', term);
        }

        params.set('page', String(multiPage));

        multiLoading = true;
        multiLoadMore.disabled = true;
        multiLoadMore.textContent = 'Loading…';

        fetch(`${multiEndpoint}?${params.toString()}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Request failed');
                }

                return response.json();
            })
            .then((payload) => {
                renderMultiTiles(payload.data || [], !reset);
                multiLoadMore.hidden = !payload.has_more;
                multiStatus.textContent = `${payload.total} result${payload.total === 1 ? '' : 's'}`;
            })
            .catch(() => {
                multiStatus.textContent = 'Could not load media. Try again.';
            })
            .finally(() => {
                multiLoading = false;
                multiLoadMore.disabled = false;
                multiLoadMore.textContent = 'Load more';
            });
    };

    const openMulti = ({ endpoint, excludeIds }) => new Promise((resolve) => {
        ensureMultiModal();
        multiEndpoint = endpoint;
        multiSelected = new Map();
        multiResolver = resolve;
        multiSearch.value = '';
        multiGrid.innerHTML = '';
        multiStatus.textContent = '';
        multiLoadMore.hidden = true;
        updateMultiConfirm();
        multiModal.hidden = false;
        document.body.classList.add('media-picker-modal-open');
        loadMulti(true);
        setTimeout(() => multiSearch.focus(), 50);
    });

    function closeMulti() {
        if (!multiModal || multiModal.hidden) {
            return;
        }

        multiModal.hidden = true;
        document.body.classList.remove('media-picker-modal-open');

        if (multiResolver) {
            const resolver = multiResolver;
            multiResolver = null;
            resolver([]);
        }
    }

    // ── Gallery instance ──
    const setupGallery = (root) => {
        const grid = root.querySelector('[data-story-grid]');
        const emptyMessage = root.querySelector('[data-story-empty]');
        const status = root.querySelector('[data-story-status]');
        const countNode = root.querySelector('[data-story-count]');
        const heroStat = root.querySelector('[data-story-hero-stat]');
        const heroInput = document.querySelector(`form input[name="${root.dataset.heroInput || 'hero_media_id'}"]`);
        const addButton = root.querySelector('[data-story-add]');

        const attachUrl = root.dataset.attachUrl;
        const reorderUrl = root.dataset.reorderUrl;
        const detachUrlPattern = root.dataset.detachUrlPattern;
        const heroUrlPattern = root.dataset.heroUrlPattern;
        const pickerUrl = root.dataset.pickerUrl;

        const flash = (message, isError = false) => {
            if (!status) {
                return;
            }

            status.textContent = message;
            status.hidden = false;
            status.classList.toggle('story-gallery__status--error', isError);

            clearTimeout(flash._timer);
            flash._timer = setTimeout(() => {
                status.hidden = true;
            }, 3200);
        };

        const renderTile = (item) => {
            const li = document.createElement('li');
            li.className = 'story-tile';

            if (item.is_hero) {
                li.classList.add('is-hero');
            }

            li.dataset.storyTile = '';
            li.dataset.mediaId = String(item.id);
            li.setAttribute('draggable', 'true');

            li.innerHTML = `
                <span class="story-tile__handle" aria-label="Drag to reorder" data-story-handle>⋮⋮</span>
                ${item.is_hero ? '<span class="story-tile__hero-badge" aria-label="Hero photo">★ Hero</span>' : ''}
                <a class="story-tile__photo" href="${item.edit_url}" tabindex="-1" aria-hidden="true">
                    ${item.url ? `<img src="${item.url}" alt="${escapeAttr(item.alt_text || item.filename || '')}" loading="lazy">` : '<span class="story-tile__placeholder">No preview</span>'}
                </a>
                <div class="story-tile__actions">
                    ${item.is_hero ? '' : '<button type="button" class="story-tile__action" data-story-hero title="Make hero">★</button>'}
                    <a href="${item.edit_url}" class="story-tile__action" title="Open in library">↗</a>
                    <button type="button" class="story-tile__action story-tile__action--danger" data-story-remove title="Remove from gallery">✕</button>
                </div>
            `;

            attachTileHandlers(li);

            return li;
        };

        const escapeAttr = (value) => String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('"', '&quot;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;');

        const applyState = (payload) => {
            grid.innerHTML = '';

            if (!payload.items || payload.items.length === 0) {
                grid.classList.add('is-empty');
                emptyMessage?.removeAttribute('hidden');
            } else {
                grid.classList.remove('is-empty');
                emptyMessage?.setAttribute('hidden', '');

                payload.items.forEach((item) => {
                    grid.appendChild(renderTile(item));
                });
            }

            if (countNode) {
                countNode.textContent = String(payload.items?.length ?? 0);
            }

            if (heroInput) {
                heroInput.value = payload.hero_media_id ? String(payload.hero_media_id) : '';
            }

            if (heroStat) {
                const valueNode = heroStat.querySelector('.story-stat__value');

                if (valueNode) {
                    if (payload.hero_media_id) {
                        valueNode.textContent = '★';
                        heroStat.classList.add('story-stat--ok');
                        heroStat.classList.remove('story-stat--alert');
                    } else {
                        valueNode.textContent = '○';
                        heroStat.classList.add('story-stat--alert');
                        heroStat.classList.remove('story-stat--ok');
                    }
                }
            }
        };

        const collectIds = () => Array.from(grid.querySelectorAll('[data-story-tile]'))
            .map((node) => Number(node.dataset.mediaId))
            .filter((id) => Number.isFinite(id));

        const persistOrder = () => {
            jsonFetch(reorderUrl, {
                method: 'PATCH',
                body: JSON.stringify({ media_ids: collectIds() }),
            })
                .then(applyState)
                .catch(() => flash('Could not save the new order.', true));
        };

        const handleDetach = (tile) => {
            const id = Number(tile.dataset.mediaId);

            if (!confirm('Remove this photo from the gallery?')) {
                return;
            }

            jsonFetch(detachUrlPattern.replace('__id__', String(id)), { method: 'DELETE' })
                .then((payload) => {
                    applyState(payload);
                    flash('Photo removed.');
                })
                .catch(() => flash('Could not remove photo.', true));
        };

        const handleHero = (tile) => {
            const id = Number(tile.dataset.mediaId);

            jsonFetch(heroUrlPattern.replace('__id__', String(id)), { method: 'POST' })
                .then((payload) => {
                    applyState(payload);
                    flash('Hero photo updated.');
                })
                .catch(() => flash('Could not set hero photo.', true));
        };

        // ── Drag-to-reorder ──
        let dragSrc = null;

        const attachTileHandlers = (tile) => {
            tile.addEventListener('dragstart', (event) => {
                dragSrc = tile;
                tile.classList.add('is-dragging');
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', tile.dataset.mediaId || '');
            });

            tile.addEventListener('dragend', () => {
                tile.classList.remove('is-dragging');
                grid.querySelectorAll('.is-drop-target').forEach((n) => n.classList.remove('is-drop-target'));
                dragSrc = null;
            });

            tile.addEventListener('dragover', (event) => {
                if (!dragSrc || dragSrc === tile) {
                    return;
                }

                event.preventDefault();
                tile.classList.add('is-drop-target');
            });

            tile.addEventListener('dragleave', () => {
                tile.classList.remove('is-drop-target');
            });

            tile.addEventListener('drop', (event) => {
                event.preventDefault();
                tile.classList.remove('is-drop-target');

                if (!dragSrc || dragSrc === tile) {
                    return;
                }

                const rect = tile.getBoundingClientRect();
                const isAfter = event.clientX > rect.left + rect.width / 2 || event.clientY > rect.top + rect.height / 2;

                if (isAfter) {
                    tile.after(dragSrc);
                } else {
                    tile.before(dragSrc);
                }

                persistOrder();
            });

            tile.querySelector('[data-story-remove]')?.addEventListener('click', () => handleDetach(tile));
            tile.querySelector('[data-story-hero]')?.addEventListener('click', () => handleHero(tile));
        };

        grid.querySelectorAll('[data-story-tile]').forEach(attachTileHandlers);

        addButton?.addEventListener('click', async () => {
            const existingIds = collectIds();
            const selection = await openMulti({ endpoint: pickerUrl, excludeIds: existingIds });

            if (selection.length === 0) {
                return;
            }

            const fresh = selection.filter((m) => !existingIds.includes(m.id));

            if (fresh.length === 0) {
                flash('Those photos are already in this gallery.');
                return;
            }

            jsonFetch(attachUrl, {
                method: 'POST',
                body: JSON.stringify({ media_ids: fresh.map((m) => m.id) }),
            })
                .then((payload) => {
                    applyState(payload);
                    flash(`Added ${fresh.length} photo${fresh.length === 1 ? '' : 's'}.`);
                })
                .catch(() => flash('Could not attach the selected photos.', true));
        });
    };

    storyGalleries.forEach(setupGallery);
}

// ── Multi-step inquiry form ──
const multistepForm = document.querySelector('[data-multistep-form]');

if (multistepForm) {
    const stepper = document.querySelector('[data-form-stepper]');
    const steps = Array.from(multistepForm.querySelectorAll('[data-step]'));
    const indicators = stepper
        ? Array.from(stepper.querySelectorAll('[data-step-indicator]'))
        : [];
    const backButton = multistepForm.querySelector('[data-form-back]');
    const nextButton = multistepForm.querySelector('[data-form-next]');
    const submitButton = multistepForm.querySelector('[data-form-submit]');

    if (steps.length > 1 && nextButton && submitButton) {
        const gtag = (...args) => {
            if (typeof window.gtag === 'function') {
                window.gtag(...args);
            } else {
                window.dataLayer = window.dataLayer || [];
                window.dataLayer.push(args);
            }
        };

        const initialStepFromError = (() => {
            const errored = multistepForm.querySelector('[aria-invalid="true"]');

            if (!errored) {
                return 1;
            }

            const owner = errored.closest('[data-step]');

            return owner ? Number(owner.dataset.step) : 1;
        })();

        let currentStep = initialStepFromError;
        let hasStarted = false;

        multistepForm.classList.add('is-multistep');

        if (stepper) {
            stepper.hidden = false;
        }

        const showStep = (index) => {
            steps.forEach((node) => {
                const stepIndex = Number(node.dataset.step);
                node.hidden = stepIndex !== index;
            });

            indicators.forEach((node) => {
                const stepIndex = Number(node.dataset.stepIndicator);
                node.classList.toggle('is-active', stepIndex === index);
                node.classList.toggle('is-complete', stepIndex < index);
            });

            backButton.hidden = index === 1;
            nextButton.hidden = index === steps.length;
            submitButton.hidden = index !== steps.length;

            const focusTarget = steps
                .find((node) => Number(node.dataset.step) === index)
                ?.querySelector('input:not([type="hidden"]), textarea, select');

            if (focusTarget && document.activeElement !== focusTarget) {
                setTimeout(() => focusTarget.focus({ preventScroll: false }), 50);
            }

            gtag('event', 'inquiry_step_view', {
                event_category: 'inquiry',
                event_label: `step_${index}`,
            });

            if (!hasStarted) {
                hasStarted = true;
                gtag('event', 'inquiry_form_start', {
                    event_category: 'inquiry',
                });
            }
        };

        const validateStep = (index) => {
            const step = steps.find((node) => Number(node.dataset.step) === index);

            if (!step) {
                return true;
            }

            const requiredFields = step.querySelectorAll('[required]');
            let firstInvalid = null;

            requiredFields.forEach((field) => {
                field.removeAttribute('aria-invalid');

                if (!field.checkValidity()) {
                    field.setAttribute('aria-invalid', 'true');

                    if (!firstInvalid) {
                        firstInvalid = field;
                    }
                }
            });

            if (firstInvalid) {
                firstInvalid.focus();
                firstInvalid.reportValidity();

                return false;
            }

            return true;
        };

        nextButton.addEventListener('click', () => {
            if (!validateStep(currentStep)) {
                return;
            }

            gtag('event', 'inquiry_step_complete', {
                event_category: 'inquiry',
                event_label: `step_${currentStep}`,
            });

            currentStep = Math.min(currentStep + 1, steps.length);
            showStep(currentStep);
        });

        backButton?.addEventListener('click', () => {
            currentStep = Math.max(currentStep - 1, 1);
            showStep(currentStep);
        });

        multistepForm.addEventListener('submit', (event) => {
            const submittedFromButton = event.submitter === submitButton;
            const onFinalStep = currentStep === steps.length;

            if (!submittedFromButton || !onFinalStep) {
                event.preventDefault();

                if (!onFinalStep) {
                    nextButton.click();
                }

                return;
            }

            if (!validateStep(currentStep)) {
                event.preventDefault();
            }
        });

        showStep(currentStep);
    }
}

// ── Block manager drag-to-reorder ──
document.querySelectorAll('[data-block-manager]').forEach((manager) => {
    const list = manager.querySelector('[data-block-list]');
    const reorderUrl = manager.dataset.reorderUrl;

    if (!list || !reorderUrl) {
        return;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    let dragging = null;
    let viaHandle = false;

    const persist = () => {
        const ids = Array.from(list.querySelectorAll('[data-block-card]'))
            .map((card) => Number(card.dataset.blockId))
            .filter((id) => Number.isInteger(id));

        fetch(reorderUrl, {
            method: 'PATCH',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ block_ids: ids }),
        }).then((response) => {
            if (response.ok) {
                window.location.reload();
            }
        });
    };

    list.querySelectorAll('[data-block-handle]').forEach((handle) => {
        handle.addEventListener('mousedown', () => { viaHandle = true; });
        handle.addEventListener('touchstart', () => { viaHandle = true; }, { passive: true });
    });

    list.querySelectorAll('[data-block-card]').forEach((card) => {
        card.addEventListener('dragstart', (event) => {
            if (!viaHandle) {
                event.preventDefault();
                return;
            }

            dragging = card;
            card.classList.add('is-dragging');
            event.dataTransfer.effectAllowed = 'move';

            try {
                event.dataTransfer.setData('text/plain', card.dataset.blockId ?? '');
            } catch (_) {
                // Some browsers disallow setData here; ignore.
            }
        });

        card.addEventListener('dragend', () => {
            viaHandle = false;
            dragging?.classList.remove('is-dragging');
            dragging = null;
            list.querySelectorAll('.is-drop-target').forEach((node) => node.classList.remove('is-drop-target'));
        });

        card.addEventListener('dragover', (event) => {
            if (!dragging || dragging === card) {
                return;
            }

            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
            card.classList.add('is-drop-target');
        });

        card.addEventListener('dragleave', () => card.classList.remove('is-drop-target'));

        card.addEventListener('drop', (event) => {
            event.preventDefault();
            card.classList.remove('is-drop-target');

            if (!dragging || dragging === card) {
                return;
            }

            const rect = card.getBoundingClientRect();
            const dropAfter = (event.clientY - rect.top) > rect.height / 2;
            list.insertBefore(dragging, dropAfter ? card.nextSibling : card);

            persist();
        });
    });
});
