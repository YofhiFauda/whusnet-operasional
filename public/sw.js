// Whusnet Service Worker — Web Push (ADHOC-288, Tahap 5)
// docs/plan/notifications/rancangan-arsitektur-web-push-pwa-nextjs.md §6

self.addEventListener('push', function (event) {
    if (!event.data) return;

    let payload;
    try {
        payload = event.data.json();
    } catch (e) {
        payload = {
            title: 'Ruang Kerja Whusnet',
            body: event.data.text(),
            data: { url: '/' },
        };
    }

    const options = {
        body: payload.body,
        icon: payload.icon || '/images/logo-192.png',
        badge: payload.badge || '/images/logo-192.png',
        image: payload.image || null,
        tag: payload.tag || 'default-tag',
        renotify: payload.renotify || false,
        requireInteraction: payload.requireInteraction || false,
        data: payload.data || { url: '/' },
        actions: payload.actions || [],
    };

    // Ack delivery (ADHOC-288, Tahap 6) — ditembak SEBELUM showNotification
    // selesai, bukan sesudah: device yang nerima push tapi gagal/telat
    // nampilin notif (OS nolak izin, battery saver numpuk event) tetap
    // ke-ack "diterima" duluan, baru nampilin. Kalau gak ada ackUrl di
    // payload (notifikasi yang belum pakai WebPushDeliveryTracker), lewatin
    // tanpa error — bukan semua notifikasi wajib di-track.
    const tasks = [];
    if (payload.data && payload.data.ackUrl) {
        tasks.push(fetch(payload.data.ackUrl).catch(function () {}));
    }

    // Notifikasi OS di-skip kalau ada tab Whusnet TERLIHAT yang menjamin toast
    // lonceng muncul (ADHOC-297 bug #1 lanjutan, keputusan user 2026-11-14).
    // Event push selalu berpasangan dengan AppNotification in-app ⇒ dulu satu
    // jadwal task = toast + notifikasi OS untuk kejadian yang sama.
    //
    // "Terlihat" saja TIDAK cukup (review ADHOC-297 #1): tab di halaman login
    // (sesi habis), tab yang Reverb-nya putus, atau tab yang login sebagai user
    // lain tidak akan menampilkan toast ⇒ notifikasi hilang total padahal ack
    // tercatat "Delivered". Jadi tiap tab terlihat DITANYA (resources/js/
    // webpush.js, `whusnet-push-check`): baru di-skip kalau ada tab yang
    // menjawab sanggup. Tidak menjawab dalam 1 detik ⇒ tampilkan notifikasi OS.
    //
    // Aman terhadap aturan userVisibleOnly: Chrome tidak memaksakan notifikasi
    // bawaan "situs ini diperbarui di latar belakang" selama origin punya tab
    // yang terlihat. Ack tetap dikirim di kedua cabang.
    const uid = payload.data && payload.data.uid;
    tasks.push(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
            const visible = clientList.filter(function (client) {
                return client.visibilityState === 'visible';
            });

            const show = function () {
                return self.registration.showNotification(payload.title, options);
            };

            if (!uid || visible.length === 0) {
                return show();
            }

            return Promise.all(visible.map(function (client) { return askClientHandlesToast(client, uid); }))
                .then(function (answers) {
                    if (answers.some(Boolean)) {
                        return;
                    }
                    return show();
                });
        })
    );

    event.waitUntil(Promise.all(tasks));
});

/**
 * Tanya satu tab: "kamu akan menampilkan toast untuk user ini?" Jawaban
 * dari resources/js/webpush.js. Timeout/galat ⇒ false (lebih baik notifikasi
 * dobel daripada notifikasi hilang).
 */
function askClientHandlesToast(client, uid) {
    return new Promise(function (resolve) {
        const channel = new MessageChannel();
        const timer = setTimeout(function () { resolve(false); }, 1000);
        channel.port1.onmessage = function (event) {
            clearTimeout(timer);
            resolve(!!(event.data && event.data.handled));
        };
        try {
            client.postMessage({ type: 'whusnet-push-check', uid: uid }, [channel.port2]);
        } catch (e) {
            clearTimeout(timer);
            resolve(false);
        }
    });
}

// SW baru langsung aktif tanpa menunggu semua tab ditutup — file ini cuma
// berisi handler push/klik (tanpa cache aset), jadi tidak ada risiko tab lama
// melayani aset dari versi SW yang berbeda. Tanpa ini perubahan perilaku push
// di atas baru berlaku setelah user menutup SEMUA tab Whusnet.
self.addEventListener('install', function () {
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();

    // `url` di payload = path relatif dari AppPushNotification ($url, ADHOC-297)
    // — mis. /tasks/12. Di-resolve ke absolut dulu: client.url SELALU absolut,
    // jadi perbandingan lama `client.url === '/tasks/12'` tidak pernah cocok
    // dan tiap klik membuka tab baru. Hanya origin sendiri yang diterima —
    // payload push bukan tempat menitipkan link ke situs lain.
    const rawUrl = (event.notification.data && event.notification.data.url) || '/';
    let targetUrl = new URL('/', self.location.origin).href;
    try {
        const resolved = new URL(rawUrl, self.location.origin);
        if (resolved.origin === self.location.origin) {
            targetUrl = resolved.href;
        }
    } catch (e) {
        // URL rusak ⇒ tetap ke beranda.
    }

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
            // 1) Tab yang sudah membuka halaman tujuan ⇒ fokuskan saja.
            for (let i = 0; i < clientList.length; i++) {
                const client = clientList[i];
                if (client.url === targetUrl && 'focus' in client) {
                    return client.focus();
                }
            }

            // 2) Selain itu SELALU tab baru. Dulu tab Whusnet lain dipakai ulang
            //    lewat navigate() milik Client — itu melewati prompt beforeunload dan
            //    membuang isian yang belum dikirim (mis. wizard laporan PSB
            //    setengah jalan) tanpa peringatan (review ADHOC-297 #2).
            if (clients.openWindow) {
                return clients.openWindow(targetUrl);
            }
        })
    );
});

// Browser/push-service bisa ganti subscription kapan saja (rotasi internal,
// expiry). Handler ini cuma menjaga subscription tetap HIDUP di level
// browser (pushManager.subscribe ulang) — TIDAK langsung POST ke server,
// karena Service Worker tidak bisa baca meta[csrf-token] dari DOM (beda
// origin konteks), dan endpoint /webpush/subscribe sengaja tetap di-CSRF-
// protect (tanpa itu, POST lintas-situs bisa daftarin subscription
// penyerang atas nama korban = notifikasi pribadi korban kebaca penyerang).
// Sinkron ke server menyusul otomatis: resources/js/webpush.js memanggil
// subscribe ulang (idempotent) tiap halaman dimuat kalau user sudah pernah
// aktifkan — jadi endpoint basi tetap kekoreksi begitu user buka halaman
// apa saja, tanpa perlu CSRF-exempt endpoint ini.
self.addEventListener('pushsubscriptionchange', function (event) {
    event.waitUntil(
        fetch('/webpush/vapid-public-key')
            .then(function (res) { return res.json(); })
            .then(function (data) {
                return self.registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: data.publicKey,
                });
            })
    );
});
