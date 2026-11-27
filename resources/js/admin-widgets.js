// Vanilla admin widgets shared with the v2 layout: focal picker, media picker field,
// story/journal gallery editor and block drag-reorder. Ported verbatim from app.js
// (same data attributes and request payloads).
import { openMediaLibrary, uploadMediaFiles } from './media-library';

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



// ── Media picker field ──
//
// The library dialog itself lives in ./media-library.js; this only binds the
// `<x-admin.media-picker>` field (hero / featured image) to it and reflects
// the chosen photo back into the field's preview.

const mediaPickers = document.querySelectorAll('[data-media-picker]');

if (mediaPickers.length > 0) {
    const applySelection = (picker, media) => {
        const input = picker.querySelector('[data-media-picker-value]');
        const surface = picker.querySelector('[data-media-picker-surface]');
        const preview = picker.querySelector('[data-media-picker-preview]');
        const filenameNode = picker.querySelector('[data-media-picker-filename]');
        const idNode = picker.querySelector('[data-media-picker-id]');
        const clearButton = picker.querySelector('[data-media-picker-clear]');

        if (input) {
            input.value = media ? media.id : '';
        }

        surface?.classList.toggle('is-empty', !media);

        if (preview) {
            preview.innerHTML = '';

            if (media?.url) {
                const img = document.createElement('img');
                img.src = media.url;
                img.alt = media.alt_text || media.filename || '';
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
            filenameNode.textContent = media?.filename || '—';
        }

        if (idNode) {
            idNode.textContent = media ? `#${media.id}` : '';
        }

        if (clearButton) {
            clearButton.hidden = !media;
        }
    };

    mediaPickers.forEach((picker) => {
        const openButton = picker.querySelector('[data-media-picker-open]');
        const clearButton = picker.querySelector('[data-media-picker-clear]');

        openButton?.addEventListener('click', async () => {
            // A featured image is one photo by definition, so this field
            // never offers the multi-select mode — it can still upload.
            const [media] = await openMediaLibrary({
                endpoint: picker.dataset.mediaPickerEndpoint,
                uploadUrl: picker.dataset.mediaPickerUploadUrl,
                maxRequestBytes: picker.dataset.maxRequestBytes,
                mode: 'single',
                allowModeToggle: false,
                title: picker.dataset.mediaPickerTitle || 'Choose an image',
                trigger: openButton,
            });

            if (media) {
                applySelection(picker, media);
            }
        });

        clearButton?.addEventListener('click', () => applySelection(picker, null));
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

    // ── Gallery instance ──
    const setupGallery = (root) => {
        const grid = root.querySelector('[data-story-grid]');
        const emptyMessage = root.querySelector('[data-story-empty]');
        const status = root.querySelector('[data-story-status]');
        const countNode = root.querySelector('[data-story-count]');
        const heroStat = root.querySelector('[data-story-hero-stat]');
        const heroInput = document.querySelector(`form input[name="${root.dataset.heroInput || 'hero_media_id'}"]`);
        const addButton = root.querySelector('[data-story-add]');
        const uploadButton = root.querySelector('[data-story-upload]');
        const fileInput = root.querySelector('[data-story-file-input]');
        const dropzone = root.querySelector('[data-story-dropzone]');

        const attachUrl = root.dataset.attachUrl;
        const reorderUrl = root.dataset.reorderUrl;
        const detachUrlPattern = root.dataset.detachUrlPattern;
        const heroUrlPattern = root.dataset.heroUrlPattern;
        const pickerUrl = root.dataset.pickerUrl;
        const uploadUrl = root.dataset.uploadUrl;
        const maxRequestBytes = Number(root.dataset.maxRequestBytes) || 0;

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

        const attachMedia = (mediaIds) => {
            if (mediaIds.length === 0) {
                return Promise.resolve(null);
            }

            return jsonFetch(attachUrl, {
                method: 'POST',
                body: JSON.stringify({ media_ids: mediaIds }),
            })
                .then((payload) => {
                    applyState(payload);
                    flash(`Added ${mediaIds.length} photo${mediaIds.length === 1 ? '' : 's'}.`);

                    return payload;
                })
                .catch(() => flash('Could not add the selected photos.', true));
        };

        addButton?.addEventListener('click', async () => {
            const existingIds = collectIds();

            const selection = await openMediaLibrary({
                endpoint: pickerUrl,
                uploadUrl,
                maxRequestBytes,
                mode: 'multi',
                allowModeToggle: true,
                title: root.dataset.pickerTitle || 'Add photos to this gallery',
                excludeIds: existingIds,
                trigger: addButton,
            });

            const fresh = selection
                .map((media) => media.id)
                .filter((id) => !existingIds.includes(id));

            if (selection.length > 0 && fresh.length === 0) {
                flash('Those photos are already in this gallery.');
                return;
            }

            attachMedia(fresh);
        });

        /**
         * Upload photos straight into this gallery: each file becomes a
         * library item, then the whole batch is attached in one call so the
         * gallery order matches the order they were chosen in.
         */
        const uploadInto = async (files) => {
            if (!uploadUrl) {
                return;
            }

            const list = Array.from(files || []);

            if (list.length === 0) {
                return;
            }

            root.classList.add('is-uploading');
            flash(`Uploading ${list.length} photo${list.length === 1 ? '' : 's'}… 0%`);

            const { created, failed } = await uploadMediaFiles({
                uploadUrl,
                files: list,
                maxRequestBytes,
                onProgress: ({ percent }) => flash(`Uploading ${list.length} photo${list.length === 1 ? '' : 's'}… ${percent}%`),
            });

            root.classList.remove('is-uploading');

            if (created.length > 0) {
                await attachMedia(created.map((media) => media.id));
            }

            if (failed.length > 0) {
                flash(
                    `${failed.length} photo${failed.length === 1 ? '' : 's'} skipped: ${failed.map((entry) => entry.filename).join(', ')}`,
                    true,
                );
            }
        };

        uploadButton?.addEventListener('click', () => fileInput?.click());

        fileInput?.addEventListener('change', () => {
            const files = Array.from(fileInput.files || []);
            fileInput.value = '';
            uploadInto(files);
        });

        // Drop photos anywhere on the gallery panel. `dragDepth` counts
        // enter/leave pairs so crossing a child tile doesn't flicker the
        // overlay off mid-drag.
        if (dropzone && uploadUrl) {
            let dragDepth = 0;

            const hasFiles = (event) => Array.from(event.dataTransfer?.types || []).includes('Files');

            root.addEventListener('dragenter', (event) => {
                if (!hasFiles(event)) {
                    return;
                }

                event.preventDefault();
                dragDepth += 1;
                dropzone.hidden = false;
            });

            root.addEventListener('dragover', (event) => {
                if (hasFiles(event)) {
                    event.preventDefault();
                }
            });

            root.addEventListener('dragleave', () => {
                dragDepth = Math.max(0, dragDepth - 1);

                if (dragDepth === 0) {
                    dropzone.hidden = true;
                }
            });

            root.addEventListener('drop', (event) => {
                if (!hasFiles(event)) {
                    return;
                }

                event.preventDefault();
                dragDepth = 0;
                dropzone.hidden = true;
                uploadInto(event.dataTransfer.files);
            });
        }
    };

    storyGalleries.forEach(setupGallery);
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
