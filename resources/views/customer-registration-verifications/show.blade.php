@extends('layouts.app')

@section('title', 'Verifikasi Registrasi - '.$customer->full_name)
@section('page_title', 'Tinjau Registrasi: '.$customer->full_name)

@section('content')
{{-- Flash messages ditangani otomatis oleh global Component Toast (<x-toast />) --}}

<a href="{{ route('customer-registration-verifications.index') }}" class="inline-flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 mb-4">
    <x-ui.icon name="arrow-left" class="w-3.5 h-3.5" /> Kembali ke Antrean Verifikasi Registrasi
</a>

<div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-6 mb-6 space-y-6">
    <div class="flex items-start justify-between gap-4 border-b border-slate-100 dark:border-slate-700/60 pb-4">
        <div>
            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ $customer->full_name }}</h3>
            <p class="text-xs text-slate-400 dark:text-slate-500 font-mono mt-0.5">{{ $customer->customer_code }}</p>
        </div>
        <a href="{{ route('customers.show', $customer) }}" class="text-[11px] text-sky-600 dark:text-sky-400 hover:underline shrink-0">Lihat Detail Pelanggan Lengkap →</a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 text-xs">
        <div>
            <span class="block text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-1">NIK</span>
            <span class="text-slate-700 dark:text-slate-300 font-mono">{{ $customer->identity_number }}</span>
        </div>
        <div>
            <span class="block text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-1">Nomor HP</span>
            <span class="text-slate-700 dark:text-slate-300">{{ $customer->primary_phone }}</span>
        </div>
        <div>
            <span class="block text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-1">POP/Cabang</span>
            <span class="text-slate-700 dark:text-slate-300">{{ $customer->pop?->name ?? '—' }}</span>
        </div>
        <div>
            <span class="block text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-1">Diregistrasi</span>
            <span class="text-slate-700 dark:text-slate-300">{{ \App\Support\IndonesianDate::dateTime($customer->created_at) }}</span>
        </div>
        <div class="md:col-span-2">
            <span class="block text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-1">Alamat Instalasi</span>
            <span class="text-slate-700 dark:text-slate-300">{{ $customer->customerAddress?->full_address ?? $customer->address ?? '—' }}</span>
        </div>
        <div>
            <span class="block text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-1">Paket Internet</span>
            <span class="text-slate-700 dark:text-slate-300">{{ $customer->customerService?->internetPackage?->name ?? $customer->customerService?->package_name_snapshot ?? '—' }}</span>
        </div>
        <div>
            <span class="block text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-1">Biaya Langganan</span>
            <span class="text-slate-700 dark:text-slate-300 font-mono">{{ $customer->customerService ? \App\Helpers\FormatHelper::rupiah($customer->customerService->total_monthly_bill) : '—' }}</span>
        </div>
    </div>

    @if($customer->documents->isNotEmpty())
    <div class="border-t border-slate-100 dark:border-slate-700/60 pt-4">
        <span class="block text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider mb-2">Dokumen Terlampir</span>
        <div class="flex flex-wrap gap-2">
            @foreach($customer->documents as $document)
                <a href="{{ route('customers.documents.show', $document) }}" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[11px] font-semibold rounded-lg border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-900/40 transition-colors">
                    <x-ui.icon name="file-text" class="w-3.5 h-3.5" /> {{ $document->typeLabel() }}
                </a>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Aksi lanjutan di halaman Detail miliknya sendiri → inline toggle Alpine,
         bukan modal/halaman baru (pola #3 CLAUDE.md). Satu x-data buat tombol
         Setujui + toggle Tolak, biar gak kepisah scope kayak yang pernah
         kejadian di warehouse/stock-requests. --}}
    <div class="border-t border-slate-100 dark:border-slate-700/60 pt-4" x-data="{ rejectOpen: false }">
        <div class="flex flex-wrap items-center gap-2">
            @can('customer_registration_verification.approve')
            <form action="{{ route('customer-registration-verifications.approve', $customer) }}" method="POST" class="inline" onsubmit="return confirm('Setujui registrasi ini? Pelanggan akan masuk antrean survey dan muncul di Task FOP.')">
                @csrf
                @method('PUT')
                <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg shadow-xs transition-colors">
                    <x-ui.icon name="check" class="w-3.5 h-3.5" /> Setujui — Masuk Antrean Survey
                </button>
            </form>
            @endcan

            @can('customer_registration_verification.reject')
            <button type="button" @click="rejectOpen = ! rejectOpen" class="inline-flex items-center gap-1.5 px-4 py-2 bg-rose-50 hover:bg-rose-100 dark:bg-rose-950/40 dark:hover:bg-rose-900/50 text-rose-700 dark:text-rose-400 border border-rose-200 dark:border-rose-800 text-xs font-bold rounded-lg transition-colors">
                Tolak
            </button>
            @endcan
        </div>

        @can('customer_registration_verification.reject')
        <form action="{{ route('customer-registration-verifications.reject', $customer) }}" method="POST"
              x-show="rejectOpen" x-cloak
              class="mt-3 p-4 rounded-lg border border-rose-200 dark:border-rose-800 bg-rose-50/40 dark:bg-rose-950/20 space-y-2">
            @csrf
            @method('PUT')
            <label class="block text-[11px] font-semibold text-rose-700 dark:text-rose-300">Alasan Penolakan <span class="text-rose-500">*</span></label>
            <textarea name="reason" rows="2" required maxlength="1000" placeholder="Jelaskan alasan penolakan registrasi ini..."
                      class="w-full text-xs px-3 py-2 border border-rose-200 dark:border-rose-800 rounded-lg bg-white dark:bg-slate-900/60 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-rose-500/20">{{ old('reason') }}</textarea>
            @error('reason')
                <p class="text-[11px] text-rose-600 dark:text-rose-400">{{ $message }}</p>
            @enderror
            <button type="submit" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-lg">Konfirmasi Tolak</button>
        </form>
        @endcan
    </div>
</div>
@endsection
