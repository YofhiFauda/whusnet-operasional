{{-- Info Registrasi Pelanggan — dipakai bareng dua tempat:
     1. verifications/admin.blade.php (tab "Data Registrasi", tahap Survey s/d Aktif).
     2. customer-registration-verifications/show.blade.php (Verifikasi Registrasi — tahap paling awal, sebelum survey sama sekali).
--}}
<script>
    if (typeof window.openPhotoLightbox !== 'function') {
        window.openPhotoLightbox = function(src, caption) {
            window.dispatchEvent(new CustomEvent('open-image-preview', { detail: { url: src, label: caption } }));
        };
    }
</script>

<div class="space-y-6">
    <!-- Section 1: Informasi Registrasi & Kontak Pelanggan -->
    <div>
        <div class="flex items-center gap-2.5 mb-4">
            <div class="w-8 h-8 rounded-lg bg-sky-500/10 dark:bg-sky-400/10 flex items-center justify-center shrink-0 border border-sky-500/20">
                <svg class="w-4 h-4 text-sky-600 dark:text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/>
                </svg>
            </div>
            <div>
                <h4 class="text-xs font-bold text-text-main uppercase tracking-wider">Informasi Registrasi Pelanggan</h4>
                <p class="text-[11px] text-text-muted">Data pribadi dan identitas saat pendaftaran</p>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5 sm:gap-4">
            <div class="bg-surface-muted/60 dark:bg-slate-900/40 border border-border rounded-xl p-3.5 sm:p-4 hover:border-slate-300 dark:hover:border-slate-700 transition-all">
                <span class="block text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1 flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    Tanggal Registrasi
                </span>
                <span class="block text-sm font-bold text-text-main">{{ $customer->registration_date ? $customer->registration_date->format('d M Y') : '-' }}</span>
            </div>

            <div class="bg-surface-muted/60 dark:bg-slate-900/40 border border-border rounded-xl p-3.5 sm:p-4 hover:border-slate-300 dark:hover:border-slate-700 transition-all">
                <span class="block text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1 flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    Nama Lengkap
                </span>
                <span class="block text-sm font-bold text-text-main truncate" title="{{ $customer->full_name }}">{{ $customer->full_name }}</span>
            </div>

            <div class="bg-surface-muted/60 dark:bg-slate-900/40 border border-border rounded-xl p-3.5 sm:p-4 hover:border-slate-300 dark:hover:border-slate-700 transition-all">
                <span class="block text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1 flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/></svg>
                    Nomor Identitas (KTP/SIM)
                </span>
                <span class="block text-sm font-mono font-semibold text-text-main">{{ $customer->identity_number ?? '-' }}</span>
            </div>

            <div class="bg-surface-muted/60 dark:bg-slate-900/40 border border-border rounded-xl p-3.5 sm:p-4 hover:border-slate-300 dark:hover:border-slate-700 transition-all">
                <span class="block text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1 flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    Tipe Pelanggan
                </span>
                <div class="mt-0.5">
                    <x-ui.badge variant="{{ strtolower($customer->customer_type) === 'bisnis' ? 'warning' : 'neutral' }}">
                        {{ ucfirst($customer->customer_type ?? 'Individu') }}
                    </x-ui.badge>
                </div>
            </div>

            <div class="bg-surface-muted/60 dark:bg-slate-900/40 border border-border rounded-xl p-3.5 sm:p-4 hover:border-slate-300 dark:hover:border-slate-700 transition-all">
                <span class="block text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1 flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    Telepon Utama
                </span>
                @if($customer->primary_phone)
                    <a href="tel:{{ $customer->primary_phone }}" class="inline-flex items-center gap-1 text-sm font-mono font-semibold text-primary hover:underline">
                        {{ $customer->primary_phone }}
                    </a>
                @else
                    <span class="block text-sm text-text-muted">-</span>
                @endif
            </div>

            <div class="bg-surface-muted/60 dark:bg-slate-900/40 border border-border rounded-xl p-3.5 sm:p-4 hover:border-slate-300 dark:hover:border-slate-700 transition-all">
                <span class="block text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1 flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    Email
                </span>
                @if($customer->email)
                    <a href="mailto:{{ $customer->email }}" class="block text-sm text-primary hover:underline truncate" title="{{ $customer->email }}">
                        {{ $customer->email }}
                    </a>
                @else
                    <span class="block text-sm text-text-muted">-</span>
                @endif
            </div>

            <div class="sm:col-span-2 lg:col-span-3 bg-surface-muted/60 dark:bg-slate-900/40 border border-border rounded-xl p-3.5 sm:p-4 hover:border-slate-300 dark:hover:border-slate-700 transition-all">
                <span class="block text-[10px] font-bold uppercase tracking-wider text-text-muted mb-1 flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Alamat Pemasangan
                </span>
                <p class="text-sm text-text-main font-medium leading-relaxed">
                    {{ $customer->address }}
                    @if($customer->village)
                        <span class="block mt-1 text-xs text-text-secondary">
                            Kel/Desa {{ $customer->village->name }}, Kec. {{ $customer->village->district->name ?? '-' }}, {{ $customer->city->name ?? '-' }}
                        </span>
                    @endif
                </p>
                @if($customer->latitude && $customer->longitude)
                    <div class="mt-3 pt-3 border-t border-border flex items-center justify-between flex-wrap gap-2">
                        <span class="text-xs font-mono text-text-muted flex items-center gap-1">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            Titik Koordinat: {{ $customer->latitude }}, {{ $customer->longitude }}
                        </span>
                        <a href="https://maps.google.com/?q={{ $customer->latitude }},{{ $customer->longitude }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-lg bg-sky-50 dark:bg-sky-950/40 text-sky-600 dark:text-sky-400 border border-sky-200 dark:border-sky-900 hover:bg-sky-100 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                            Buka di Google Maps
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Section 2: Layanan Terpilih -->
    <div>
        <div class="flex items-center gap-2.5 mb-4">
            <div class="w-8 h-8 rounded-lg bg-blue-500/10 dark:bg-blue-400/10 flex items-center justify-center shrink-0 border border-blue-500/20">
                <svg class="w-4 h-4 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                </svg>
            </div>
            <div>
                <h4 class="text-xs font-bold text-text-main uppercase tracking-wider">Layanan Terpilih</h4>
                <p class="text-[11px] text-text-muted">Paket langganan internet & estimasi biaya bulanan</p>
            </div>
        </div>

        <div class="bg-gradient-to-br from-blue-500/10 via-sky-500/5 to-transparent border border-blue-500/20 dark:border-blue-900/50 rounded-2xl p-4 sm:p-6 shadow-xs">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 lg:gap-6">
                <div>
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-blue-700 dark:text-blue-300 mb-1">Paket Internet</span>
                    <span class="block text-base font-extrabold text-blue-950 dark:text-blue-100">
                        {{ $customer->internetPackage->name ?? '-' }}
                    </span>
                    @if($customer->internetPackage?->bandwidth_label)
                        <span class="inline-block mt-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-indigo-100 dark:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300">
                            {{ $customer->internetPackage->bandwidth_label }}
                        </span>
                    @endif
                </div>

                <div>
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-indigo-700 dark:text-indigo-300 mb-1">Biaya Berlangganan (Bulanan)</span>
                    <span class="block text-lg sm:text-xl font-mono font-black text-indigo-700 dark:text-indigo-400">
                        Rp {{ number_format($customer->customerService->total_monthly_bill ?? 0, 0, ',', '.') }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Section 3: Dokumen & Foto Registrasi -->
    <div>
        <div class="flex items-center gap-2.5 mb-4">
            <div class="w-8 h-8 rounded-lg bg-emerald-500/10 dark:bg-emerald-400/10 flex items-center justify-center shrink-0 border border-emerald-500/20">
                <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </div>
            <div>
                <h4 class="text-xs font-bold text-text-main uppercase tracking-wider">Dokumen & Foto Registrasi</h4>
                <p class="text-[11px] text-text-muted">Foto lokasi/rumah & berkas administrasi pendukung</p>
            </div>
        </div>

        @php
            $fotoRumahUrl = foto_publik($customer->foto_rumah);
            $fotoKontrakUrl = foto_publik($customer->foto_kontrak);
            $fabDocument = $customer->relationLoaded('documents')
                ? $customer->documents->firstWhere('document_type', \App\Enums\DocumentType::FAB)
                : $customer->documents()->where('document_type', \App\Enums\DocumentType::FAB->value)->latest()->first();
        @endphp

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @if($fotoRumahUrl)
                <div class="group relative bg-surface-muted/60 dark:bg-slate-900/40 border border-border rounded-xl p-3.5 text-center hover:border-sky-500/40 transition-all shadow-2xs">
                    <span class="block text-xs font-bold uppercase tracking-wider text-text-muted mb-2.5">Foto Rumah</span>
                    <div class="relative overflow-hidden rounded-lg bg-slate-950/5 dark:bg-slate-950/40 aspect-video flex items-center justify-center cursor-pointer"
                         @click="$dispatch('open-image-preview', { url: '{{ $fotoRumahUrl }}', label: 'Foto Rumah — {{ $customer->full_name }}' })">
                        <img src="{{ $fotoRumahUrl }}" alt="Foto Rumah" class="w-full h-full object-cover rounded-lg group-hover:scale-105 transition-transform duration-300">
                        <div class="absolute inset-0 bg-slate-950/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-1.5 text-white text-xs font-semibold">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"/></svg>
                            Klik untuk memperbesar
                        </div>
                    </div>
                </div>
            @endif

            @if($fotoKontrakUrl)
                <div class="group relative bg-surface-muted/60 dark:bg-slate-900/40 border border-border rounded-xl p-3.5 text-center hover:border-sky-500/40 transition-all shadow-2xs">
                    <span class="block text-xs font-bold uppercase tracking-wider text-text-muted mb-2.5">Foto Kontrak</span>
                    <div class="relative overflow-hidden rounded-lg bg-slate-950/5 dark:bg-slate-950/40 aspect-video flex items-center justify-center cursor-pointer"
                         @click="$dispatch('open-image-preview', { url: '{{ $fotoKontrakUrl }}', label: 'Foto Kontrak — {{ $customer->full_name }}' })">
                        <img src="{{ $fotoKontrakUrl }}" alt="Foto Kontrak" class="w-full h-full object-cover rounded-lg group-hover:scale-105 transition-transform duration-300">
                        <div class="absolute inset-0 bg-slate-950/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-1.5 text-white text-xs font-semibold">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7"/></svg>
                            Klik untuk memperbesar
                        </div>
                    </div>
                </div>
            @endif

            @if($fabDocument)
                <div class="bg-surface-muted/60 dark:bg-slate-900/40 border border-border rounded-xl p-4 flex flex-col justify-between text-center hover:border-sky-500/40 transition-all shadow-2xs">
                    <div>
                        <span class="block text-xs font-bold uppercase tracking-wider text-text-muted mb-2">FAB (Formulir Berlangganan Bisnis)</span>
                        <p class="text-[11px] text-text-muted mb-4">Dokumen fisik pendaftaran kategori bisnis yang diunggah.</p>
                    </div>
                    <a href="{{ route('customers.documents.show', $fabDocument) }}" target="_blank" class="inline-flex items-center justify-center gap-2 text-xs font-bold text-primary hover:text-primary-hover px-3 py-2 rounded-lg bg-primary-soft/50 border border-primary-border hover:bg-primary-soft transition-colors">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Lihat Berkas FAB →
                    </a>
                </div>
            @endif

            @if(!$fotoRumahUrl && !$fotoKontrakUrl && !$fabDocument)
                <div class="col-span-full">
                    <x-ui.alert variant="warning" title="Tidak Ada Dokumen Diunggah">
                        Pelanggan ini belum mengunggah foto rumah, foto kontrak, ataupun berkas FAB saat registrasi.
                    </x-ui.alert>
                </div>
            @endif
        </div>
    </div>
</div>
