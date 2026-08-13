{{--
    Realtime SELURUH aktivitas kas kolektor — dipakai bersama Worksheet Admin
    (index & detail) dan Worklist Kolektor.

    Mendengarkan dua event pada kanal yang sama:
      - `CollectorDepositUpdated`  → siklus setoran (diajukan…dihapus buku)
      - `CollectorActivityUpdated` → pembayaran dicatat/ditolak, rute berubah

    Parameter:
      - `channels` : array nama private channel yang didengarkan.
                     Admin  → ['collector-activity.{popId}', …]
                     Kolektor → ['App.Models.User.{id}']
      - `audiens`  : 'admin' | 'kolektor' — menentukan bunyi pesannya.

    Kenapa hanya memberi kabar, bukan menambal angka:
    halaman ini menghitung UANG FISIK. Kalau saldo berubah sendiri saat admin
    sedang menghitung uang di meja, dia melanjutkan hitungan dengan patokan yang
    berubah tanpa sadar. Jadi event dipakai sebagai ABA-ABA — muat ulang adalah
    keputusan manusia, bukan efek samping.
--}}
<div id="collector-realtime-bar"
     class="hidden sticky top-2 z-40 flex items-center gap-2.5 px-4 py-2.5 rounded-xl shadow-sm
            bg-sky-50 text-sky-900 border border-sky-200
            dark:bg-sky-950/60 dark:text-sky-200 dark:border-sky-800">
    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
    </svg>
    <span id="collector-realtime-text" class="text-xs font-semibold"></span>
    <button type="button" onclick="window.location.reload()"
            class="ml-auto text-xs font-bold underline underline-offset-2 hover:opacity-80 cursor-pointer shrink-0">
        Muat ulang
    </button>
</div>

@push('scripts')
<script>
    (function () {
        const CHANNELS = @js($channels);
        const AUDIENS = @js($audiens);

        if (! CHANNELS.length) return;

        const bar = document.getElementById('collector-realtime-bar');
        const text = document.getElementById('collector-realtime-text');

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
         * perubahan rute. Semuanya mengubah angka orang lain, dan sebelum ini
         * tak satu pun bersuara di halaman.
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

        function tampilkan(isi, jenis, judul) {
            if (text) text.textContent = isi + ' Angka di halaman ini belum ikut berubah.';
            if (bar) bar.classList.remove('hidden');

            if (window.Toast) {
                window.Toast.show(jenis, judul, isi, 7000);
            }
        }

        function tangani(e) {
            // Setoran menunggu verifikasi = pekerjaan yang menunggu orang,
            // jadi warnanya beda dari sekadar kabar selesai.
            const jenis = e.aksi === 'diajukan' && AUDIENS === 'admin' ? 'warning' : 'success';

            tampilkan(pesan(e), jenis, 'Setoran kolektor');
        }

        function tanganiAktivitas(e) {
            // Rute berkurang & pembayaran ditolak = sesuatu yang HILANG dari
            // pihak yang menerima kabar; jangan dibungkus hijau seolah kabar
            // baik.
            const buruk = e.aksi === 'pelanggan_dilepas' || e.aksi === 'pembayaran_ditolak';

            tampilkan(pesanAktivitas(e), buruk ? 'warning' : 'success', 'Aktivitas kolektor');
        }

        function pasang() {
            if (! window.Echo) return;

            CHANNELS.forEach(function (nama) {
                window.Echo.private(nama)
                    .listen('.CollectorDepositUpdated', tangani)
                    .listen('.CollectorActivityUpdated', tanganiAktivitas);
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
