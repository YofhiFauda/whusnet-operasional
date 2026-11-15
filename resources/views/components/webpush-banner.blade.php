<script>
// Popup "aktifkan notifikasi" — tampil SEKALI (localStorage per-device, bukan
// per-user/server) di halaman pertama setelah login, lewat window.Dialog
// (global, components/dialog.blade.php) biar gak kejebak scroll/posisi kayak
// versi banner inline sebelumnya. Beda sama toggle di Profil: itu bisa
// aktif/nonaktif berkali-kali, ini cuma nawarin SEKALI. ADHOC-288.
(function () {
    // Kunci localStorage disertain user ID — device bisa dipakai gantian
    // antar akun (mis. superadmin logout, teknisi login di browser sama).
    // PushManager browser cuma satu per origin, jadi kalau kunci gak
    // di-scope per-user, user kedua yang login bakal kebaca "udah dismiss/
    // aktif" padahal itu keputusan/subscription punya user SEBELUMNYA.
    var dismissKey = 'webpush_banner_dismissed_{{ auth()->id() }}';

    function maybeShowWebpushPrompt() {
        if (localStorage.getItem(dismissKey) === '1') {
            return;
        }

        if (!window.WebPush || !window.WebPush.isSupported() || !window.Dialog) {
            return;
        }

        if (Notification.permission === 'denied') {
            // Browser gak akan munculin prompt izin lagi kalau udah ditolak —
            // nawarin di sini percuma. Arahkan ke Profil, bukan dipaksa di sini.
            localStorage.setItem(dismissKey, '1');
            return;
        }

        window.WebPush.isEnabledForCurrentUser().then(function (enabled) {
            if (enabled) {
                // Udah subscribe (mis. lewat Profil) — gak perlu nawarin lagi.
                localStorage.setItem(dismissKey, '1');
                return;
            }

            window.Dialog.show({
                title: 'Aktifkan Notifikasi Perangkat',
                icon: 'info',
                message: 'Biar gak ketinggalan tiket prioritas, penjadwalan task, dan info penting lainnya — aktifkan notifikasi perangkat untuk situs ini?',
                // Tombol X di header Dialog manggil Dialog.close() langsung,
                // gak lewat onClick "Nanti Saja" — tanpa ini nutup lewat X gak
                // ninggalin tanda dan popup nongol lagi di tiap halaman. X =
                // "Nanti Saja" (keputusan user 2026-11-15); aktifkan ulang
                // tetap bisa dari Profil.
                onClose: function () {
                    localStorage.setItem(dismissKey, '1');
                },
                buttons: [
                    {
                        text: 'Nanti Saja',
                        type: 'secondary',
                        onClick: function () {
                            localStorage.setItem(dismissKey, '1');
                            window.Dialog.close();
                        },
                    },
                    {
                        text: 'Aktifkan',
                        type: 'primary',
                        onClick: function (e, dialog) {
                            window.WebPush.subscribe()
                                .then(function () {
                                    localStorage.setItem(dismissKey, '1');
                                    window.Dialog.close();
                                    if (window.Toast) {
                                        window.Toast.success('Notifikasi Perangkat', 'Notifikasi perangkat berhasil diaktifkan.');
                                    }
                                })
                                .catch(function (err) {
                                    // Gagal pun ditandai "sudah ditawari": tanpa ini popup
                                    // muncul lagi di TIAP halaman dan user ketemu error yang
                                    // sama berulang-ulang. Coba ulang tetap bisa dari Profil.
                                    localStorage.setItem(dismissKey, '1');
                                    window.Dialog.close();
                                    if (window.Toast) {
                                        window.Toast.error('Notifikasi Perangkat', (err.message || 'Gagal mengaktifkan notifikasi.') + ' Anda bisa mencoba lagi dari halaman Profil.');
                                    }
                                });
                        },
                    },
                ],
            });
        }).catch(function () {});
    }

    // Tunggu sebentar biar app.js (window.WebPush/window.Dialog/window.Toast)
    // sempat selesai ter-register dulu sebelum dicek — pola sama kayak retry
    // window.Echo di notification-dropdown.blade.php.
    setTimeout(maybeShowWebpushPrompt, 800);
})();
</script>
