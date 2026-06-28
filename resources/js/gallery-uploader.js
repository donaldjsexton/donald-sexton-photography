/**
 * Progressive enhancement for the admin gallery editor: uploads photos over
 * AJAX and tracks each file live via the gallery's Reverb websocket channel.
 *
 * Without JS (or websockets), the album upload forms still submit normally and
 * the server ingests them synchronously — this only layers on a live heartbeat.
 *
 * DOM contract (see resources/views/admin/galleries/edit.blade.php):
 *   [data-gallery-uploads][data-gallery-channel][data-gallery-event]
 *     form[data-upload-form][data-upload-url][data-album-id]
 *     [data-upload-status][data-album-id]
 *     [data-photo-grid][data-album-id]
 */

function initGalleryUploader() {
    const root = document.querySelector('[data-gallery-uploads]');

    if (!root || !window.Echo) {
        return;
    }

    const channelName = root.dataset.galleryChannel;
    const eventName = root.dataset.galleryEvent || '.upload.progressed';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    // batchId -> { albumId, total, processed, created, duplicates, failed }
    const batches = new Map();

    const statusFor = (albumId) =>
        root.querySelector(`[data-upload-status][data-album-id="${albumId}"]`);
    const gridFor = (albumId) =>
        root.querySelector(`[data-photo-grid][data-album-id="${albumId}"]`);

    function setStatus(albumId, message, tone = 'info') {
        const el = statusFor(albumId);

        if (!el) {
            return;
        }

        el.hidden = false;
        el.dataset.tone = tone;
        el.textContent = message;
    }

    function appendThumbnail(albumId, photo) {
        const grid = gridFor(albumId);

        if (!grid || !photo) {
            return;
        }

        const emptyNote = grid.parentElement?.querySelector('[data-empty-note]');

        if (emptyNote) {
            emptyNote.remove();
        }

        if (grid.querySelector(`[data-photo-id="${photo.id}"]`)) {
            return;
        }

        const tile = document.createElement('div');
        tile.className = 'gallery-photo is-fresh';
        tile.dataset.photoId = photo.id;

        const img = document.createElement('img');
        img.src = photo.thumb_url;
        img.alt = photo.original_name || '';
        img.loading = 'lazy';

        tile.appendChild(img);
        grid.appendChild(tile);
    }

    function summarise(batch) {
        const parts = [`${batch.created} added`];

        if (batch.duplicates) {
            parts.push(`${batch.duplicates} duplicate${batch.duplicates === 1 ? '' : 's'}`);
        }

        if (batch.failed) {
            parts.push(`${batch.failed} failed`);
        }

        return parts.join(', ') + '.';
    }

    window.Echo.private(channelName).listen(eventName, (payload) => {
        const batch = batches.get(payload.batch_id);

        if (!batch) {
            return;
        }

        batch.processed = payload.index;

        if (payload.status === 'created') {
            batch.created++;
            appendThumbnail(batch.albumId, payload.photo);
        } else if (payload.status === 'duplicate') {
            batch.duplicates++;
            appendThumbnail(batch.albumId, payload.photo);
        } else {
            batch.failed++;
        }

        if (batch.processed >= batch.total) {
            setStatus(batch.albumId, `Done — ${summarise(batch)}`, batch.failed ? 'warn' : 'success');
            batches.delete(payload.batch_id);
        } else {
            setStatus(
                batch.albumId,
                `Processing ${batch.processed} of ${batch.total}…`,
                'info',
            );
        }
    });

    root.querySelectorAll('form[data-upload-form]').forEach((form) => {
        const input = form.querySelector('input[type="file"]');
        const albumId = form.dataset.albumId;

        form.addEventListener('submit', (event) => {
            if (!input || input.files.length === 0) {
                return;
            }

            event.preventDefault();

            const data = new FormData();
            Array.from(input.files).forEach((file) => data.append('photos[]', file));

            setStatus(albumId, `Uploading ${input.files.length} file${input.files.length === 1 ? '' : 's'}… 0%`, 'info');

            window.axios
                .post(form.dataset.uploadUrl, data, {
                    headers: csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {},
                    onUploadProgress: (progress) => {
                        if (!progress.total) {
                            return;
                        }

                        const percent = Math.round((progress.loaded / progress.total) * 100);

                        if (percent < 100) {
                            setStatus(albumId, `Uploading… ${percent}%`, 'info');
                        } else {
                            setStatus(albumId, 'Uploaded — waiting for processing…', 'info');
                        }
                    },
                })
                .then((response) => {
                    const { batch_id: batchId, total } = response.data;

                    batches.set(batchId, {
                        albumId,
                        total,
                        processed: 0,
                        created: 0,
                        duplicates: 0,
                        failed: 0,
                    });

                    input.value = '';
                })
                .catch(() => {
                    setStatus(albumId, 'Upload failed. Please try again.', 'warn');
                });
        });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initGalleryUploader);
} else {
    initGalleryUploader();
}
