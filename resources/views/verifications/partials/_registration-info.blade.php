{{-- Info Registrasi Pelanggan — dipakai bareng dua tempat:
     1. verifications/admin.blade.php (tab "Data Registrasi", tahap Survey s/d Aktif).
     2. customer-registration-verifications/show.blade.php (Verifikasi Registrasi — tahap paling awal, sebelum survey sama sekali).

     Edit cepat Data Diri + Paket Internet (bug 2026-09-30) — CS dulu harus
     keluar ke Edit Pelanggan biasa buat benerin typo nama/HP/paket, padahal
     datanya sudah tampil di layar yang sama. `$canEditVerificationData` +
     `$identityUpdateRoute`/`$packageUpdateRoute` WAJIB dikirim parent view
     (beda controller/status guard per konteks — lihat
     CustomerRegistrationVerificationController::updateIdentity() vs
     CustomerVerificationController::updateIdentity()). `$verifCities`/
     `$verifPackages` juga dikirim parent, cuma dipakai saat form edit dibuka.
--}}
<script>
    if (typeof window.openPhotoLightbox !== 'function') {
        window.openPhotoLightbox = function(src, caption) {
            window.dispatchEvent(new CustomEvent('open-image-preview', { detail: { url: src, label: caption } }));
        };
    }

    // Cascading Kota→Kecamatan→Desa — endpoint sama dengan customers/edit.blade.php
    // (/api/cities/{city}/districts, /api/districts/{district}/villages). Nama
    // fungsi di-namespace verifEdit* biar gak tabrakan kalau suatu saat halaman ini
    // dan Edit Pelanggan pernah dirender di konteks yang sama.
    if (typeof window.verifEditLoadDistricts !== 'function') {
        window.verifEditLoadDistricts = async function(cityId, selectedDistrictId = null, selectedVillageId = null) {
            const districtSelect = document.getElementById('verif-edit-district_id');
            const villageSelect = document.getElementById('verif-edit-village_id');
            if (!districtSelect || !villageSelect) return;

            districtSelect.innerHTML = '<option value="">Memuat...</option>';
            villageSelect.innerHTML = '<option value="">Pilih kecamatan dulu</option>';
            if (!cityId) {
                districtSelect.innerHTML = '<option value="">Pilih kota/kabupaten dulu</option>';
                return;
            }

            try {
                const res = await fetch(`/api/cities/${cityId}/districts`);
                const districts = await res.json();
                districtSelect.innerHTML = '<option value="">Pilih Kecamatan</option>' +
                    districts.map(d => `<option value="${d.id}" ${String(d.id) === String(selectedDistrictId) ? 'selected' : ''}>${d.name}</option>`).join('');
                if (selectedDistrictId) {
                    window.verifEditLoadVillages(selectedDistrictId, selectedVillageId);
                }
            } catch (err) {
                districtSelect.innerHTML = '<option value="">Gagal memuat kecamatan</option>';
            }
        };
    }

    if (typeof window.verifEditLoadVillages !== 'function') {
        window.verifEditLoadVillages = async function(districtId, selectedVillageId = null) {
            const villageSelect = document.getElementById('verif-edit-village_id');
            if (!villageSelect) return;

            villageSelect.innerHTML = '<option value="">Memuat...</option>';
            if (!districtId) {
                villageSelect.innerHTML = '<option value="">Pilih kecamatan dulu</option>';
                return;
            }

            try {
                const res = await fetch(`/api/districts/${districtId}/villages`);
                const villages = await res.json();
                villageSelect.innerHTML = '<option value="">Pilih Desa/Kelurahan</option>' +
                    villages.map(v => `<option value="${v.id}" ${String(v.id) === String(selectedVillageId) ? 'selected' : ''}>${v.name}</option>`).join('');
            } catch (err) {
                villageSelect.innerHTML = '<option value="">Gagal memuat desa</option>';
            }
        };
    }
</script>

<div class="space-y-6" x-data="{ editingIdentity: false, editingPackage: false }">
    <!-- Section 1: Informasi Registrasi & Kontak Pelanggan -->
    <div>
        <div class="flex items-center gap-2.5 mb-4 justify-between">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-sky-50 dark:bg-sky-950/60 flex items-center justify-center shrink-0 border border-sky-200/80 dark:border-sky-800/60">
                    <svg class="w-4 h-4 text-sky-600 dark:text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/>
                    </svg>
                </div>
                <div>
                    <h4 class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">Informasi Data Diri &amp; Kontak</h4>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400">Data identitas pribadi, domisili, dan titik koordinat</p>
                </div>
            </div>
            @if(($canEditVerificationData ?? false) && isset($identityUpdateRoute))
                <button type="button" @click="editingIdentity = ! editingIdentity"
                        class="shrink-0 inline-flex items-center gap-1.5 text-xs font-semibold text-sky-600 dark:text-sky-400 hover:text-sky-700 dark:hover:text-sky-300 px-3 py-1.5 rounded-lg border border-sky-200 dark:border-sky-800/60 hover:bg-sky-50 dark:hover:bg-sky-950/40 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    <span x-text="editingIdentity ? 'Batal' : 'Edit Data Diri'"></span>
                </button>
            @endif
        </div>

        @if(($canEditVerificationData ?? false) && isset($identityUpdateRoute))
            <form action="{{ route($identityUpdateRoute, $customer) }}" method="POST" x-show="editingIdentity" x-cloak x-collapse
                  class="mb-4 p-4 sm:p-5 rounded-lg border border-sky-200/80 dark:border-sky-900/60 bg-sky-50/30 dark:bg-sky-950/20 space-y-3.5">
                @csrf
                @method('PUT')
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <div>
                        <label class="block mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Nama Lengkap</label>
                        <input type="text" name="full_name" value="{{ old('full_name', $customer->full_name) }}" required maxlength="150"
                               class="w-full text-xs sm:text-sm px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                        @error('full_name')<p class="text-xs text-rose-600 font-semibold mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Nomor Identitas (NIK)</label>
                        <input type="text" name="identity_number" value="{{ old('identity_number', $customer->identity_number) }}" maxlength="16"
                               class="w-full text-xs sm:text-sm px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 font-mono">
                        @error('identity_number')<p class="text-xs text-rose-600 font-semibold mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Telepon Utama</label>
                        <input type="text" name="primary_phone" value="{{ old('primary_phone', $customer->primary_phone) }}" required maxlength="20"
                               class="w-full text-xs sm:text-sm px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 font-mono">
                        @error('primary_phone')<p class="text-xs text-rose-600 font-semibold mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Email</label>
                        <input type="email" name="email" value="{{ old('email', $customer->email) }}" maxlength="100"
                               class="w-full text-xs sm:text-sm px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                        @error('email')<p class="text-xs text-rose-600 font-semibold mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Alamat Pemasangan</label>
                        <textarea name="address" rows="2" required
                                  class="w-full text-xs sm:text-sm px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">{{ old('address', $customer->address) }}</textarea>
                        @error('address')<p class="text-xs text-rose-600 font-semibold mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Kota/Kabupaten</label>
                        <select id="verif-edit-city_id" name="city_id" onchange="window.verifEditLoadDistricts(this.value)"
                                class="w-full text-xs sm:text-sm px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                            <option value="">Pilih Kota/Kabupaten</option>
                            @foreach($verifCities ?? [] as $city)
                                <option value="{{ $city->id }}" {{ old('city_id', $customer->city_id) == $city->id ? 'selected' : '' }}>{{ $city->name }}</option>
                            @endforeach
                        </select>
                        @error('city_id')<p class="text-xs text-rose-600 font-semibold mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Kecamatan</label>
                        <select id="verif-edit-district_id" name="district_id"
                                class="w-full text-xs sm:text-sm px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                            <option value="">Pilih kota/kabupaten dulu</option>
                        </select>
                        @error('district_id')<p class="text-xs text-rose-600 font-semibold mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Desa/Kelurahan</label>
                        <select id="verif-edit-village_id" name="village_id"
                                class="w-full text-xs sm:text-sm px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                            <option value="">Pilih kecamatan dulu</option>
                        </select>
                        @error('village_id')<p class="text-xs text-rose-600 font-semibold mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Titik Koordinat (Lat, Long)</label>
                        <div class="flex gap-2">
                            <input type="text" name="latitude" value="{{ old('latitude', $customer->latitude) }}" placeholder="Latitude"
                                   class="w-1/2 text-xs sm:text-sm px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 font-mono">
                            <input type="text" name="longitude" value="{{ old('longitude', $customer->longitude) }}" placeholder="Longitude"
                                   class="w-1/2 text-xs sm:text-sm px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 font-mono">
                        </div>
                        @error('latitude')<p class="text-xs text-rose-600 font-semibold mt-1">{{ $message }}</p>@enderror
                        @error('longitude')<p class="text-xs text-rose-600 font-semibold mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" @click="editingIdentity = false" class="px-3.5 py-2 text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 transition-colors">Batal</button>
                    <button type="submit" class="px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white text-xs font-bold uppercase tracking-wider rounded-lg shadow-2xs transition-colors">Simpan Data Diri</button>
                </div>
                <script>
                    // Prefill kecamatan/desa saat form dibuka pertama kali
                    document.addEventListener('alpine:init', () => {
                        const cityId = document.getElementById('verif-edit-city_id')?.value;
                        if (cityId) {
                            window.verifEditLoadDistricts(cityId, '{{ old('district_id', $customer->district_id) }}', '{{ old('village_id', $customer->village_id) }}');
                        }
                    });
                </script>
            </form>
        @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5 sm:gap-4">
            <div class="bg-slate-50/75 dark:bg-slate-900/40 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-3.5 sm:p-4 hover:border-slate-300 dark:hover:border-slate-600 transition-all">
                <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1 flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-slate-400 dark:text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    Tanggal Registrasi
                </span>
                <span class="block text-sm font-mono font-bold text-slate-800 dark:text-slate-200">{{ $customer->registration_date ? $customer->registration_date->format('d M Y') : '-' }}</span>
            </div>

            <div class="bg-slate-50/75 dark:bg-slate-900/40 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-3.5 sm:p-4 hover:border-slate-300 dark:hover:border-slate-600 transition-all">
                <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1 flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-slate-400 dark:text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    Nama Lengkap
                </span>
                <span class="block text-sm font-bold text-slate-900 dark:text-slate-100 truncate" title="{{ $customer->full_name }}">{{ $customer->full_name }}</span>
            </div>

            <div class="bg-slate-50/75 dark:bg-slate-900/40 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-3.5 sm:p-4 hover:border-slate-300 dark:hover:border-slate-600 transition-all">
                <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1 flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-slate-400 dark:text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/></svg>
                    Nomor Identitas (NIK)
                </span>
                <span class="block text-sm font-mono font-semibold text-slate-800 dark:text-slate-200">{{ $customer->identity_number ?? '-' }}</span>
            </div>

            <div class="bg-slate-50/75 dark:bg-slate-900/40 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-3.5 sm:p-4 hover:border-slate-300 dark:hover:border-slate-600 transition-all">
                <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1 flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-slate-400 dark:text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    Tipe Pelanggan
                </span>
                <div class="mt-0.5">
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold {{ strtolower($customer->customer_type) === 'bisnis' ? 'bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400 border border-amber-200/80 dark:border-amber-800/60' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700' }}">
                        {{ ucfirst($customer->customer_type ?? 'Individu') }}
                    </span>
                </div>
            </div>

            <div class="bg-slate-50/75 dark:bg-slate-900/40 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-3.5 sm:p-4 hover:border-slate-300 dark:hover:border-slate-600 transition-all">
                <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1 flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-slate-400 dark:text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    Telepon Utama
                </span>
                @if($customer->primary_phone)
                    <a href="tel:{{ $customer->primary_phone }}" class="inline-flex items-center gap-1 text-sm font-mono font-semibold text-sky-600 dark:text-sky-400 hover:underline">
                        {{ $customer->primary_phone }}
                    </a>
                @else
                    <span class="block text-sm text-slate-400 dark:text-slate-500">-</span>
                @endif
            </div>

            <div class="bg-slate-50/75 dark:bg-slate-900/40 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-3.5 sm:p-4 hover:border-slate-300 dark:hover:border-slate-600 transition-all">
                <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1 flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-slate-400 dark:text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    Email
                </span>
                @if($customer->email)
                    <a href="mailto:{{ $customer->email }}" class="block text-sm text-sky-600 dark:text-sky-400 hover:underline truncate" title="{{ $customer->email }}">
                        {{ $customer->email }}
                    </a>
                @else
                    <span class="block text-sm text-slate-400 dark:text-slate-500">-</span>
                @endif
            </div>

            <div class="sm:col-span-2 lg:col-span-3 bg-slate-50/75 dark:bg-slate-900/40 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-3.5 sm:p-4 hover:border-slate-300 dark:hover:border-slate-600 transition-all">
                <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1 flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-slate-400 dark:text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Alamat Pemasangan
                </span>
                <p class="text-sm text-slate-800 dark:text-slate-200 font-medium leading-relaxed">
                    {{ $customer->address }}
                    @if($customer->village)
                        <span class="block mt-1 text-xs text-slate-500 dark:text-slate-400">
                            Kel/Desa {{ $customer->village->name }}, Kec. {{ $customer->village->district->name ?? '-' }}, {{ $customer->city->name ?? '-' }}
                        </span>
                    @endif
                </p>
                @if($customer->latitude && $customer->longitude)
                    <div class="mt-3 pt-3 border-t border-slate-200/80 dark:border-slate-700/60 flex items-center justify-between flex-wrap gap-2">
                        <span class="text-xs font-mono text-slate-600 dark:text-slate-400 flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            Titik Koordinat: <strong class="font-mono text-slate-800 dark:text-slate-200">{{ $customer->latitude }}, {{ $customer->longitude }}</strong>
                        </span>
                        <a href="https://maps.google.com/?q={{ $customer->latitude }},{{ $customer->longitude }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-lg bg-sky-50 dark:bg-sky-950/40 text-sky-600 dark:text-sky-400 border border-sky-200 dark:border-sky-900 hover:bg-sky-100 dark:hover:bg-sky-900/60 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                            Buka di Google Maps
                        </a>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Section 2: Layanan Terpilih -->
    <div class="pt-6 border-t border-slate-200/80 dark:border-slate-700/80">
        <div class="flex items-center gap-2.5 mb-4 justify-between">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-sky-50 dark:bg-sky-950/60 flex items-center justify-center shrink-0 border border-sky-200/80 dark:border-sky-800/60">
                    <svg class="w-4 h-4 text-sky-600 dark:text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                </div>
                <div>
                    <h4 class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">Layanan Paket Terpilih</h4>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400">Paket langganan internet &amp; estimasi tagihan bulanan</p>
                </div>
            </div>
            @if(($canEditVerificationData ?? false) && isset($packageUpdateRoute))
                <button type="button" @click="editingPackage = ! editingPackage"
                        class="shrink-0 inline-flex items-center gap-1.5 text-xs font-semibold text-sky-600 dark:text-sky-400 hover:text-sky-700 dark:hover:text-sky-300 px-3 py-1.5 rounded-lg border border-sky-200 dark:border-sky-800/60 hover:bg-sky-50 dark:hover:bg-sky-950/40 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    <span x-text="editingPackage ? 'Batal' : 'Ganti Paket'"></span>
                </button>
            @endif
        </div>

        @if(($canEditVerificationData ?? false) && isset($packageUpdateRoute))
            <form action="{{ route($packageUpdateRoute, $customer) }}" method="POST" x-show="editingPackage" x-cloak x-collapse
                  class="mb-4 p-4 sm:p-5 rounded-lg border border-sky-200/80 dark:border-sky-900/60 bg-sky-50/30 dark:bg-sky-950/20 space-y-3">
                @csrf
                @method('PUT')
                <label class="block mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Pilih Paket Internet Baru</label>
                <select name="internet_package_id" required
                        class="w-full text-xs sm:text-sm px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                    @foreach($verifPackages ?? [] as $package)
                        <option value="{{ $package->id }}" {{ old('internet_package_id', $customer->internet_package_id) == $package->id ? 'selected' : '' }}>
                            {{ $package->package_code }} — {{ $package->name }} (Rp {{ number_format($package->monthly_price, 0, ',', '.') }}/bln)
                        </option>
                    @endforeach
                </select>
                @error('internet_package_id')<p class="text-xs text-rose-600 font-semibold mt-1">{{ $message }}</p>@enderror
                <p class="text-[11px] text-slate-500 dark:text-slate-400">Diskon/PPN/biaya lain yang sudah tersimpan tetap dipakai — cuma paket &amp; harga dasarnya yang diganti.</p>
                <div class="flex justify-end gap-2 pt-1">
                    <button type="button" @click="editingPackage = false" class="px-3.5 py-2 text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 transition-colors">Batal</button>
                    <button type="submit" class="px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white text-xs font-bold uppercase tracking-wider rounded-lg shadow-2xs transition-colors">Simpan Paket</button>
                </div>
            </form>
        @endif

        <div class="bg-slate-50/75 dark:bg-slate-900/40 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-4 sm:p-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Paket Langganan Terdaftar</span>
                    <span class="block text-base font-bold text-slate-900 dark:text-slate-100">
                        {{ $customer->internetPackage->name ?? '-' }}
                    </span>
                    @if($customer->internetPackage?->bandwidth_label)
                        <span class="inline-block mt-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-sky-50 dark:bg-sky-950/60 text-sky-700 dark:text-sky-300 border border-sky-200/80 dark:border-sky-800/60">
                            {{ $customer->internetPackage->bandwidth_label }}
                        </span>
                    @endif
                </div>

                <div>
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">Estimasi Tagihan Bulanan</span>
                    <span class="block text-xl font-mono font-bold text-sky-600 dark:text-sky-400">
                        Rp {{ number_format($customer->customerService->total_monthly_bill ?? ($customer->internetPackage?->monthly_price ?? 0), 0, ',', '.') }}<span class="text-xs text-slate-400 dark:text-slate-500 font-normal">/bulan</span>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Section 3: Dokumen & Foto Registrasi -->
    <div class="pt-6 border-t border-slate-200/80 dark:border-slate-700/80">
        <div class="flex items-center gap-2.5 mb-4">
            <div class="w-8 h-8 rounded-lg bg-sky-50 dark:bg-sky-950/60 flex items-center justify-center shrink-0 border border-sky-200/80 dark:border-sky-800/60">
                <svg class="w-4 h-4 text-sky-600 dark:text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
            </div>
            <div>
                <h4 class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">Dokumen &amp; Foto Registrasi</h4>
                <p class="text-[11px] text-slate-500 dark:text-slate-400">Foto lokasi/rumah &amp; berkas administrasi pendukung</p>
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
                <div class="group relative bg-slate-50/75 dark:bg-slate-900/40 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-3.5 text-center hover:border-sky-500/40 transition-all shadow-2xs">
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2.5">Foto Rumah</span>
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
                <div class="group relative bg-slate-50/75 dark:bg-slate-900/40 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-3.5 text-center hover:border-sky-500/40 transition-all shadow-2xs">
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-2.5">Foto Kontrak</span>
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
                <div class="bg-slate-50/75 dark:bg-slate-900/40 border border-slate-200/80 dark:border-slate-700/80 rounded-lg p-4 flex flex-col justify-between text-center hover:border-sky-500/40 transition-all shadow-2xs">
                    <div>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1.5">FAB (Formulir Berlangganan Bisnis)</span>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 mb-4">Dokumen fisik pendaftaran kategori bisnis yang diunggah.</p>
                    </div>
                    <a href="{{ route('customers.documents.show', $fabDocument) }}" target="_blank" class="inline-flex items-center justify-center gap-2 text-xs font-bold text-sky-600 dark:text-sky-400 hover:text-sky-700 dark:hover:text-sky-300 px-3 py-2 rounded-lg bg-sky-50 dark:bg-sky-950/60 border border-sky-200 dark:border-sky-800/60 hover:bg-sky-100 dark:hover:bg-sky-900/40 transition-colors">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Lihat Berkas FAB →
                    </a>
                </div>
            @endif

            @if(!$fotoRumahUrl && !$fotoKontrakUrl && !$fabDocument)
                <div class="col-span-full">
                    <div class="p-4 rounded-lg bg-amber-50/60 dark:bg-amber-950/30 border border-amber-200/80 dark:border-amber-900/50 text-amber-800 dark:text-amber-300 text-xs">
                        <strong class="font-semibold block mb-0.5">Tidak Ada Dokumen Diunggah</strong>
                        Pelanggan ini belum mengunggah foto rumah, foto kontrak, ataupun berkas FAB saat pendaftaran registrasi.
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
