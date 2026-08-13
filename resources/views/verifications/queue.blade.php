@extends('layouts.app')

@section('title', 'Proses Verifikasi & Pemasangan - Whusnet Operasional')
@section('page_title', 'Antrean Verifikasi & Pemasangan')
@section('breadcrumb_parent', 'Pelanggan')
@section('breadcrumb_parent_url', '/customers')

@section('content')
<div x-data="processToTimHandler()">

{{-- Page header — naked, tanpa card (Design.md §1.5). --}}
<div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2 mb-5">
    <div class="min-w-0">
        <h1 class="text-lg sm:text-xl font-bold text-text-main leading-tight">Antrean Verifikasi Lapangan</h1>
        <p class="text-xs sm:text-sm text-text-muted mt-0.5">Pelanggan yang menunggu ACC, pemasangan, dan verifikasi admin.</p>
    </div>
    <span class="shrink-0 self-start inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg border border-border bg-surface text-xs text-text-muted">
        <span class="font-mono font-semibold text-text-main">{{ $customers->total() }}</span> pelanggan
    </span>
</div>

{{-- Filter bar — naked, tanpa card. Di mobile input dan tombol menumpuk
     penuh lebar supaya tak ada tombol yang terpotong di 360px. --}}
<form action="{{ route('verifications.queue') }}" method="GET" class="flex flex-col sm:flex-row sm:items-center gap-2 mb-5">
    <label for="search" class="sr-only">Cari pelanggan</label>
    <div class="relative flex-1 sm:max-w-md">
        <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-text-muted pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" /></svg>
        <input type="text" name="search" id="search" value="{{ request('search') }}" placeholder="Cari nama, No. HP, atau ID Lama..."
               class="w-full h-10 pl-9 pr-3 text-sm font-sans border border-border rounded-lg bg-surface text-text-main placeholder:text-text-muted focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20">
    </div>
    <div class="flex gap-2">
        <button type="submit" class="h-10 px-5 flex-1 sm:flex-none bg-primary hover:bg-primary/90 text-white text-sm font-medium rounded-lg transition-colors cursor-pointer focus:outline-none focus:ring-2 focus:ring-primary/25">
            Cari
        </button>
        <a href="{{ route('verifications.queue') }}" class="h-10 px-4 flex-1 sm:flex-none inline-flex items-center justify-center bg-surface hover:bg-surface-muted border border-border text-text-main text-sm font-medium rounded-lg transition-colors cursor-pointer">
            Reset
        </a>
    </div>
</form>

{{-- Panel antrean — satu-satunya card di halaman ini (Type A, card budget 1).
     @container bikin panel ini jadi acuan ukur buat isinya, sehingga tampilan
     ikut menyesuaikan saat sidebar di-collapse/expand tanpa reload — sesuatu
     yang tidak bisa dilakukan media query, karena viewport-nya tidak berubah. --}}
<div class="@container bg-surface border border-border rounded-lg overflow-hidden">
    <div class="border-b border-border bg-surface-muted/50 dark:bg-transparent px-4 sm:px-6 py-3">
        <span class="text-[11px] sm:text-xs font-bold text-text-muted uppercase tracking-wider">Daftar Antrean</span>
    </div>

    {{-- DESKTOP: tabel penuh. Ambang pemilihan tabel/kartu dipegang container
         query (@min-[52rem]), BUKAN breakpoint viewport: lebar yang tersedia
         buat tabel = viewport − sidebar − padding, dan sidebar bisa 256px
         (expanded) atau 80px (collapsed). Dengan breakpoint viewport, laptop
         1024 sidebar-expanded cuma menyisakan ~728px tapi tetap dipaksa
         merender 8 kolom — itu sumber tabel kepotong yang dilaporkan.
         Kolom nomor urut dihapus — angkanya di-reset tiap halaman jadi tidak
         berarti apa-apa (Design.md §6.2.1). --}}
    <div class="hidden @min-[52rem]:block overflow-x-auto">
        <table class="w-full min-w-[800px] border-collapse text-left text-sm text-text-main">
            <thead>
                <tr class="bg-surface-muted/50 dark:bg-transparent border-b border-border text-text-muted font-semibold text-[11px] uppercase tracking-wider">
                    <th scope="col" class="px-4 py-3">ID</th>
                    <th scope="col" class="px-4 py-3">Nama</th>
                    <th scope="col" class="px-4 py-3">HP</th>
                    <th scope="col" class="px-4 py-3">Desa</th>
                    <th scope="col" class="px-4 py-3">Masuk Antrean</th>
                    <th scope="col" class="px-4 py-3 text-center">Status</th>
                    <th scope="col" class="px-4 py-3">Waktu (Live)</th>
                    <th scope="col" class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse($customers as $customer)
                    @php
                        $installation = $customer->latestInstallation;
                    @endphp
                    <tr class="hover:bg-surface-muted/45 transition-colors" id="customer-row-{{ $customer->id }}" data-pop-id="{{ $customer->pop_id }}">
                        <td class="px-4 py-3.5 whitespace-nowrap font-mono text-xs">{{ $customer->display_id }}</td>
                        <td class="px-4 py-3.5 font-medium text-text-main">{{ $customer->full_name }}</td>
                        <td class="px-4 py-3.5 font-mono text-xs whitespace-nowrap">{{ $customer->primary_phone }}</td>
                        <td class="px-4 py-3.5">{{ $customer->village->name ?? '-' }}</td>
                        <td class="px-4 py-3.5 font-mono text-xs whitespace-nowrap">{{ $customer->created_at->format('Y-m-d H:i') }}</td>
                        @include('verifications.partials.queue-status-cells', ['customer' => $customer, 'installation' => $installation])
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-6 py-10 text-center text-text-muted">
                            <div class="flex flex-col items-center justify-center gap-2">
                                <svg class="w-8 h-8 text-border" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" /></svg>
                                <span class="text-sm font-medium">Tidak ada antrean saat ini.</span>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- MOBILE, TABLET, dan laptop sempit (mis. 1024 dengan sidebar expanded):
         daftar kartu. Di container 36rem+ kartu jadi 2 kolom — di bawah itu
         (HP & tablet) tetap 1 kolom persis seperti sebelumnya. --}}
    <div class="@min-[52rem]:hidden p-3 sm:p-4 bg-background/40">
        <div class="grid grid-cols-1 @min-[36rem]:grid-cols-2 gap-3">
        @forelse($customers as $customer)
            @include('verifications.partials.queue-card', ['customer' => $customer, 'installation' => $customer->latestInstallation])
        @empty
            <div class="col-span-full flex flex-col items-center justify-center gap-2 py-10 text-text-muted">
                <svg class="w-8 h-8 text-border" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" /></svg>
                <span class="text-sm font-medium">Tidak ada antrean saat ini.</span>
            </div>
        @endforelse
        </div>
    </div>

    @if($customers->hasPages())
        <div class="border-t border-border px-4 sm:px-6 py-3 sm:py-4 bg-surface-muted/50 dark:bg-transparent">
            {{ $customers->links() }}
        </div>
    @endif
</div>

{{-- Modal Final Verify telah dipindahkan ke halaman verifications/admin.blade.php --}}

{{-- Modal Reject — di mobile menempel ke bawah layar (bottom sheet) supaya
     tombolnya berada dalam jangkauan ibu jari, bukan melayang di tengah. --}}
<div id="rejectModal" class="fixed inset-0 z-[100] flex items-end sm:items-center justify-center hidden bg-black/50 backdrop-blur-sm transition-opacity opacity-0 duration-300">
    <div class="bg-surface border border-border rounded-t-lg sm:rounded-lg shadow-2xl w-full sm:max-w-md max-h-[90vh] overflow-y-auto transform translate-y-4 sm:translate-y-0 sm:scale-95 transition-transform duration-300">
        <div class="flex justify-between items-center px-4 sm:px-6 py-4 border-b border-border bg-error-bg/60">
            <h3 class="text-base sm:text-lg font-bold text-error">Batalkan / Gagal Pelanggan</h3>
            <button type="button" onclick="closeRejectModal()" aria-label="Tutup" class="text-text-muted hover:text-text-main transition-colors focus:outline-none rounded-lg hover:bg-surface-muted p-1.5 cursor-pointer">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>

        <form id="rejectForm" method="POST" action="">
            @csrf
            <div class="p-4 sm:p-6 space-y-4">
                <div>
                    <label for="rejectReason" class="block text-xs font-semibold text-text-muted mb-2">ALASAN PENOLAKAN <span class="text-error">*</span></label>
                    <textarea name="reason" id="rejectReason" rows="3" class="w-full text-sm px-3 py-2 border border-border rounded-lg focus:outline-none focus:border-error focus:ring-1 focus:ring-error bg-surface text-text-main" required placeholder="Contoh: Lokasi tidak terjangkau jaringan (ODP Penuh)..."></textarea>
                </div>
            </div>

            <div class="px-4 sm:px-6 py-4 border-t border-border bg-surface-muted dark:bg-transparent flex flex-col-reverse sm:flex-row sm:justify-end gap-2 sm:gap-3">
                <button type="button" onclick="closeRejectModal()" class="h-10 px-5 text-sm font-medium text-text-muted bg-surface border border-border rounded-lg hover:bg-surface-muted transition-colors cursor-pointer">Tutup</button>
                <button type="submit" class="h-10 px-5 text-sm font-medium text-white bg-error rounded-lg hover:bg-error/90 transition-colors shadow-sm cursor-pointer">Batalkan / Gagal</button>
            </div>
        </form>
    </div>
</div>

    {{-- Drawer/modal selection no longer needed as verifikasi is direct --}}
</div>

<script>
    function processToTimHandler() {
        return {};
    }


    /**
     * @param {string} rejectUrl URL POST yang sudah dirender server-side oleh
     *   verifications/partials/queue-actions.blade.php (`route('customers.verification.reject')`).
     *   JANGAN kembali merakit path dari id di sini — path route tidak boleh
     *   diduplikasi sebagai string literal di klien (ADHOC-20 langkah 3).
     */
    function openRejectModal(rejectUrl) {
        const modal = document.getElementById('rejectModal');
        const form = document.getElementById('rejectForm');

        if (!rejectUrl) {
            if (window.Toast) {
                window.Toast.error('Aksi Gagal', 'Target penolakan tidak dikenal. Muat ulang halaman.');
            }
            return;
        }
        form.action = rejectUrl;

        modal.classList.remove('hidden');
        setTimeout(() => {
            modal.classList.remove('opacity-0');
            modal.querySelector('div').classList.remove('scale-95', 'translate-y-4');
        }, 10);
    }

    function closeRejectModal() {
        const modal = document.getElementById('rejectModal');
        modal.classList.add('opacity-0');
        modal.querySelector('div').classList.add('scale-95', 'translate-y-4');
        setTimeout(() => {
            modal.classList.add('hidden');
            document.getElementById('rejectForm').reset();
        }, 300);
    }

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') {
            return;
        }
        const modal = document.getElementById('rejectModal');
        if (modal && !modal.classList.contains('hidden')) {
            closeRejectModal();
        }
    });

    // Live Countdown Logic — elemen di-query ulang tiap tick, bukan sekali di
    // DOMContentLoaded: baris/kartu yang diganti oleh refreshVerificationRow()
    // adalah node BARU, jadi NodeList lama tidak lagi menunjuk ke apa pun yang
    // tampil dan countdown-nya berhenti di "Menghitung...".
    document.addEventListener('DOMContentLoaded', function() {
        function updateCountdowns() {
            const now = new Date();

            document.querySelectorAll('[id^="countdown-"]').forEach(el => {
                const startTimeStr = el.getAttribute('data-start');
                if (!startTimeStr) return;

                const startTime = new Date(startTimeStr);
                const diffMs = now - startTime;

                if (diffMs < 0) {
                    el.textContent = "00:00:00";
                    return;
                }

                const hours = Math.floor(diffMs / 3600000);
                const minutes = Math.floor((diffMs % 3600000) / 60000);
                const seconds = Math.floor((diffMs % 60000) / 1000);

                const h = String(hours).padStart(2, '0');
                const m = String(minutes).padStart(2, '0');
                const s = String(seconds).padStart(2, '0');

                el.textContent = `${h}:${m}:${s}`;
            });
        }

        updateCountdowns();
        setInterval(updateCountdowns, 1000);
    });

    // Realtime tanpa reload: begitu App\Events\CustomerVerificationStatusChanged
    // masuk buat pelanggan yang lagi tampil di baris ini, refetch 3 sel
    // (STATUS/WAKTU/ACTION) lewat verifications.row — nyegah 2 admin
    // verifikasi pelanggan yang sama tanpa saling tahu (docs/plan/analisa-
    // realtime-spa-operasional.md §2.1 no. 10). Baris yang udah keluar
    // cakupan antrean (endpoint balikin 204) langsung dihapus dari layar.
    //
    // Sejak halaman punya dua tampilan (tabel ≥lg, kartu <lg), KEDUANYA ada di
    // DOM sekaligus dan keduanya disegarkan dari fragment yang sama: tabel
    // menukar <td> utuh, kartu menyalin innerHTML tiap sel ke slot -card-.
    function refreshVerificationRow(customerId) {
        const row = document.getElementById('customer-row-' + customerId);
        const card = document.getElementById('customer-card-' + customerId);
        if (!row && !card) {
            return;
        }

        // Penanda baris sedang disegarkan. Tanpa ini, baris yang berubah karena
        // admin LAIN memverifikasi terlihat seperti diam saja lalu tiba-tiba
        // berganti isi — dan kalau permintaannya gagal, tak ada bedanya dengan
        // "memang belum ada perubahan".
        [row, card].forEach(function (el) {
            if (el) { el.classList.add('opacity-60', 'transition-opacity'); }
        });
        const selesai = function () {
            [row, card].forEach(function (el) {
                if (el) { el.classList.remove('opacity-60'); }
            });
        };

        fetch('/verifications/' + customerId + '/row', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        }).then(function (res) {
            if (res.status === 204) {
                if (row) { row.remove(); }
                if (card) { card.remove(); }
                return null;
            }
            return res.text();
        }).then(function (html) {
            if (!html) {
                return;
            }

            const wrapper = document.createElement('table');
            wrapper.innerHTML = '<tbody><tr>' + html + '</tr></tbody>';

            ['status', 'live', 'action'].forEach(function (part) {
                const fresh = wrapper.querySelector('#customer-' + part + '-cell-' + customerId);
                if (!fresh) {
                    return;
                }

                const slotKartu = document.getElementById('customer-' + part + '-card-' + customerId);
                if (slotKartu) {
                    // Kartu memakai id countdown ber-prefix 'card-' supaya tidak
                    // bentrok dengan kembarannya di tabel; fragment dari server
                    // selalu versi tabel, jadi prefiksnya dipasang di sini.
                    const salinan = fresh.cloneNode(true);
                    const timer = salinan.querySelector('[id^="countdown-"]');
                    if (timer) {
                        timer.id = 'countdown-card-' + customerId;
                    }
                    slotKartu.innerHTML = salinan.innerHTML;
                }

                if (row) {
                    const current = row.querySelector('#customer-' + part + '-cell-' + customerId);
                    if (current) {
                        current.replaceWith(fresh);
                    }
                }
            });
        }).catch(function () {
            // Barisnya tetap menampilkan data lama — itu keputusan yang benar,
            // aksi admin tak boleh terganggu. Tapi kegagalannya TIDAK lagi
            // dipendam: tanpa satu pun tanda, baris basi tak bisa dibedakan dari
            // baris yang memang belum berubah, dan dua admin bisa memverifikasi
            // pelanggan yang sama — persis yang mau dicegah refresh ini.
            if (window.Toast) {
                window.Toast.show('error', 'Gagal menyegarkan baris', 'Data pelanggan ini mungkin tertinggal. Muat ulang halaman.', 6000);
            }
        }).finally(selesai);
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof window.Echo === 'undefined' || !window.Echo) {
            return;
        }

        const popIds = [...new Set(
            Array.from(document.querySelectorAll('[data-pop-id]')).map(function (el) {
                return el.getAttribute('data-pop-id');
            })
        )];

        popIds.forEach(function (popId) {
            window.Echo.private('customers.' + popId)
                .listen('.CustomerVerificationStatusChanged', function (e) {
                    refreshVerificationRow(e.customer_id);
                });
        });
    });
</script>
@endsection
