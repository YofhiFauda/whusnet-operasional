{{--
    Realtime SELURUH aktivitas kas kolektor — dipakai bersama Worksheet Admin
    (index & detail) dan Worklist Kolektor.

    Mendengarkan tiga event pada kanal yang sama:
      - `CollectorDepositUpdated`  → siklus setoran (diajukan…dihapus buku)
      - `CollectorActivityUpdated` → pembayaran dicatat/ditolak, rute berubah
      - `CashDepositUpdated`       → setoran admin ke Owner/Bank diperiksa/ditutup selisih
                                      (cuma relevan buat channel App.Models.User.{id} admin)

    Parameter:
      - `channels` : array nama private channel yang didengarkan.
                     Admin  → ['collector-activity.{popId}', …, 'App.Models.User.{id}']
                     Kolektor → ['App.Models.User.{id}']
      - `audiens`  : 'admin' | 'kolektor' — menentukan bunyi pesannya.
      - `patchContainerId` : id elemen yang di-fetch-ulang & ditambal otomatis
                     tiap event masuk (default 'live-content').

    Riwayat keputusan (2026-08-21): sebelum ini halaman CUMA dikasih kabar
    (toast + bar "Muat ulang" manual) — sengaja TIDAK menambal angka sendiri,
    karena halaman ini menghitung UANG FISIK dan berubah diam-diam di tengah
    hitungan admin dianggap berbahaya. User secara eksplisit minta itu dicabut
    (SPA-like penuh, nol refresh manual/polling, "termasuk pas form kebuka") —
    jadi sekarang auto-tambal SELALU jalan, gak ada pengecualian "skip kalau
    modal/form lagi kebuka". Konsekuensinya: kalau admin lagi ngetik nominal di
    form yang berada di dalam `patchContainerId` pas event lain masuk,
    ketikannya bisa hilang tertimpa data fresh. Ini keputusan sadar, bukan bug
    — kalau mau diubah lagi, ubah di sini (titik tunggal).
--}}
@php
    $patchContainerId = $patchContainerId ?? 'live-content';
@endphp
@push('scripts')
<script>
    (function () {
        const CHANNELS = @js($channels);
        const AUDIENS = @js($audiens);
        const PATCH_CONTAINER_ID = @js($patchContainerId);

        if (! CHANNELS.length) return;

        function pesan(e) {
            const nominal = 'Rp' + Number(e.declared_amount || 0).toLocaleString('id-ID');

            if (AUDIENS === 'kolektor') {
                switch (e.aksi) {
                    case 'diverifikasi': return `Setoran ${e.deposit_number} sudah diperiksa kantor — ${e.status_label}.`;
                    case 'dilunasi': return `Kekurangan pada setoran ${e.deposit_number} berkurang — ${e.status_label}.`;
                    case 'dihapus_buku': return `Setoran ${e.deposit_number} dihapus buku oleh kantor.`;
                    default: return `Setoran ${e.deposit_number} tercatat — menunggu diperiksa kantor.`;
                }
            }

            switch (e.aksi) {
                case 'diajukan': return `${e.collector_name} menyetor ${nominal} (${e.deposit_number}) — menunggu verifikasi.`;
                case 'diverifikasi': return `Setoran ${e.deposit_number} milik ${e.collector_name}: ${e.status_label}.`;
                case 'dilunasi': return `Kekurangan setoran ${e.deposit_number} milik ${e.collector_name} berkurang.`;
                case 'dihapus_buku': return `Setoran ${e.deposit_number} milik ${e.collector_name} dihapus buku.`;
                default: return `Setoran ${e.deposit_number} berubah.`;
            }
        }

        function rupiah(n) {
            return 'Rp' + Number(n || 0).toLocaleString('id-ID');
        }

        /**
         * Aktivitas kas DI LUAR siklus setoran — pembayaran dicatat/ditolak dan
         * perubahan rute.
         */
        function pesanAktivitas(e) {
            const siapa = AUDIENS === 'kolektor' ? 'Anda' : e.collector_name;

            switch (e.aksi) {
                case 'pembayaran_dicatat':
                    return AUDIENS === 'kolektor'
                        ? `${e.jumlah} pembayaran (${rupiah(e.total)}) tercatat — saldo Anda bertambah.`
                        : `${siapa} mencatat ${e.jumlah} pembayaran (${rupiah(e.total)}) — saldonya bertambah.`;
                case 'pembayaran_ditolak':
                    return AUDIENS === 'kolektor'
                        ? `Pembayaran ${e.keterangan} (${rupiah(e.total)}) ditolak kantor — saldo Anda berkurang.`
                        : `Pembayaran ${e.keterangan} milik ${siapa} ditolak — saldonya berkurang.`;
                case 'pelanggan_diassign':
                    return AUDIENS === 'kolektor'
                        ? `${e.keterangan ?? e.jumlah + ' pelanggan'} masuk ke rute penagihan Anda.`
                        : `${e.keterangan ?? e.jumlah + ' pelanggan'} masuk ke rute ${siapa}.`;
                case 'pelanggan_dilepas':
                    return AUDIENS === 'kolektor'
                        ? `${e.keterangan ?? e.jumlah + ' pelanggan'} dikeluarkan dari rute Anda — jangan ditagih lagi.`
                        : `${e.keterangan ?? e.jumlah + ' pelanggan'} dikeluarkan dari rute ${siapa}.`;
                default:
                    return 'Aktivitas kolektor berubah.';
            }
        }

        function pesanCashDeposit(e) {
            switch (e.aksi) {
                case 'diverifikasi': return `Setoran kas Anda ke kantor (${e.deposit_number}): ${e.status_label}.`;
                case 'ditutup_selisih': return `Selisih setoran kas ${e.deposit_number} sudah ditutup kantor.`;
                default: return `Setoran kas ${e.deposit_number} berubah.`;
            }
        }

        /**
         * Tambal otomatis — fetch ulang URL halaman ini, cari elemen ber-id
         * PATCH_CONTAINER_ID di hasilnya, ganti elemen yang sama di halaman
         * sekarang. Sama persis pola `refreshFopTaskRow()`/`refreshTaskCard()`
         * (fop_tasks/index.blade.php, tasks/own.blade.php) — fetch + replace,
         * BUKAN reload, BUKAN polling.
         */
        function tambalOtomatis() {
            const current = document.getElementById(PATCH_CONTAINER_ID);
            if (! current) return;

            fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then((res) => res.text())
                .then((html) => {
                    const doc = new DOMParser().parseFromString(html, 'text/html');
                    const fresh = doc.getElementById(PATCH_CONTAINER_ID);
                    if (! fresh) return;

                    current.replaceWith(fresh);
                    if (window.Alpine) window.Alpine.initTree(fresh);
                })
                .catch(() => {
                    // Diam-diam gagal — halaman tetap nampilin data lama, gak ganggu kerjaan.
                });
        }

        function tampilkan(isi, jenis, judul) {
            if (window.Toast) {
                window.Toast.show(jenis, judul, isi, 6000);
            }
            tambalOtomatis();
        }

        function tangani(e) {
            const jenis = e.aksi === 'diajukan' && AUDIENS === 'admin' ? 'warning' : 'success';
            tampilkan(pesan(e), jenis, 'Setoran kolektor');
        }

        function tanganiAktivitas(e) {
            const buruk = e.aksi === 'pelanggan_dilepas' || e.aksi === 'pembayaran_ditolak';
            tampilkan(pesanAktivitas(e), buruk ? 'warning' : 'success', 'Aktivitas kolektor');
        }

        function tanganiCashDeposit(e) {
            tampilkan(pesanCashDeposit(e), 'success', 'Setoran kas ke kantor');
        }

        function pasang() {
            if (! window.Echo) return;

            CHANNELS.forEach(function (nama) {
                window.Echo.private(nama)
                    .listen('.CollectorDepositUpdated', tangani)
                    .listen('.CollectorActivityUpdated', tanganiAktivitas)
                    .listen('.CashDepositUpdated', tanganiCashDeposit);
            });
        }

        if (window.Echo) {
            pasang();
        } else {
            window.addEventListener('echo:ready', pasang, { once: true });
        }
    })();
</script>
@endpush
