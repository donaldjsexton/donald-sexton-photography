/**
 * The admin media library picker: one modal shared by every place that needs
 * to reach into the library — hero/featured image fields, story and journal
 * galleries, block editors.
 *
 * It supersedes the two near-identical modals that used to live in app.js
 * (one single-select, one multi-select). Both flows are the same dialog now,
 * differing only in the mode it opens in:
 *
 *   openMediaLibrary({ endpoint, mode: 'single' })  → resolves [media] or []
 *   openMediaLibrary({ endpoint, mode: 'multi'  })  → resolves [media, …] or []
 *
 * Uploading is part of the picker rather than a separate trip to the library:
 * the Upload button and a drop target over the grid post straight to the
 * batch endpoint, and freshly created media lands selected at the top of the
 * grid, ready to insert.
 */

const COLLECTIONS = [
    { value: 'all', label: 'All media' },
    { value: 'recent', label: 'Recent' },
    { value: 'unused', label: 'Unused' },
    { value: 'missing-alt', label: 'No alt text' },
    { value: 'pictime', label: 'Pic-Time' },
    { value: 'wp', label: 'WordPress' },
];

const TYPES = [
    { value: 'all', label: 'All types' },
    { value: 'jpeg', label: 'JPEG' },
    { value: 'png', label: 'PNG' },
    { value: 'webp', label: 'WebP' },
    { value: 'gif', label: 'GIF' },
];

const SORTS = [
    { value: 'newest', label: 'Newest' },
    { value: 'oldest', label: 'Oldest' },
    { value: 'name', label: 'Name A–Z' },
    { value: 'largest', label: 'Largest' },
];

const ACCEPT = 'image/jpeg,image/png,image/webp,image/gif';

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const icon = (paths, size = 16) =>
    `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="currentColor"
        stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${paths}</svg>`;

const ICON_SEARCH = icon('<circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path>', 15);
const ICON_UPLOAD = icon('<path d="M12 3v12"></path><path d="m17 8-5-5-5 5"></path><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>', 15);
const ICON_UPLOAD_LARGE = icon('<path d="M12 3v12"></path><path d="m17 8-5-5-5 5"></path><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>', 30);

const formatDimensions = (media) =>
    media.width && media.height ? `${media.width}×${media.height}` : null;

/**
 * Group files into requests that each stay inside PHP's post_max_size, the
 * same budget the client-gallery uploader works to. Files too large to ever
 * fit come back separately so the user is told which ones to shrink rather
 * than watching a silent 413.
 */
const splitIntoChunks = (files, budget) => {
    const chunks = [];
    const oversize = [];
    let current = [];
    let currentBytes = 0;

    files.forEach((file) => {
        if (file.size > budget) {
            oversize.push(file);
            return;
        }

        if (current.length > 0 && currentBytes + file.size > budget) {
            chunks.push(current);
            current = [];
            currentBytes = 0;
        }

        current.push(file);
        currentBytes += file.size;
    });

    if (current.length > 0) {
        chunks.push(current);
    }

    return { chunks, oversize };
};

const formatMegabytes = (bytes) => `${Math.max(1, Math.floor(bytes / (1024 * 1024)))} MB`;

/**
 * Post a batch of files to the media upload endpoint, split across as many
 * requests as the request budget demands.
 *
 * @param {object} options
 * @param {string} options.uploadUrl
 * @param {File[]} options.files
 * @param {number} [options.maxRequestBytes] 0 or absent means "unlimited"
 * @param {(state: {percent: number, uploaded: number, total: number}) => void} [options.onProgress]
 * @returns {Promise<{created: Array<object>, failed: Array<{filename: string, message: string}>}>}
 */
export async function uploadMediaFiles({ uploadUrl, files, maxRequestBytes = 0, onProgress }) {
    const list = Array.from(files || []).filter((file) => file.size > 0 || file.type.startsWith('image/'));

    if (list.length === 0) {
        return { created: [], failed: [] };
    }

    const budget = maxRequestBytes > 0 ? Math.floor(maxRequestBytes * 0.9) : Infinity;
    const { chunks, oversize } = splitIntoChunks(list, budget);

    const created = [];
    const failed = oversize.map((file) => ({
        filename: file.name,
        message: `Over the ${formatMegabytes(budget)} per-upload limit — export a smaller copy.`,
    }));

    const totalBytes = chunks.flat().reduce((sum, file) => sum + file.size, 0);
    const totalFiles = chunks.reduce((sum, chunk) => sum + chunk.length, 0);
    let uploadedBytes = 0;

    for (const chunk of chunks) {
        const data = new FormData();
        chunk.forEach((file) => data.append('files[]', file));

        const chunkBytes = chunk.reduce((sum, file) => sum + file.size, 0);

        try {
            const response = await window.axios.post(uploadUrl, data, {
                headers: { 'X-CSRF-TOKEN': csrfToken() },
                onUploadProgress: (progress) => {
                    if (!onProgress || !totalBytes || !progress.total) {
                        return;
                    }

                    const overall = Math.min(
                        uploadedBytes + (progress.loaded / progress.total) * chunkBytes,
                        totalBytes,
                    );

                    onProgress({
                        percent: Math.round((overall / totalBytes) * 100),
                        uploaded: created.length,
                        total: totalFiles,
                    });
                },
            });

            created.push(...(response.data?.data ?? []));
            failed.push(...(response.data?.failed ?? []));
        } catch (error) {
            const message = error?.response?.status === 413
                ? 'Too large for the server to accept in one request — try fewer photos at a time.'
                : error?.response?.data?.message || 'Upload failed.';

            chunk.forEach((file) => failed.push({ filename: file.name, message }));
        }

        uploadedBytes += chunkBytes;
    }

    return { created, failed };
}

/** Build the one library tile shape used in both modes. */
const buildTile = (media, { multi, selected, attached, index }) => {
    // A <div role="button"> rather than a real <button>, so the tile is an
    // ordinary block box with no form-control sizing quirks. Keyboard
    // activation is restored below.
    const tile = document.createElement('div');
    tile.setAttribute('role', multi ? 'checkbox' : 'button');

    if (multi) {
        tile.setAttribute('aria-checked', selected ? 'true' : 'false');
    }

    tile.tabIndex = 0;
    tile.className = 'media-picker-tile';
    tile.style.setProperty('--enter-delay', `${Math.min(index, 16) * 28}ms`);
    tile.dataset.mediaTile = '';
    tile.dataset.id = String(media.id);

    if (selected) {
        tile.classList.add('is-selected');
    }

    if (attached) {
        // Already in this gallery: shown for context, but not pickable.
        tile.classList.add('is-attached');
        tile.tabIndex = -1;
        tile.setAttribute('aria-disabled', 'true');
    }

    tile.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            tile.click();
        }
    });

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

    const kind = document.createElement('span');
    kind.className = 'media-picker-tile__kind';
    kind.textContent = media.kind || 'IMAGE';
    figure.appendChild(kind);

    const idBadge = document.createElement('span');
    idBadge.className = 'media-picker-tile__id';
    idBadge.textContent = `#${media.id}`;
    figure.appendChild(idBadge);

    if (attached) {
        const badge = document.createElement('span');
        badge.className = 'media-picker-tile__badge';
        badge.textContent = 'Added';
        figure.appendChild(badge);
    } else if (multi) {
        const check = document.createElement('span');
        check.className = 'media-picker-tile__check';
        check.innerHTML = icon('<path d="m5 12 5 5L20 7"></path>', 14);
        figure.appendChild(check);
    }

    const name = document.createElement('span');
    name.className = 'media-picker-tile__name';
    name.textContent = media.filename || `#${media.id}`;
    name.title = media.filename || '';

    const meta = document.createElement('span');
    meta.className = 'media-picker-tile__meta';
    meta.textContent = [formatDimensions(media), media.kind].filter(Boolean).join(' · ');

    tile.append(figure, name, meta);

    return tile;
};

/** Lazily built singleton — every picker on the page shares one dialog. */
let library = null;

const buildLibrary = () => {
    const root = document.createElement('div');
    root.className = 'media-picker-modal';
    root.setAttribute('role', 'dialog');
    root.setAttribute('aria-modal', 'true');
    root.setAttribute('aria-label', 'Media library');
    root.hidden = true;

    const options = (list) => list
        .map((option) => `<option value="${option.value}">${option.label}</option>`)
        .join('');

    root.innerHTML = `
        <div class="media-picker-modal__backdrop" data-close></div>
        <div class="media-picker-modal__panel">
            <header class="media-picker-modal__header">
                <div class="media-picker-modal__heading">
                    <p class="eyebrow">Media library</p>
                    <h2 data-title>Choose an image</h2>
                </div>
                <button type="button" class="media-picker-modal__close" data-close aria-label="Close">&times;</button>
            </header>

            <div class="media-picker-modal__toolbar">
                <p class="media-picker-modal__search">
                    ${ICON_SEARCH}
                    <input type="search" data-search placeholder="Search by filename, alt text, or ID…" aria-label="Search the media library" autocomplete="off">
                </p>
                <div class="media-picker-modal__filters">
                    <select class="media-picker-modal__select" data-filter aria-label="Collection">${options(COLLECTIONS)}</select>
                    <select class="media-picker-modal__select" data-type aria-label="File type">${options(TYPES)}</select>
                    <select class="media-picker-modal__select" data-sort aria-label="Sort order">${options(SORTS)}</select>
                </div>
                <div class="media-picker-modal__toolbar-end">
                    <div class="media-picker-modal__modes" role="group" aria-label="Selection mode" data-modes>
                        <button type="button" class="media-picker-modal__mode" data-mode="single">Single</button>
                        <button type="button" class="media-picker-modal__mode" data-mode="multi">Select multiple</button>
                    </div>
                    <button type="button" class="cta-secondary media-picker-modal__upload" data-upload>
                        ${ICON_UPLOAD}<span>Upload</span>
                    </button>
                </div>
                <input type="file" data-file-input accept="${ACCEPT}" multiple hidden>
            </div>

            <div class="media-picker-modal__body" data-body>
                <div class="media-picker-modal__grid" data-grid></div>
                <button type="button" class="cta-secondary media-picker-modal__more" data-more hidden>Load more</button>
                <div class="media-picker-modal__dropzone" data-dropzone hidden>
                    ${ICON_UPLOAD_LARGE}
                    <span>Drop to upload</span>
                </div>
            </div>

            <footer class="media-picker-modal__footer">
                <p class="media-picker-modal__results" data-results aria-live="polite"></p>
                <div class="media-picker-modal__actions">
                    <button type="button" class="cta-secondary" data-close>Cancel</button>
                    <button type="button" class="cta media-picker-modal__confirm" data-confirm disabled>Insert image</button>
                </div>
            </footer>
        </div>
    `;

    document.body.appendChild(root);

    const state = {
        root,
        panel: root.querySelector('.media-picker-modal__panel'),
        title: root.querySelector('[data-title]'),
        search: root.querySelector('[data-search]'),
        filter: root.querySelector('[data-filter]'),
        type: root.querySelector('[data-type]'),
        sort: root.querySelector('[data-sort]'),
        modes: root.querySelector('[data-modes]'),
        modeButtons: Array.from(root.querySelectorAll('[data-mode]')),
        uploadButton: root.querySelector('[data-upload]'),
        fileInput: root.querySelector('[data-file-input]'),
        body: root.querySelector('[data-body]'),
        grid: root.querySelector('[data-grid]'),
        dropzone: root.querySelector('[data-dropzone]'),
        results: root.querySelector('[data-results]'),
        more: root.querySelector('[data-more]'),
        confirm: root.querySelector('[data-confirm]'),

        endpoint: null,
        uploadUrl: null,
        maxRequestBytes: 0,
        mode: 'single',
        multi: false,
        excludeIds: new Set(),
        selected: new Map(),
        renderedIds: new Set(),
        page: 1,
        total: 0,
        loading: false,
        uploading: false,
        hasMore: false,
        resolver: null,
        searchDebounce: null,
        lastFocused: null,
        dragDepth: 0,
    };

    // ── Result rendering ──

    const setResults = (message) => {
        state.results.textContent = message;
    };

    const describeResults = () => {
        if (state.uploading) {
            return;
        }

        const count = `${state.total.toLocaleString()} ${state.total === 1 ? 'item' : 'items'}`;
        const selected = state.selected.size;

        setResults(selected > 0 ? `${count} · ${selected} selected` : count);
    };

    const updateConfirm = () => {
        const count = state.selected.size;

        state.confirm.disabled = count === 0;

        if (!state.multi) {
            state.confirm.textContent = 'Insert image';
        } else if (count === 0) {
            state.confirm.textContent = 'Insert photos';
        } else {
            state.confirm.textContent = `Insert ${count} ${count === 1 ? 'photo' : 'photos'}`;
        }

        describeResults();
    };

    const markTile = (tile, selected) => {
        tile.classList.toggle('is-selected', selected);

        if (tile.getAttribute('role') === 'checkbox') {
            tile.setAttribute('aria-checked', selected ? 'true' : 'false');
        }
    };

    const toggle = (media, tile) => {
        if (state.multi) {
            if (state.selected.has(media.id)) {
                state.selected.delete(media.id);
                markTile(tile, false);
            } else {
                state.selected.set(media.id, media);
                markTile(tile, true);
            }

            updateConfirm();

            return;
        }

        // Single mode: picking a tile is the whole interaction.
        state.selected = new Map([[media.id, media]]);
        markTile(tile, true);
        updateConfirm();
        finish(Array.from(state.selected.values()));
    };

    const appendTiles = (items, { prepend = false } = {}) => {
        // Offset pagination can repeat an item across page boundaries when
        // new media lands mid-browse — skip anything already in the grid.
        const fresh = items.filter((media) => !state.renderedIds.has(media.id));

        state.grid.querySelector('.media-picker-modal__empty')?.remove();

        fresh.forEach((media, index) => {
            state.renderedIds.add(media.id);

            const attached = state.excludeIds.has(media.id);

            const tile = buildTile(media, {
                multi: state.multi,
                selected: state.selected.has(media.id),
                attached,
                index: prepend ? 0 : index,
            });

            if (!attached) {
                tile.addEventListener('click', () => toggle(media, tile));
            }

            if (prepend) {
                state.grid.prepend(tile);
            } else {
                state.grid.appendChild(tile);
            }
        });

        return fresh.length;
    };

    const showEmpty = () => {
        const empty = document.createElement('p');
        empty.className = 'media-picker-modal__empty';
        empty.textContent = 'Nothing here yet. Try a different search, or upload photos.';
        state.grid.appendChild(empty);
    };

    // ── Loading ──

    const load = (reset) => {
        if (!state.endpoint || state.loading) {
            return;
        }

        // The page advances only once a response lands — bumping it up front
        // meant a failed request skipped a page of results forever after.
        const page = reset ? 1 : state.page + 1;

        if (reset) {
            state.body.scrollTop = 0;
            setResults('Searching…');
        }

        const params = new URLSearchParams({ page: String(page) });
        const term = state.search.value.trim();

        if (term !== '') {
            params.set('q', term);
        }

        if (state.filter.value !== 'all') {
            params.set('filter', state.filter.value);
        }

        if (state.type.value !== 'all') {
            params.set('type', state.type.value);
        }

        params.set('sort', state.sort.value);

        state.loading = true;
        state.more.disabled = true;
        state.more.textContent = 'Loading…';

        fetch(`${state.endpoint}?${params.toString()}`, {
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
                if (reset) {
                    state.grid.innerHTML = '';
                    state.renderedIds = new Set();
                }

                state.page = payload.current_page || page;
                state.total = payload.total ?? 0;
                state.hasMore = Boolean(payload.has_more);

                const added = appendTiles(payload.data || []);

                if (reset && added === 0) {
                    showEmpty();
                }

                state.more.hidden = !state.hasMore;
                placeSentinel();
                describeResults();
            })
            .catch(() => {
                setResults('Could not load media. Try again.');
            })
            .finally(() => {
                state.loading = false;
                state.more.disabled = false;
                state.more.textContent = 'Load more';
            });
    };

    // ── Infinite scroll ──
    // The sentinel rides along as the grid's last child; re-observing it
    // after each batch forces a fresh intersection check, so loading chains
    // until the sentinel sits beyond the preload margin or `has_more` runs
    // out. "Load more" stays as the manual fallback.

    const sentinel = document.createElement('div');
    sentinel.className = 'media-picker-modal__sentinel';
    sentinel.setAttribute('aria-hidden', 'true');

    const observer = typeof IntersectionObserver === 'function'
        ? new IntersectionObserver((entries) => {
            if (state.hasMore && !state.loading && entries.some((entry) => entry.isIntersecting)) {
                load(false);
            }
        }, { root: state.body, rootMargin: '600px 0px' })
        : null;

    function placeSentinel() {
        observer?.unobserve(sentinel);
        state.grid.appendChild(sentinel);

        if (state.hasMore) {
            observer?.observe(sentinel);
        }
    }

    // ── Uploading ──

    const runUpload = async (files) => {
        if (!state.uploadUrl || state.uploading) {
            return;
        }

        const list = Array.from(files || []);

        if (list.length === 0) {
            return;
        }

        state.uploading = true;
        state.uploadButton.disabled = true;
        state.panel.classList.add('is-uploading');
        setResults(`Uploading ${list.length} ${list.length === 1 ? 'photo' : 'photos'}… 0%`);

        const { created, failed } = await uploadMediaFiles({
            uploadUrl: state.uploadUrl,
            files: list,
            maxRequestBytes: state.maxRequestBytes,
            onProgress: ({ percent }) => setResults(`Uploading ${list.length} ${list.length === 1 ? 'photo' : 'photos'}… ${percent}%`),
        });

        state.uploading = false;
        state.uploadButton.disabled = false;
        state.panel.classList.remove('is-uploading');

        // New media lands at the top already selected, so the common case —
        // upload then insert — is a single extra click.
        if (state.multi) {
            created.forEach((media) => state.selected.set(media.id, media));
        } else if (created.length > 0) {
            state.grid.querySelectorAll('.media-picker-tile.is-selected')
                .forEach((node) => markTile(node, false));
            state.selected = new Map([[created[0].id, created[0]]]);
        }

        state.total += created.length;
        appendTiles(created.slice().reverse(), { prepend: true });
        updateConfirm();

        if (failed.length > 0) {
            const names = failed.map((entry) => entry.filename).join(', ');
            setResults(`${created.length} uploaded · ${failed.length} skipped (${names})`);
        }
    };

    // ── Wiring ──

    root.querySelectorAll('[data-close]').forEach((node) => {
        node.addEventListener('click', () => finish([]));
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !root.hidden) {
            finish([]);
        }
    });

    state.search.addEventListener('input', () => {
        clearTimeout(state.searchDebounce);
        state.searchDebounce = setTimeout(() => load(true), 220);
    });

    [state.filter, state.type, state.sort].forEach((select) => {
        select.addEventListener('change', () => load(true));
    });

    state.more.addEventListener('click', () => load(false));

    state.modeButtons.forEach((button) => {
        button.addEventListener('click', () => setMode(button.dataset.mode));
    });

    state.uploadButton.addEventListener('click', () => state.fileInput.click());

    state.fileInput.addEventListener('change', () => {
        const files = Array.from(state.fileInput.files || []);
        state.fileInput.value = '';
        runUpload(files);
    });

    state.confirm.addEventListener('click', () => {
        if (state.selected.size > 0) {
            finish(Array.from(state.selected.values()));
        }
    });

    // Drag-and-drop straight onto the grid. `dragDepth` counts enter/leave
    // pairs so crossing a child element doesn't flicker the overlay off.
    const dragHasFiles = (event) => Array.from(event.dataTransfer?.types || []).includes('Files');

    state.body.addEventListener('dragenter', (event) => {
        if (!state.uploadUrl || !dragHasFiles(event)) {
            return;
        }

        event.preventDefault();
        state.dragDepth += 1;
        state.dropzone.hidden = false;
    });

    state.body.addEventListener('dragover', (event) => {
        if (state.uploadUrl && dragHasFiles(event)) {
            event.preventDefault();
        }
    });

    state.body.addEventListener('dragleave', () => {
        state.dragDepth = Math.max(0, state.dragDepth - 1);

        if (state.dragDepth === 0) {
            state.dropzone.hidden = true;
        }
    });

    state.body.addEventListener('drop', (event) => {
        if (!state.uploadUrl || !dragHasFiles(event)) {
            return;
        }

        event.preventDefault();
        state.dragDepth = 0;
        state.dropzone.hidden = true;
        runUpload(event.dataTransfer.files);
    });

    // ── Mode + lifecycle ──

    function setMode(mode) {
        state.mode = mode === 'multi' ? 'multi' : 'single';
        state.multi = state.mode === 'multi';

        state.modeButtons.forEach((button) => {
            const active = button.dataset.mode === state.mode;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });

        state.panel.classList.toggle('is-multi', state.multi);
        state.selected.clear();
        updateConfirm();
        load(true);
    }

    function finish(selection) {
        if (root.hidden) {
            return;
        }

        root.hidden = true;
        document.body.classList.remove('media-picker-modal-open');
        observer?.unobserve(sentinel);

        const resolver = state.resolver;
        state.resolver = null;
        state.lastFocused?.focus();

        resolver?.(selection);
    }

    return {
        state,
        setMode,
        load,
        open(options) {
            // A picker opened while another is still resolving would strand
            // the first caller's promise — settle it with an empty selection.
            finish([]);

            state.endpoint = options.endpoint;
            state.uploadUrl = options.uploadUrl || null;
            state.maxRequestBytes = Number(options.maxRequestBytes) || 0;
            state.excludeIds = new Set((options.excludeIds || []).map(Number));
            state.selected = new Map();
            state.renderedIds = new Set();
            state.page = 1;
            state.total = 0;
            state.hasMore = false;
            state.lastFocused = options.trigger || document.activeElement;

            state.title.textContent = options.title || 'Choose an image';
            state.search.value = '';
            state.filter.value = 'all';
            state.type.value = 'all';
            state.sort.value = 'newest';
            state.grid.innerHTML = '';
            state.more.hidden = true;
            state.uploadButton.hidden = !state.uploadUrl;
            state.modes.hidden = !options.allowModeToggle;

            root.hidden = false;
            document.body.classList.add('media-picker-modal-open');

            setMode(options.mode === 'multi' ? 'multi' : 'single');
            setTimeout(() => state.search.focus(), 50);

            return new Promise((resolve) => {
                state.resolver = resolve;
            });
        },
    };
};

/**
 * Open the shared media library.
 *
 * @param {object} options
 * @param {string} options.endpoint          picker JSON endpoint
 * @param {string} [options.uploadUrl]       batch upload endpoint; upload UI is hidden without it
 * @param {'single'|'multi'} [options.mode]
 * @param {boolean} [options.allowModeToggle] show the Single / Select multiple switch
 * @param {string} [options.title]
 * @param {number[]} [options.excludeIds]    media already attached, hidden from the grid
 * @param {number} [options.maxRequestBytes]
 * @param {HTMLElement} [options.trigger]    element to restore focus to on close
 * @returns {Promise<Array<object>>} the chosen media, or [] when cancelled
 */
export function openMediaLibrary(options) {
    if (!library) {
        library = buildLibrary();
    }

    return library.open(options);
}
