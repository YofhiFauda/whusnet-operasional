@extends('layouts.app')

@section('title', 'Proses Verifikasi & Pemasangan - Whusnet Operasional')
@section('page_title', 'Antrean Verifikasi & Pemasangan')
@section('breadcrumb_parent', 'Pelanggan')
@section('breadcrumb_parent_url', '/customers')

@section('content')
<div x-data="processToTimHandler()">
<!-- Top Action Bar -->
<div class="flex justify-between items-center mb-6">
    <h3 class="text-text-main text-sm font-semibold uppercase tracking-wider">Antrean Verifikasi Lapangan</h3>
</div>

<!-- Filter & Search Panel -->
<div class="bg-surface border border-border rounded-lg p-6 mb-6">
    <form action="{{ route('verifications.queue') }}" method="GET" class="flex flex-col sm:flex-row gap-4 items-end">
        <!-- Search -->
        <div class="flex-1">
            <label for="search" class="block text-xs font-semibold text-text-muted mb-2">CARI PELANGGAN</label>
            <input type="text" name="search" id="search" value="{{ request('search') }}" placeholder="Cari nama, No. HP, atau ID Lama..." class="w-full font-sans text-sm px-3 py-2 border border-border rounded-md bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25">
        </div>

        <!-- Action Buttons -->
        <div class="flex gap-2">
            <button type="submit" class="bg-primary hover:bg-primary/90 text-white text-sm font-medium py-2 px-6 rounded-md transition-colors cursor-pointer focus:outline-none focus:ring-2 focus:ring-primary/25">
                Cari
            </button>
            <a href="{{ route('verifications.queue') }}" class="bg-surface-muted hover:bg-surface border border-border text-text-main text-sm font-medium py-2 px-4 rounded-md transition-colors cursor-pointer text-center focus:outline-none">
                Reset
            </a>
        </div>
    </form>
</div>

<!-- Table Content -->
<div class="bg-surface border border-border rounded-lg overflow-hidden">
    <div class="border-b border-border bg-surface-muted/50 dark:bg-transparent px-6 py-3 flex items-center justify-between">
        <span class="text-sm font-bold text-text-main uppercase tracking-wider">Daftar Antrean</span>
    </div>

    <!-- Table Container -->
    <div class="overflow-x-auto">
        <table class="w-full border-collapse text-left text-sm text-text-main">
            <thead>
                <tr class="bg-surface-muted/50 dark:bg-transparent border-b border-border text-text-muted font-semibold text-xs">
                    <th class="px-6 py-3.5 w-12 text-center">NO</th>
                    <th class="px-6 py-3.5">ID</th>
                    <th class="px-6 py-3.5">NAMA</th>
                    <th class="px-6 py-3.5">HP</th>
                    <th class="px-6 py-3.5">DESA</th>
                    <th class="px-6 py-3.5">INSERTED AT</th>
                    <th class="px-6 py-3.5 text-center">STATUS</th>
                    <th class="px-6 py-3.5">WAKTU (LIVE)</th>
                    <th class="px-6 py-3.5 text-right">ACTION</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse($customers as $customer)
                    @php
                    $installation = $customer->latestInstallation;
                    @endphp
                <tr class="hover:bg-surface-muted/45 transition-colors" id="customer-row-{{ $customer->id }}" data-pop-id="{{ $customer->pop_id }}">
                    <td class="px-6 py-3.5 text-center text-text-muted font-mono">{{ $loop->iteration }}</td>
                    <td class="px-6 py-3.5 whitespace-nowrap font-mono">{{ $customer->display_id }}</td>
                    <td class="px-6 py-3.5 font-medium text-text-main">{{ $customer->full_name }}</td>
                    <td class="px-6 py-3.5 font-mono">{{ $customer->primary_phone }}</td>
                    <td class="px-6 py-3.5 font-medium">{{ $customer->village->name ?? '-' }}</td>
                    <td class="px-6 py-3.5 font-mono text-xs">{{ $customer->created_at->format('Y-m-d H:i:s') }}</td>
                    @include('verifications.partials.queue-status-cells', ['customer' => $customer, 'installation' => $installation])
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-6 py-8 text-center text-text-muted">
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
    
    @if($customers->hasPages())
        <div class="border-t border-border px-6 py-4 bg-surface-muted/50 dark:bg-transparent">
            {{ $customers->links() }}
        </div>
    @endif
</div>



{{-- Modal Final Verify telah dipindahkan ke halaman verifications/admin.blade.php --}}

<!-- Modal Reject -->
<div id="rejectModal" class="fixed inset-0 z-[100] flex items-center justify-center hidden bg-black/50 backdrop-blur-sm transition-opacity opacity-0 duration-300">
    <div class="bg-surface border border-border rounded-xl shadow-2xl w-full max-w-md overflow-hidden transform scale-95 transition-transform duration-300">
        <div class="flex justify-between items-center px-6 py-4 border-b border-border bg-error-bg/60">
            <h3 class="text-lg font-bold text-error">Batalkan / Gagal Pelanggan</h3>
            <button type="button" onclick="closeRejectModal()" class="text-text-muted hover:text-text-main transition-colors focus:outline-none rounded-md hover:bg-surface-muted p-1 cursor-pointer">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        
        <form id="rejectForm" method="POST" action="">
            @csrf
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-text-muted mb-2">ALASAN PENOLAKAN <span class="text-error">*</span></label>
                    <textarea name="reason" rows="3" class="w-full text-sm px-3 py-2 border border-border rounded-md focus:outline-none focus:border-error focus:ring-1 focus:ring-error bg-surface" required placeholder="Contoh: Lokasi tidak terjangkau jaringan (ODP Penuh)..."></textarea>
                </div>
            </div>
            
            <div class="px-6 py-4 border-t border-border bg-surface-muted dark:bg-transparent flex justify-end gap-3">
                <button type="button" onclick="closeRejectModal()" class="px-5 py-2 text-sm font-medium text-text-muted bg-surface border border-border rounded-md hover:bg-surface-muted transition-colors cursor-pointer">Tutup</button>
                <button type="submit" class="px-5 py-2 text-sm font-medium text-white bg-error rounded-md hover:bg-error/90 transition-colors shadow-sm cursor-pointer">Batalkan / Gagal</button>
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


    function openRejectModal(customerId) {
        const modal = document.getElementById('rejectModal');
        const form = document.getElementById('rejectForm');
        
        form.action = `/verifications/${customerId}/reject`;
        
        modal.classList.remove('hidden');
        setTimeout(() => {
            modal.classList.remove('opacity-0');
            modal.querySelector('div').classList.remove('scale-95');
        }, 10);
    }

    function closeRejectModal() {
        const modal = document.getElementById('rejectModal');
        modal.classList.add('opacity-0');
        modal.querySelector('div').classList.add('scale-95');
        setTimeout(() => {
            modal.classList.add('hidden');
            document.getElementById('rejectForm').reset();
        }, 300);
    }

    // Live Countdown Logic
    document.addEventListener('DOMContentLoaded', function() {
        const countdownElements = document.querySelectorAll('[id^="countdown-"]');
        
        function updateCountdowns() {
            const now = new Date();
            
            countdownElements.forEach(el => {
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
        
        if (countdownElements.length > 0) {
            updateCountdowns(); // Initial call
            setInterval(updateCountdowns, 1000); // Update every second
        }
    });

    // Realtime tanpa reload: begitu App\Events\CustomerVerificationStatusChanged
    // masuk buat pelanggan yang lagi tampil di baris ini, refetch 3 sel
    // (STATUS/WAKTU/ACTION) lewat verifications.row — nyegah 2 admin
    // verifikasi pelanggan yang sama tanpa saling tahu (docs/plan/analisa-
    // realtime-spa-operasional.md §2.1 no. 10). Baris yang udah keluar
    // cakupan antrean (endpoint balikin 204) langsung dihapus dari layar.
    function refreshVerificationRow(customerId) {
        const row = document.getElementById('customer-row-' + customerId);
        if (!row) {
            return;
        }

        fetch('/verifications/' + customerId + '/row', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        }).then(function (res) {
            if (res.status === 204) {
                row.remove();
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
                const current = row.querySelector('#customer-' + part + '-cell-' + customerId);
                if (fresh && current) {
                    current.replaceWith(fresh);
                }
            });
        }).catch(function () {
            // Diam-diam gagal — baris tetap nampilin data lama, gak ganggu kerjaan admin.
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof window.Echo === 'undefined' || !window.Echo) {
            return;
        }

        const popIds = [...new Set(
            Array.from(document.querySelectorAll('tr[data-pop-id]')).map(function (row) {
                return row.getAttribute('data-pop-id');
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
