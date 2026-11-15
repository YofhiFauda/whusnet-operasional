/**
 * pageEcho — listener Echo milik SATU halaman, lepas otomatis saat halaman
 * diganti (ADHOC-297 P4, view realtime).
 *
 * Kenapa perlu: `<body hx-boost="true">` mengganti isi <body> tanpa reload,
 * sedangkan `window.Echo` (dan channel-nya) hidup sepanjang tab. Dulu tiap
 * halaman realtime memanggil `Echo.private(ch).listen(ev, cb)` langsung:
 *   - buka halaman yang sama N kali ⇒ N handler menumpuk ⇒ satu event memicu
 *     N fetch/refresh/toast;
 *   - pindah ke halaman lain ⇒ handler lama tetap jalan dan menyentuh DOM
 *     halaman baru (atau fetch partial yang tak dipakai).
 *
 * Cara kerja: tiap listener ditautkan ke elemen jangkar milik halaman
 * (default `#main-content`, yang ikut terganti tiap navigasi boost). Begitu
 * jangkar lepas dari DOM, listener di-`stopListening()`. Penyapuan jalan saat
 * mendaftar listener baru, tiap `htmx:afterSettle`, dan malas saat event
 * datang — jadi tidak bergantung urutan event htmx.
 *
 * Listener yang memang hidup sepanjang tab (lonceng notifikasi, kartu Tasks
 * Saya) TIDAK memakai ini — mereka punya guard sekali-per-dokumen sendiri.
 *
 * Pemakaian (di dalam callback yang sudah memastikan window.Echo ada):
 *   window.pageEcho('tickets.' + popId)
 *       .listen('.TicketQueueUpdated', refresh);
 */
const bindings = [];

function sweep() {
    for (let i = bindings.length - 1; i >= 0; i--) {
        const binding = bindings[i];
        if (!binding.anchor.isConnected) {
            binding.channel.stopListening(binding.event, binding.handler);
            bindings.splice(i, 1);
        }
    }
}

function pageEcho(channelName, anchor = document.getElementById('main-content') || document.body) {
    sweep();

    const channel = window.Echo.private(channelName);
    const api = {
        listen(event, callback) {
            const handler = (payload) => {
                if (!anchor.isConnected) {
                    sweep();
                    return;
                }
                callback(payload);
            };

            channel.listen(event, handler);
            bindings.push({ anchor, channel, event, handler });

            return api;
        },
    };

    return api;
}

// Dipakai test/diagnosa di console: berapa listener halaman yang masih aktif.
pageEcho.activeCount = () => bindings.length;

window.pageEcho = pageEcho;
document.addEventListener('htmx:afterSettle', sweep);
