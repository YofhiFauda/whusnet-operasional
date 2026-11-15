/* Web Push client (ADHOC-288, Tahap 5).
   docs/plan/notifications/rancangan-arsitektur-web-push-pwa-nextjs.md §5A/§6

   Diekspos lewat window.WebPush (bukan ES import) karena dipanggil dari
   Alpine.data() inline di notification-dropdown.blade.php — komponen itu
   sendiri didaftarkan lewat <script> global (pola sama dengan
   notificationDropdown()/window.Toast), bukan modul ES. */

// VAPID public key dari server berformat base64url; Push API butuh
// Uint8Array mentah sebagai applicationServerKey.
function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);

    for (let i = 0; i < rawData.length; i++) {
        outputArray[i] = rawData.charCodeAt(i);
    }

    return outputArray;
}

function csrfHeaders() {
    return {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        'Accept': 'application/json',
    };
}

// URL endpoint webpush dirender server lewat route() di <head> layout
// (window.WebPushRoutes) — klien tidak boleh merakit path POST sendiri
// (CLAUDE.md "Target aksi yang mengubah data dirender server-side";
// review ADHOC-297 #12).
function routeFor(name) {
    const url = window.WebPushRoutes && window.WebPushRoutes[name];
    if (!url) {
        throw new Error('Route webpush "' + name + '" tidak tersedia di halaman ini.');
    }

    return url;
}

function isSupported() {
    return 'serviceWorker' in navigator && 'PushManager' in window;
}

async function registerServiceWorker() {
    return navigator.serviceWorker.register('/sw.js');
}

async function getActiveSubscription() {
    const registration = await registerServiceWorker();

    return registration.pushManager.getSubscription();
}

// PushManager itu per BROWSER/device+origin, bukan per akun aplikasi — kalau
// device dipakai gantian (mis. superadmin subscribe, logout, teknisi login
// di browser sama), getActiveSubscription() tetap balikin subscription LAMA
// biarpun secara DB itu masih kepunyaan user sebelumnya (belum di-reassign,
// karena subscribe() belum pernah dipanggil buat user yang baru login).
// Fungsi ini nambah pengecekan ke server: subscription browser itu ada di
// daftar subscription MILIK USER YANG LOGIN SEKARANG gak.
async function isEnabledForCurrentUser() {
    const subscription = await getActiveSubscription();
    if (!subscription) {
        return false;
    }

    const response = await fetch(routeFor('subscriptions'), {
        headers: { 'Accept': 'application/json' },
    });
    if (!response.ok) {
        return false;
    }

    const body = await response.json();
    const own = body.subscriptions || [];

    return own.some(function (row) { return row.endpoint === subscription.endpoint; });
}

// Error yang pesannya SUDAH ramah pengguna (ditulis sendiri di file ini) —
// dibedakan dari DOMException/TypeError bawaan browser yang pesannya teknis
// (mis. "Registration failed - push service error", "Failed to fetch") dan
// dulu langsung nongol mentah di toast.
class WebPushUserError extends Error {}

// Terjemahkan error apa pun dari alur subscribe jadi kalimat yang bisa
// ditindaklanjuti pengguna. Detail teknis tetap ke console buat debugging.
function friendlySubscribeError(err) {
    if (err instanceof WebPushUserError) {
        return err;
    }

    console.error('[WebPush] Gagal mengaktifkan notifikasi:', err);

    const name = err && err.name;
    const message = String((err && err.message) || '');

    if (name === 'NotAllowedError') {
        return new WebPushUserError('Izin notifikasi ditolak. Izinkan notifikasi untuk situs ini lewat ikon gembok di address bar, lalu coba lagi.');
    }
    // Layanan push milik browser gak bisa dihubungi. Paling sering di Brave
    // (push dimatikan bawaan), browser mode privat/incognito, atau jaringan
    // yang memblokir server push browser.
    if (name === 'AbortError' || /push service/i.test(message)) {
        return new WebPushUserError('Browser ini belum bisa menerima notifikasi push. Pastikan tidak dalam mode Incognito/Private; di Brave, aktifkan "Use Google services for push messaging" di Settings > Privacy and security, lalu coba lagi.');
    }
    if (name === 'InvalidStateError') {
        return new WebPushUserError('Notifikasi lama di browser ini bentrok. Muat ulang halaman lalu coba aktifkan lagi.');
    }
    if (err instanceof TypeError) {
        return new WebPushUserError('Gagal terhubung ke server. Periksa koneksi internet lalu coba lagi.');
    }

    return new WebPushUserError('Notifikasi perangkat gagal diaktifkan di browser ini. Muat ulang halaman lalu coba lagi.');
}

async function subscribe() {
    try {
        return await subscribeOrThrow();
    } catch (err) {
        throw friendlySubscribeError(err);
    }
}

async function subscribeOrThrow() {
    if (!isSupported()) {
        throw new WebPushUserError('Browser ini tidak mendukung notifikasi push.');
    }

    const permission = await Notification.requestPermission();
    if (permission !== 'granted') {
        throw new WebPushUserError('Izin notifikasi ditolak. Izinkan notifikasi untuk situs ini lewat ikon gembok di address bar, lalu coba lagi.');
    }

    const registration = await registerServiceWorker();
    let subscription = await registration.pushManager.getSubscription();

    if (!subscription) {
        const keyResponse = await fetch(routeFor('vapidKey'), {
            headers: { 'Accept': 'application/json' },
        });
        const { publicKey } = keyResponse.ok ? await keyResponse.json() : {};

        // VAPID_PUBLIC_KEY belum diisi di .env server ⇒ endpoint balikin null
        // dan urlBase64ToUint8Array(null) crash "Cannot read properties of
        // null (reading 'length')". Bukan salah user — bilang apa adanya.
        if (!publicKey) {
            throw new WebPushUserError('Notifikasi perangkat belum disiapkan di server. Hubungi admin.');
        }

        subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(publicKey),
        });
    }

    const json = subscription.toJSON();

    const response = await fetch(routeFor('subscribe'), {
        method: 'POST',
        headers: csrfHeaders(),
        body: JSON.stringify({
            endpoint: json.endpoint,
            keys: json.keys,
        }),
    });

    if (response.status === 419 || response.status === 401) {
        throw new WebPushUserError('Sesi login sudah berakhir. Muat ulang halaman lalu coba lagi.');
    }
    if (!response.ok) {
        throw new WebPushUserError('Server gagal menyimpan pendaftaran notifikasi. Coba lagi beberapa saat lagi.');
    }

    return subscription;
}

// ADHOC-288 Tahap 8 — "Kirim notifikasi uji" di halaman Profil.
async function sendTest() {
    const response = await fetch(routeFor('test'), {
        method: 'POST',
        headers: csrfHeaders(),
    });

    const body = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new Error(body.message || 'Gagal mengirim notifikasi uji.');
    }

    return body;
}

// ADHOC-288 Tahap 8 — iOS Safari di luar mode standalone (belum di-"Add to
// Home Screen") TIDAK mendukung Push API sama sekali, beda dari Android/
// desktop. isSupported() di atas sudah otomatis false di situ (gak ada
// 'PushManager' in window), tapi UI butuh tau KENAPA biar bisa nunjukin
// panduan install PWA, bukan cuma "tidak didukung" generik.
function isIosSafariNonStandalone() {
    const ua = navigator.userAgent || '';
    const isIos = /iPad|iPhone|iPod/.test(ua) && !window.MSStream;
    const isStandalone = window.navigator.standalone === true
        || window.matchMedia('(display-mode: standalone)').matches;

    return isIos && !isStandalone;
}

// ADHOC-288 Tahap 8 — panduan battery saver per merek HP di halaman
// onboarding Profil. Pola regex SENGAJA disalin manual dari
// App\Support\DeviceBrandDetector (PHP, dipakai server buat label dashboard
// delivery) — bukan di-share lewat endpoint, karena ini cuma teks bantuan
// statis di klien, gak perlu bolak-balik ke server tiap render halaman.
function detectBrand() {
    const ua = navigator.userAgent || '';

    if (/Xiaomi|MIUI|Redmi|POCO/i.test(ua)) {
        return 'xiaomi';
    }
    if (/OPPO|CPH\d|realme|RMX\d/i.test(ua)) {
        return 'oppo';
    }
    if (/vivo/i.test(ua)) {
        return 'vivo';
    }
    if (/SM-|Samsung/i.test(ua)) {
        return 'samsung';
    }
    if (/iPhone|iPad|iPod/i.test(ua)) {
        return 'ios';
    }

    return 'lainnya';
}

const BATTERY_SAVER_GUIDES = {
    xiaomi: 'HP Xiaomi/Redmi/POCO (MIUI) sering nge-kill notifikasi di background. Buka Setelan > Aplikasi > Whusnet Operasional > Hemat Baterai, pilih "Tanpa batasan". Aktifkan juga "Autostart" buat aplikasi ini.',
    oppo: 'HP OPPO/realme (ColorOS) suka ngebatasin aplikasi background. Buka Setelan > Baterai > Whusnet Operasional, matikan optimasi baterai. Cek juga Setelan > Privasi > Startup Manager, aktifkan autostart.',
    vivo: 'HP vivo (Funtouch OS) agresif nutup aplikasi background. Buka Setelan > Baterai > Konsumsi Baterai Tinggi, kecualikan aplikasi ini. Aktifkan juga autostart di i Manager > Izin Aplikasi.',
    samsung: 'HP Samsung kadang nge-tidurin aplikasi yang jarang dibuka. Buka Setelan > Baterai > Batasan Penggunaan Baterai, pastikan aplikasi ini TIDAK masuk daftar "Tidur" atau "Tidur Dalam".',
    ios: null, // ditangani lewat panduan standalone/PWA terpisah, bukan battery saver.
    lainnya: null,
};

async function unsubscribe() {
    const subscription = await getActiveSubscription();
    if (!subscription) {
        return;
    }

    const endpoint = subscription.endpoint;
    await subscription.unsubscribe();

    await fetch(routeFor('unsubscribe'), {
        method: 'POST',
        headers: csrfHeaders(),
        body: JSON.stringify({ endpoint }),
    });
}

window.WebPush = {
    isSupported,
    subscribe,
    unsubscribe,
    getActiveSubscription,
    isEnabledForCurrentUser,
    sendTest,
    isIosSafariNonStandalone,
    detectBrand,
    batterySaverGuide: function (brand) { return BATTERY_SAVER_GUIDES[brand] || null; },
};

/*
 * Jawaban untuk public/sw.js (`whusnet-push-check`, review ADHOC-297 #1):
 * SW cuma menahan notifikasi OS kalau ada tab terlihat yang PASTI menampilkan
 * toast lonceng — login sebagai penerima yang sama, Reverb tersambung, dan
 * listener lonceng sudah terpasang (components/notification-dropdown).
 * Halaman login / sesi habis / Reverb putus menjawab false ⇒ notifikasi OS
 * tetap tampil.
 */
function canShowToastFor(uid) {
    const userId = Number(document.querySelector('meta[name="user-id"]')?.content || 0);
    const state = window.Echo?.connector?.pusher?.connection?.state;

    return userId > 0
        && userId === Number(uid)
        && state === 'connected'
        && window.__notificationEchoBound === userId
        && document.visibilityState === 'visible';
}

if ('serviceWorker' in navigator) {
    navigator.serviceWorker.addEventListener('message', function (event) {
        if (!event.data || event.data.type !== 'whusnet-push-check' || !event.ports || !event.ports[0]) {
            return;
        }
        event.ports[0].postMessage({ handled: canShowToastFor(event.data.uid) });
    });
    navigator.serviceWorker.startMessages();
}

/*
 * Logout melepas subscription browser ini dari akun yang keluar (review
 * ADHOC-297 #9). PushManager itu per device, bukan per akun — tanpa ini push
 * milik admin tetap muncul di perangkat bersama setelah teknisi login.
 * Endpoint dititipkan ke field tersembunyi #logout-form; LoginController::
 * logout() menghapus subscription milik user itu untuk endpoint tersebut.
 * Diisi ulang tiap navigasi boost karena #logout-form ikut ditukar.
 */
function fillLogoutPushEndpoint() {
    const field = document.querySelector('#logout-form input[name="push_endpoint"]');
    if (!field || !isSupported()) {
        return;
    }
    navigator.serviceWorker.getRegistration()
        .then(function (registration) { return registration ? registration.pushManager.getSubscription() : null; })
        .then(function (subscription) {
            if (subscription && field.isConnected) {
                field.value = subscription.endpoint;
            }
        })
        .catch(function () {});
}

fillLogoutPushEndpoint();
document.addEventListener('htmx:afterSettle', fillLogoutPushEndpoint);
