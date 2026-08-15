import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

/**
 * Laravel Echo wired to the Reverb websocket server. Configured from the
 * VITE_REVERB_* build-time env vars (mirrors the server's REVERB_* values).
 *
 * The connection is created lazily via `startEcho()` — only pages that
 * genuinely need realtime (e.g. the gallery uploader) call it. Connecting
 * eagerly on every admin page opened a websocket the vast majority of pages
 * never used, and any page load where Reverb was unreachable spilled a failed
 * `wss://…/app/…` handshake into the browser console. Gating the connection on
 * an actual consumer keeps those pages quiet.
 */
const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;

/**
 * Create the Echo instance on first call and expose it on `window.Echo`.
 * Returns the instance, or `null` when no Reverb key is configured (in which
 * case realtime consumers fall back to their non-websocket behavior).
 */
export function startEcho() {
    if (window.Echo) {
        return window.Echo;
    }

    if (!reverbKey) {
        return null;
    }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverbKey,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
        auth: {
            headers: csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {},
        },
    });

    return window.Echo;
}
