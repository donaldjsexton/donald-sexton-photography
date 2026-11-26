(function () {
    var toggle = document.getElementById('push-toggle');
        var label = document.getElementById('push-label');

        if (!toggle || !('serviceWorker' in navigator) || !('PushManager' in window)) {
            if (label) label.textContent = 'Push not supported';
            return;
        }

        var vapidKey = document.querySelector('meta[name="vapid-public-key"]');
        var csrfToken = document.querySelector('meta[name="csrf-token"]');
        if (!vapidKey || !vapidKey.content || !csrfToken) {
            label.textContent = 'Push not configured';
            return;
        }

        function urlBase64ToUint8Array(base64String) {
            var padding = '='.repeat((4 - base64String.length % 4) % 4);
            var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
            var rawData = atob(base64);
            var outputArray = new Uint8Array(rawData.length);
            for (var i = 0; i < rawData.length; ++i) {
                outputArray[i] = rawData.charCodeAt(i);
            }
            return outputArray;
        }

        function updateUI(subscribed) {
            label.textContent = subscribed ? 'Notifications on' : 'Enable notifications';
            toggle.classList.toggle('is-subscribed', subscribed);
            toggle.disabled = false;
        }

        function showError(message) {
            label.textContent = message;
            toggle.classList.remove('is-subscribed');
            toggle.disabled = false;
        }

        navigator.serviceWorker.register('/sw.js').then(function (reg) {
            return reg.pushManager.getSubscription().then(function (sub) {
                updateUI(!!sub);
            });
        }).catch(function (error) {
            console.error('Push: service worker registration failed', error);
            showError('Push unavailable');
        });

        toggle.addEventListener('click', function () {
            toggle.disabled = true;
            navigator.serviceWorker.ready.then(function (reg) {
                return reg.pushManager.getSubscription().then(function (sub) {
                    if (sub) {
                        return fetch('/admin/push/unsubscribe', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken.content,
                            },
                            body: JSON.stringify({ endpoint: sub.endpoint }),
                        }).then(function () {
                            return sub.unsubscribe();
                        }).then(function () {
                            updateUI(false);
                        });
                    }

                    return reg.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: urlBase64ToUint8Array(vapidKey.content),
                    }).then(function (newSub) {
                        var key = newSub.getKey('p256dh');
                        var auth = newSub.getKey('auth');
                        return fetch('/admin/push/subscribe', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken.content,
                            },
                            body: JSON.stringify({
                                endpoint: newSub.endpoint,
                                keys: {
                                    p256dh: btoa(String.fromCharCode.apply(null, new Uint8Array(key))),
                                    auth: btoa(String.fromCharCode.apply(null, new Uint8Array(auth))),
                                },
                            }),
                        }).then(function (response) {
                            if (!response.ok) {
                                throw new Error('Subscribe request failed: ' + response.status);
                            }
                            updateUI(true);
                        });
                    });
                });
            }).catch(function (error) {
                console.error('Push: toggle failed', error);
                if (typeof Notification !== 'undefined' && Notification.permission === 'denied') {
                    showError('Notifications blocked');
                } else {
                    showError('Could not enable');
                }
            });
        });
})();
