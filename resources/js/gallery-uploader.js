/**
 * Progressive enhancement for the admin gallery editor: uploads photos over
 * AJAX and tracks each file live via the gallery's Reverb websocket channel.
 *
 * Large selections are split across several requests so no single POST exceeds
 * PHP's post_max_size (exposed via data-max-request-bytes) — sending the whole
 * batch in one request is what used to trigger "POST data is too large" (413)
 * failures for a typical shoot's worth of photos.
 *
 * Without JS (or websockets), the album upload forms still submit normally and
 * the server ingests them synchronously — this only layers on a live heartbeat.
 *
 * DOM contract (see resources/views/admin/galleries/edit.blade.php):
 *   [data-gallery-uploads][data-gallery-channel][data-gallery-event][data-max-request-bytes]
 *     form[data-upload-form][data-upload-url][data-album-id]
 *     [data-upload-status][data-album-id]
 *     [data-photo-grid][data-album-id]
 */

import { startEcho } from './echo';

function initGalleryUploader() {
    const root = document.querySelector('[data-gallery-uploads]');

    if (!root) {
        return;
    }

    // Open the Reverb websocket only now that a page actually needs it. When no
    // key is configured, uploads still work — the forms POST synchronously and
    // the server ingests them without the live progress heartbeat.
    const echo = startEcho();

    if (!echo) {
        return;
    }

    const channelName = root.dataset.galleryChannel;
    const eventName = root.dataset.galleryEvent || '.upload.progressed';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    // Keep each request comfortably under PHP's post_max_size; the margin
    // absorbs multipart boundaries, headers, and the CSRF token.
    const maxRequestBytes = Number(root.dataset.maxRequestBytes) || 0;
    const requestBudget = maxRequestBytes > 0 ? Math.floor(maxRequestBytes * 0.9) : Infinity;

    // batchId -> upload session. One session per form submission; a session
    // spans several batch ids when the selection is split across requests.
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

    function summarise(session) {
        const parts = [`${session.created} added`];

        if (session.duplicates) {
            parts.push(`${session.duplicates} duplicate${session.duplicates === 1 ? '' : 's'}`);
        }

        if (session.failed) {
            parts.push(`${session.failed} failed`);
        }

        return parts.join(', ') + '.';
    }

    function processedCount(session) {
        let processed = 0;

        session.processedByBatch.forEach((count) => {
            processed += count;
        });

        return processed;
    }

    function refreshProcessingStatus(session) {
        // The upload loop owns the status line until every request is up.
        if (session.uploading) {
            return;
        }

        const processed = processedCount(session);

        if (processed >= session.total) {
            setStatus(session.albumId, `Done — ${summarise(session)}`, session.failed ? 'warn' : 'success');
            session.batchIds.forEach((batchId) => batches.delete(batchId));
        } else {
            setStatus(session.albumId, `Processing ${processed} of ${session.total}…`, 'info');
        }
    }

    echo.private(channelName).listen(eventName, (payload) => {
        const session = batches.get(payload.batch_id);

        if (!session) {
            return;
        }

        session.processedByBatch.set(payload.batch_id, payload.index);

        if (payload.status === 'created') {
            session.created++;
            appendThumbnail(session.albumId, payload.photo);
        } else if (payload.status === 'duplicate') {
            session.duplicates++;
            appendThumbnail(session.albumId, payload.photo);
        } else {
            session.failed++;
        }

        refreshProcessingStatus(session);
    });

    /**
     * Groups files into chunks whose combined size stays within the request
     * budget. Files that alone exceed the budget can never upload; they are
     * returned separately so the user can be told which ones to shrink.
     */
    function splitIntoChunks(files) {
        const chunks = [];
        const oversize = [];
        let current = [];
        let currentBytes = 0;

        files.forEach((file) => {
            if (file.size > requestBudget) {
                oversize.push(file);
                return;
            }

            if (current.length > 0 && currentBytes + file.size > requestBudget) {
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
    }

    function formatMegabytes(bytes) {
        return `${Math.max(1, Math.floor(bytes / (1024 * 1024)))} MB`;
    }

    root.querySelectorAll('form[data-upload-form]').forEach((form) => {
        const input = form.querySelector('input[type="file"]');
        const albumId = form.dataset.albumId;

        form.addEventListener('submit', async (event) => {
            if (!input || input.files.length === 0) {
                return;
            }

            event.preventDefault();

            const { chunks, oversize } = splitIntoChunks(Array.from(input.files));

            if (oversize.length > 0) {
                const names = oversize.map((file) => file.name).join(', ');

                setStatus(
                    albumId,
                    `Skipping ${oversize.length} file${oversize.length === 1 ? '' : 's'} over the ${formatMegabytes(requestBudget)} per-upload limit: ${names}. Export smaller copies and try again.`,
                    'warn',
                );

                if (chunks.length === 0) {
                    return;
                }
            }

            const session = {
                albumId,
                total: 0,
                created: 0,
                duplicates: 0,
                failed: oversize.length,
                processedByBatch: new Map(),
                batchIds: [],
                uploading: true,
            };

            const totalFiles = chunks.reduce((sum, chunk) => sum + chunk.length, 0);
            const totalBytes = chunks.flat().reduce((sum, file) => sum + file.size, 0);
            let uploadedBytes = 0;

            setStatus(albumId, `Uploading ${totalFiles} file${totalFiles === 1 ? '' : 's'}… 0%`, 'info');

            try {
                for (const chunk of chunks) {
                    const data = new FormData();
                    chunk.forEach((file) => data.append('photos[]', file));

                    const chunkBytes = chunk.reduce((sum, file) => sum + file.size, 0);

                    const response = await window.axios.post(form.dataset.uploadUrl, data, {
                        headers: csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {},
                        onUploadProgress: (progress) => {
                            if (!progress.total || !totalBytes) {
                                return;
                            }

                            const overall = Math.min(
                                uploadedBytes + (progress.loaded / progress.total) * chunkBytes,
                                totalBytes,
                            );
                            const percent = Math.round((overall / totalBytes) * 100);

                            if (percent < 100) {
                                setStatus(albumId, `Uploading… ${percent}%`, 'info');
                            } else {
                                setStatus(albumId, 'Uploaded — waiting for processing…', 'info');
                            }
                        },
                    });

                    uploadedBytes += chunkBytes;
                    session.total += chunk.length;
                    session.batchIds.push(response.data.batch_id);
                    batches.set(response.data.batch_id, session);
                }

                input.value = '';
            } catch (error) {
                // Files in requests that never made it up count as failed;
                // batches already accepted keep processing and reporting
                // their progress over the websocket.
                session.failed += totalFiles - session.total;
                session.uploading = false;

                const message = error?.response?.status === 413
                    ? 'The upload was too large for the server to accept in a single request. Try again with fewer or smaller photos.'
                    : error?.response?.data?.message;

                setStatus(albumId, message || 'Upload failed. Please try again.', 'warn');

                return;
            }

            session.uploading = false;
            refreshProcessingStatus(session);
        });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initGalleryUploader);
} else {
    initGalleryUploader();
}
