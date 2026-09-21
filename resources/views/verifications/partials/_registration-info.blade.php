{{-- Info Registrasi Pelanggan — dipakai bareng dua tempat:
     1. verifications/admin.blade.php (tab "Data Registrasi", tahap Survey s/d
        Aktif).
     2. customer-registration-verifications/show.blade.php (ADHOC-73,
        Verifikasi Registrasi — tahap paling awal, sebelum survey sama sekali).
     Diekstrak dari verifications/admin.blade.php 2026-09-16 (permintaan user:
     dua halaman ini harus tampilan sama) — JANGAN duplikasi markup-nya lagi,
     ubah di sini, dua-duanya ikut kebawa. --}}
<h4 class="text-xs font-bold text-text-muted uppercase tracking-wider mb-4 flex items-center gap-2">
    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/></svg>
    Informasi Registrasi Pelanggan
</h4>
<div class="bg-surface-muted dark:bg-transparent border border-border rounded-xl p-5 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div>
            <span class="block text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">Tanggal Registrasi</span>
            <span class="block text-sm font-bold text-text-main">{{ $customer->registration_date ? $customer->registration_date->format('d M Y') : '-' }}</span>
        </div>
        <div>
            <span class="block text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">Nama Lengkap</span>
            <span class="block text-sm font-bold text-text-main">{{ $customer->full_name }}</span>
        </div>
        <div>
            <span class="block text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">Nomor Identitas (KTP/SIM)</span>
            <span class="block text-sm font-mono text-text-main">{{ $customer->identity_number ?? '-' }}</span>
        </div>
        <div>
            <span class="block text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">Tipe Pelanggan</span>
            <span class="block text-sm text-text-main">{{ ucfirst($customer->customer_type ?? '-') }}</span>
        </div>
        <div>
            <span class="block text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">Telepon Utama</span>
            <span class="block text-sm font-mono text-text-main">{{ $customer->primary_phone ?? '-' }}</span>
        </div>
        <div>
            <span class="block text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">Email</span>
            <span class="block text-sm text-text-main">{{ $customer->email ?? '-' }}</span>
        </div>
        <div class="md:col-span-3">
            <span class="block text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">Alamat Pemasangan</span>
            <span class="block text-sm text-text-main">
                {{ $customer->address }}
                @if($customer->village)
                    <br><span class="text-xs text-text-secondary">Kel/Desa. {{ $customer->village->name }}, Kec. {{ $customer->village->district->name ?? '-' }}, {{ $customer->city->name ?? '-' }}</span>
                @endif
            </span>
        </div>
        @if($customer->latitude && $customer->longitude)
        <div class="md:col-span-3">
            <span class="block text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1">Koordinat (Latitude, Longitude)</span>
            <a href="https://maps.google.com/?q={{ $customer->latitude }},{{ $customer->longitude }}" target="_blank" class="text-sm font-mono text-primary hover:underline flex items-center gap-1">
                 <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                {{ $customer->latitude }}, {{ $customer->longitude }}
            </a>
        </div>
        @endif
    </div>
</div>

<h4 class="text-xs font-bold text-text-muted uppercase tracking-wider mb-4 flex items-center gap-2">
    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
    Layanan Terpilih
</h4>
<div class="bg-primary-soft border border-primary-border rounded-xl p-5 mb-3">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <span class="block text-[10px] font-bold uppercase tracking-wider text-primary mb-1">Paket Internet</span>
            <span class="block text-sm font-bold text-primary">{{ $customer->internetPackage->name ?? '-' }}</span>
        </div>
        <div>
            <span class="block text-[10px] font-bold uppercase tracking-wider text-primary mb-1">Biaya Berlangganan</span>
            <span class="block text-sm font-mono font-bold text-primary">Rp {{ number_format($customer->customerService->total_monthly_bill ?? 0, 0, ',', '.') }}</span>
        </div>
    </div>
</div>

{{-- Dokumen Foto Pelanggan --}}
<div class="mb-6">
    <h4 class="text-xs font-bold text-text-muted uppercase tracking-wider mb-4 flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        Dokumen & Foto
    </h4>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        @php
            $fotoRumahUrl = foto_publik($customer->foto_rumah);
            $fotoKontrakUrl = foto_publik($customer->foto_kontrak);
            // FAB (Formulir Akan Berlangganan Bisnis, ADHOC-72) — bukan kolom
            // customers.foto_*, disimpan lewat CustomerDocument. Cuma relevan
            // buat paket kategori Bisnis, jadi wajar kalau kosong di sebagian
            // besar pelanggan.
            $fabDocument = $customer->relationLoaded('documents')
                ? $customer->documents->firstWhere('document_type', \App\Enums\DocumentType::FAB)
                : $customer->documents()->where('document_type', \App\Enums\DocumentType::FAB->value)->latest()->first();
        @endphp
        @if($fotoRumahUrl)
        <div class="bg-surface-muted dark:bg-transparent border border-border rounded-xl p-4 text-center">
            <span class="block text-xs font-bold uppercase tracking-wider text-text-muted mb-3">Foto Rumah</span>
            <img src="{{ $fotoRumahUrl }}" alt="Rumah" class="h-32 object-contain mx-auto rounded-lg shadow-sm cursor-pointer hover:opacity-90" onclick="openPhotoLightbox('{{ $fotoRumahUrl }}', 'Foto Rumah')">
        </div>
        @endif
        @if($fotoKontrakUrl)
        <div class="bg-surface-muted dark:bg-transparent border border-border rounded-xl p-4 text-center">
            <span class="block text-xs font-bold uppercase tracking-wider text-text-muted mb-3">Foto Kontrak</span>
            <img src="{{ $fotoKontrakUrl }}" alt="Kontrak" class="h-32 object-contain mx-auto rounded-lg shadow-sm cursor-pointer hover:opacity-90" onclick="openPhotoLightbox('{{ $fotoKontrakUrl }}', 'Foto Kontrak')">
        </div>
        @endif
        @if($fabDocument)
        <div class="bg-surface-muted dark:bg-transparent border border-border rounded-xl p-4 text-center">
            <span class="block text-xs font-bold uppercase tracking-wider text-text-muted mb-3">FAB (Formulir Akan Berlangganan Bisnis)</span>
            <a href="{{ route('customers.documents.show', $fabDocument) }}" target="_blank" class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary hover:underline">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Lihat Berkas FAB
            </a>
        </div>
        @endif
        @if(!$fotoRumahUrl && !$fotoKontrakUrl && !$fabDocument)
        <div class="col-span-2 bg-warning-bg border border-warning-border rounded-xl p-4 flex items-center gap-3">
            <svg class="w-5 h-5 text-warning shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <p class="text-sm text-warning">Tidak ada dokumen atau foto yang diunggah saat registrasi, atau berkas tidak tersedia di penyimpanan.</p>
        </div>
        @endif
    </div>
</div>
