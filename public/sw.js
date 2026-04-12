self.addEventListener('push', function (event) {
    var data = { title: 'New Notification', body: '', url: '/admin' };

    if (event.data) {
        try {
            data = Object.assign(data, event.data.json());
        } catch (e) {
            data.body = event.data.text();
        }
    }

    event.waitUntil(
        self.registration.showNotification(data.title, {
            body: data.body,
            icon: '/icon-192.png',
            badge: '/icon-192.png',
            data: { url: data.url || '/admin' },
        })
    );
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();

    var url = event.notification.data && event.notification.data.url
        ? event.notification.data.url
        : '/admin';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
            for (var i = 0; i < clientList.length; i++) {
                if (clientList[i].url.indexOf('/admin') !== -1 && 'focus' in clientList[i]) {
                    clientList[i].navigate(url);
                    return clientList[i].focus();
                }
            }

            return clients.openWindow(url);
        })
    );
});
