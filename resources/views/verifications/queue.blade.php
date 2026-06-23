@extends('layouts.app')

@section('title', 'Proses Verifikasi & Pemasangan - Whusnet Operasional')
@section('page_title', 'Antrean Verifikasi & Pemasangan')

@section('content')
<!-- Top Action Bar -->
<div class="flex justify-between items-center mb-6">
    <h3 class="text-slate-800 text-sm font-semibold uppercase tracking-wider">Antrean Verifikasi Lapangan</h3>
</div>

<!-- Filter & Search Panel -->
<div class="bg-white border border-slate-200 rounded-lg p-6 mb-6">
    <form action="{{ route('verifications.queue') }}" method="GET" class="flex flex-col sm:flex-row gap-4 items-end">
        <!-- Search -->
        <div class="flex-1">
            <label for="search" class="block text-xs font-semibold text-slate-500 mb-2">CARI PELANGGAN</label>
            <input type="text" name="search" id="search" value="{{ request('search') }}" placeholder="Cari nama, No. HP, atau ID Lama..." class="w-full font-sans text-sm px-3 py-2 border border-slate-200 rounded-md bg-white text-slate-900 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/25">
        </div>

        <!-- Action Buttons -->
        <div class="flex gap-2">
            <button type="submit" class="bg-sky-600 hover:bg-sky-700 text-white text-sm font-medium py-2 px-6 rounded-md transition-colors cursor-pointer focus:outline-none focus:ring-2 focus:ring-sky-500/25">
                Cari
            </button>
            <a href="{{ route('verifications.queue') }}" class="bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium py-2 px-4 rounded-md transition-colors cursor-pointer text-center focus:outline-none">
                Reset
            </a>
        </div>
    </form>
</div>

<!-- Table Content -->
<div class="bg-white border border-slate-200 rounded-lg overflow-hidden">
    <div class="border-b border-slate-200 bg-sky-50 px-6 py-3 flex items-center justify-between">
        <span class="text-sm font-bold text-sky-800 uppercase tracking-wider">Daftar Antrean</span>
    </div>

    <!-- Table Container -->
    <div class="overflow-x-auto">
        <table class="w-full border-collapse text-left text-sm text-slate-700">
            <thead>
                <tr class="bg-slate-50/50 border-b border-slate-200 text-slate-500 font-semibold text-xs">
                    <th class="px-6 py-3.5 w-12 text-center">NO</th>
                    <th class="px-6 py-3.5">ID</th>
                    <th class="px-6 py-3.5">NAMA</th>
                    <th class="px-6 py-3.5">HP</th>
                    <th class="px-6 py-3.5">DESA</th>
                    <th class="px-6 py-3.5 text-center">STATUS</th>
                    <th class="px-6 py-3.5">INSERTED AT</th>
                    <th class="px-6 py-3.5">WAKTU (LIVE)</th>
                    <th class="px-6 py-3.5 text-right">ACTION</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($customers as $customer)
                    @php
                    $installation = $customer->latestInstallation;
                    @endphp
                <tr class="hover:bg-slate-50/45 transition-colors">
                    <td class="px-6 py-3.5 text-center text-slate-400 font-mono">{{ $loop->iteration }}</td>
                    <td class="px-6 py-3.5 whitespace-nowrap font-mono">{{ $customer->display_id }}</td>
                    <td class="px-6 py-3.5 font-medium text-slate-900">{{ $customer->full_name }}</td>
                    <td class="px-6 py-3.5 font-mono">{{ $customer->primary_phone }}</td>
                    <td class="px-6 py-3.5 font-medium">{{ $customer->village->name ?? '-' }}</td>
                    <td class="px-6 py-3.5 text-center">
                        @if($customer->status === 'waiting_acc' || $customer->status === 'surveyed')
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-bold tracking-wide uppercase bg-amber-50 text-amber-700 border border-amber-200">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> MENUNGGU ACC
                            </span>
                        @elseif($customer->status === 'waiting_installation')
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-bold tracking-wide uppercase bg-slate-100 text-slate-700 border border-slate-200">
                                MENUNGGU PEMASANGAN
                            </span>
                        @elseif($customer->status === 'installation_in_progress')
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-bold tracking-wide uppercase bg-sky-50 text-sky-700 border border-sky-200">
                                <span class="w-1.5 h-1.5 rounded-full bg-sky-500 animate-pulse"></span> MULAI PASANG
                            </span>
                        @elseif($customer->status === 'revision_installation')
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-bold tracking-wide uppercase bg-red-50 text-red-700 border border-red-200">
                                REVISI PEMASANGAN
                            </span>
                        @elseif($customer->status === 'installed' || $customer->status === 'verification_admin')
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-bold tracking-wide uppercase bg-emerald-50 text-emerald-700 border border-emerald-200">
                                VERIFIKASI ADMIN
                            </span>
                        @endif
                    </td>
                    <td class="px-6 py-3.5 font-mono text-xs">{{ $customer->created_at->format('Y-m-d H:i:s') }}</td>
                    <td class="px-6 py-3.5 font-mono text-xs">
                        @if(($customer->status === 'installation_in_progress' || $customer->status === 'revision_installation') && $installation && $installation->started_at)
                            <div class="text-sky-600 font-bold" id="countdown-{{ $customer->id }}" data-start="{{ $installation->started_at->toIso8601String() }}">
                                Menghitung...
                            </div>
                        @elseif($customer->status === 'waiting_installation' || $customer->status === 'waiting_acc' || $customer->status === 'surveyed')
                            <span class="text-slate-400">Belum Mulai</span>
                        @else
                            <span class="text-emerald-600 font-bold">Selesai</span>
                        @endif
                    </td>
                    <td class="px-6 py-3.5 text-right whitespace-nowrap">
                        <div class="flex items-center justify-end gap-2">
                            <button type="button" class="text-slate-400 hover:text-sky-600 transition-colors p-1" title="Generate/Lihat QR" onclick="window.Toast.info('Mockup', 'Generate/Lihat QR')">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm14 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                                </svg>
                            </button>
                            <a href="{{ route('customers.show', $customer) }}" class="text-slate-400 hover:text-sky-600 transition-colors p-1" title="Detail">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                            </a>
                            <form action="{{ route('customers.destroy', $customer) }}" method="POST" class="inline-block m-0 p-0" onsubmit="event.preventDefault(); window.confirmDelete('Apakah Anda yakin ingin menghapus pelanggan ini?', this);">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-slate-400 hover:text-red-600 transition-colors p-1" title="Delete">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                </button>
                            </form>
                            
                            @if($customer->status === 'waiting_acc' || $customer->status === 'surveyed')
                                <form action="{{ route('customers.verification.process-to-team', $customer->id) }}" method="POST" class="inline-block m-0 p-0" onsubmit="event.preventDefault(); window.confirmAction('Proses pelanggan ini ke Tim Pemasangan?', this);">
                                    @csrf
                                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white text-[11px] font-bold uppercase tracking-wider py-1.5 px-3 rounded shadow-sm transition-colors cursor-pointer">
                                        Proses ke Tim
                                    </button>
                                </form>
                                <button type="button" onclick="openRejectModal('{{ $customer->id }}')" class="bg-red-100 hover:bg-red-200 text-red-700 text-[11px] font-bold uppercase tracking-wider py-1.5 px-3 rounded shadow-sm transition-colors cursor-pointer">
                                    Batalkan / Gagal
                                </button>
                            @elseif($customer->status === 'waiting_installation')
                                <form action="{{ route('customers.installation.start', $customer) }}" method="POST" class="inline-block m-0 p-0" onsubmit="event.preventDefault(); window.confirmAction('Mulai proses pemasangan untuk pelanggan ini?', this);">
                                    @csrf
                                    <button type="submit" class="bg-sky-600 hover:bg-sky-700 text-white text-[11px] font-bold uppercase tracking-wider py-1.5 px-3 rounded shadow-sm transition-colors cursor-pointer">
                                        Start Proses
                                    </button>
                                </form>
                            @elseif($customer->status === 'installation_in_progress')
                                <a href="{{ route('customers.installation.report', $customer) }}" class="bg-emerald-600 hover:bg-emerald-700 text-white text-[11px] font-bold uppercase tracking-wider py-1.5 px-3 rounded shadow-sm transition-colors cursor-pointer text-center">
                                    Lapor Pemasangan
                                </a>
                            @elseif($customer->status === 'revision_installation')
                                <a href="{{ route('customers.installation.report', $customer) }}" class="bg-red-600 hover:bg-red-700 text-white text-[11px] font-bold uppercase tracking-wider py-1.5 px-3 rounded shadow-sm transition-colors cursor-pointer text-center">
                                    Revisi
                                </a>
                            @elseif($customer->status === 'installed' || $customer->status === 'verification_admin')
                                <a href="{{ route('customers.verification.admin', $customer) }}" class="bg-emerald-700 hover:bg-emerald-800 text-white text-[11px] font-bold uppercase tracking-wider py-1.5 px-3 rounded shadow-sm transition-colors cursor-pointer text-center inline-flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    Verifikasi
                                </a>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-6 py-8 text-center text-slate-500">
                        <div class="flex flex-col items-center justify-center gap-2">
                            <svg class="w-8 h-8 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" /></svg>
                            <span class="text-sm font-medium">Tidak ada antrean saat ini.</span>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
    @if($customers->hasPages())
        <div class="border-t border-slate-200 px-6 py-4 bg-slate-50/50">
            {{ $customers->links() }}
        </div>
    @endif
</div>



{{-- Modal Final Verify telah dipindahkan ke halaman verifications/admin.blade.php --}}

<!-- Modal Reject -->
<div id="rejectModal" class="fixed inset-0 z-[100] flex items-center justify-center hidden bg-slate-900/50 backdrop-blur-sm transition-opacity opacity-0 duration-300">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden transform scale-95 transition-transform duration-300">
        <div class="flex justify-between items-center px-6 py-4 border-b border-slate-100 bg-red-50">
            <h3 class="text-lg font-bold text-red-800">Batalkan / Gagal Pelanggan</h3>
            <button type="button" onclick="closeRejectModal()" class="text-slate-400 hover:text-slate-600 transition-colors focus:outline-none rounded-md hover:bg-slate-100 p-1 cursor-pointer">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        
        <form id="rejectForm" method="POST" action="">
            @csrf
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-2">ALASAN PENOLAKAN <span class="text-red-500">*</span></label>
                    <textarea name="reason" rows="3" class="w-full text-sm px-3 py-2 border border-slate-200 rounded-md focus:outline-none focus:border-red-500 focus:ring-1 focus:ring-red-500 bg-white" required placeholder="Contoh: Lokasi tidak terjangkau jaringan (ODP Penuh)..."></textarea>
                </div>
            </div>
            
            <div class="px-6 py-4 border-t border-slate-100 bg-slate-50 flex justify-end gap-3">
                <button type="button" onclick="closeRejectModal()" class="px-5 py-2 text-sm font-medium text-slate-600 bg-white border border-slate-200 rounded-md hover:bg-slate-50 transition-colors cursor-pointer">Tutup</button>
                <button type="submit" class="px-5 py-2 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700 transition-colors shadow-sm cursor-pointer">Batalkan / Gagal</button>
            </div>
        </form>
    </div>
</div>

<script>


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
</script>
@endsection
