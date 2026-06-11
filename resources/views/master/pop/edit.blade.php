@extends('layouts.app')

@section('title', 'Ubah POP - Whusnet Operasional')
@section('page_title', 'Ubah POP / Cabang')

@section('content')
<!-- Back link and Title Header -->
<div class="mb-6 flex items-center justify-between">
    <a href="{{ route('master.pop.index') }}" class="inline-flex items-center gap-2 text-xs font-bold text-slate-500 hover:text-slate-800 transition-colors">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
        </svg>
        Kembali ke Daftar POP
    </a>
</div>

<!-- Form Container -->
<form action="{{ route('master.pop.update', $pop) }}" method="POST" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    @csrf
    @method('PUT')
    
    <!-- Left Panel: Data Utama -->
    <div class="bg-white border border-slate-200 rounded-lg p-6 shadow-sm lg:col-span-2 space-y-5">
        <div class="border-b border-slate-100 pb-3">
            <h3 class="text-sm font-bold text-slate-800">Informasi Utama POP</h3>
            <p class="text-xs text-slate-400 mt-0.5">Ubah kode unik, nama, tipe, parent, dan status keaktifan POP.</p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Kode POP -->
            <div>
                <label for="code" class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">Kode POP <span class="text-rose-500">*</span></label>
                <input type="text" name="code" id="code" value="{{ old('code', $pop->code) }}" required placeholder="Contoh: POP-SMN-01"
                       class="w-full px-3 py-2 border @error('code') border-rose-500 focus:ring-rose-500 @else border-slate-300 focus:ring-sky-500 focus:border-sky-500 @enderror rounded-md text-sm text-slate-800 focus:outline-none focus:ring-1">
                @error('code')
                    <p class="text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</p>
                @enderror
            </div>

            <!-- Nama POP -->
            <div>
                <label for="name" class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">Nama POP <span class="text-rose-500">*</span></label>
                <input type="text" name="name" id="name" value="{{ old('name', $pop->name) }}" required placeholder="Contoh: POP Cabang Sleman"
                       class="w-full px-3 py-2 border @error('name') border-rose-500 focus:ring-rose-500 @else border-slate-300 focus:ring-sky-500 focus:border-sky-500 @enderror rounded-md text-sm text-slate-800 focus:outline-none focus:ring-1">
                @error('name')
                    <p class="text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 border-t border-slate-100 pt-5">
            <!-- Kode Identifier POP -->
            <div>
                <label for="pop_code" class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">Kode Identifier POP <span class="text-rose-500">*</span></label>
                <input type="text" name="pop_code" id="pop_code" value="{{ old('pop_code', $pop->pop_code) }}" required placeholder="Contoh: SMN"
                       class="w-full px-3 py-2 border @error('pop_code') border-rose-500 focus:ring-rose-500 @else border-slate-300 focus:ring-sky-500 focus:border-sky-500 @enderror rounded-md text-sm text-slate-800 focus:outline-none focus:ring-1 uppercase">
                @error('pop_code')
                    <p class="text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</p>
                @enderror
            </div>

            <!-- Prefix ID Request -->
            <div>
                <label for="registration_prefix" class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">Prefix ID Request <span class="text-rose-500">*</span></label>
                <input type="text" name="registration_prefix" id="registration_prefix" value="{{ old('registration_prefix', $pop->registration_prefix) }}" required placeholder="Contoh: C"
                       class="w-full px-3 py-2 border @error('registration_prefix') border-rose-500 focus:ring-rose-500 @else border-slate-300 focus:ring-sky-500 focus:border-sky-500 @enderror rounded-md text-sm text-slate-800 focus:outline-none focus:ring-1 uppercase">
                @error('registration_prefix')
                    <p class="text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</p>
                @enderror
            </div>

            <!-- Prefix CID -->
            <div>
                <label for="cid_prefix" class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">Prefix CID <span class="text-rose-500">*</span></label>
                <input type="text" name="cid_prefix" id="cid_prefix" value="{{ old('cid_prefix', $pop->cid_prefix) }}" required placeholder="Contoh: D"
                       class="w-full px-3 py-2 border @error('cid_prefix') border-rose-500 focus:ring-rose-500 @else border-slate-300 focus:ring-sky-500 focus:border-sky-500 @enderror rounded-md text-sm text-slate-800 focus:outline-none focus:ring-1 uppercase">
                @error('cid_prefix')
                    <p class="text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Tipe POP -->
            <div>
                <label for="type" class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">Tipe POP <span class="text-rose-500">*</span></label>
                <select name="type" id="type" required 
                        class="w-full px-3 py-2 border @error('type') border-rose-500 focus:ring-rose-500 @else border-slate-300 focus:ring-sky-500 focus:border-sky-500 @enderror rounded-md text-sm text-slate-800 focus:outline-none focus:ring-1 bg-white">
                    <option value="pusat" {{ old('type', $pop->type) === 'pusat' ? 'selected' : '' }}>Pusat</option>
                    <option value="cabang" {{ old('type', $pop->type) === 'cabang' ? 'selected' : '' }}>Cabang</option>
                    <option value="mini_pop" {{ old('type', $pop->type) === 'mini_pop' ? 'selected' : '' }}>Mini POP</option>
                </select>
                @error('type')
                    <p class="text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</p>
                @enderror
            </div>

            <!-- Parent POP -->
            <div>
                <label for="parent_id" class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">Parent POP</label>
                <select name="parent_id" id="parent_id" 
                        class="w-full px-3 py-2 border @error('parent_id') border-rose-500 focus:ring-rose-500 @else border-slate-300 focus:ring-sky-500 focus:border-sky-500 @enderror rounded-md text-sm text-slate-800 focus:outline-none focus:ring-1 bg-white">
                    <option value="">-- Tanpa Parent (POP Utama) --</option>
                    @foreach($parents as $parent)
                        <option value="{{ $parent->id }}" {{ old('parent_id', $pop->parent_id) == $parent->id ? 'selected' : '' }}>
                            {{ $parent->name }} ({{ $parent->code }})
                        </option>
                    @endforeach
                </select>
                @error('parent_id')
                    <p class="text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 border-t border-slate-100 pt-5">
            <!-- Status Keaktifan -->
            <div>
                <label for="status" class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">Status POP <span class="text-rose-500">*</span></label>
                <select name="status" id="status" required 
                        class="w-full px-3 py-2 border @error('status') border-rose-500 focus:ring-rose-500 @else border-slate-300 focus:ring-sky-500 focus:border-sky-500 @enderror rounded-md text-sm text-slate-800 focus:outline-none focus:ring-1 bg-white">
                    <option value="active" {{ old('status', $pop->status) === 'active' ? 'selected' : '' }}>Aktif</option>
                    <option value="inactive" {{ old('status', $pop->status) === 'inactive' ? 'selected' : '' }}>Nonaktif</option>
                </select>
                @error('status')
                    <p class="text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="border-t border-slate-100 pt-5 space-y-4">
            <div class="pb-1">
                <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Penanggung Jawab (PIC)</h3>
                <p class="text-[11px] text-slate-400">Kontak person yang bertanggung jawab penuh terhadap POP ini.</p>
            </div>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- Nama PIC -->
                <div>
                    <label for="pic_name" class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">Nama Lengkap PIC</label>
                    <input type="text" name="pic_name" id="pic_name" value="{{ old('pic_name', $pop->pic_name) }}" placeholder="Contoh: Ahmad Subardjo"
                           class="w-full px-3 py-2 border @error('pic_name') border-rose-500 focus:ring-rose-500 @else border-slate-300 focus:ring-sky-500 focus:border-sky-500 @enderror rounded-md text-sm text-slate-800 focus:outline-none focus:ring-1">
                    @error('pic_name')
                        <p class="text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Kontak PIC -->
                <div>
                    <label for="pic_phone" class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">No. HP / WA PIC</label>
                    <input type="text" name="pic_phone" id="pic_phone" value="{{ old('pic_phone', $pop->pic_phone) }}" placeholder="Contoh: 08123456789"
                           class="w-full px-3 py-2 border @error('pic_phone') border-rose-500 focus:ring-rose-500 @else border-slate-300 focus:ring-sky-500 focus:border-sky-500 @enderror rounded-md text-sm text-slate-800 focus:outline-none focus:ring-1">
                    @error('pic_phone')
                        <p class="text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>
    </div>

    <!-- Right Panel: Lokasi & Wilayah -->
    <div class="space-y-6 lg:col-span-1">
        <div class="bg-white border border-slate-200 rounded-lg p-6 shadow-sm space-y-4">
            <div class="border-b border-slate-100 pb-3">
                <h3 class="text-sm font-bold text-slate-800">Detail Alamat & Lokasi</h3>
                <p class="text-xs text-slate-400 mt-0.5">Ubah alamat fisik dan koordinat geografis presisi POP.</p>
            </div>

            <!-- Alamat Lengkap -->
            <div>
                <label for="address" class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">Alamat Lengkap</label>
                <textarea name="address" id="address" rows="3" placeholder="Jl. Diponegoro No. 23, RT 02/05..."
                          class="w-full px-3 py-2 border @error('address') border-rose-500 focus:ring-rose-500 @else border-slate-300 focus:ring-sky-500 focus:border-sky-500 @enderror rounded-md text-sm text-slate-800 focus:outline-none focus:ring-1">{{ old('address', $pop->address) }}</textarea>
                @error('address')
                    <p class="text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</p>
                @enderror
            </div>

            <!-- Wilayah Administratif -->
            <div class="space-y-3">
                <div>
                    <label for="city" class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">Kota / Kabupaten</label>
                    <input type="text" name="city" id="city" value="{{ old('city', $pop->city) }}" placeholder="Sleman"
                           class="w-full px-3 py-2 border @error('city') border-rose-500 focus:ring-rose-500 @else border-slate-300 focus:ring-sky-500 focus:border-sky-500 @enderror rounded-md text-sm text-slate-800 focus:outline-none focus:ring-1">
                    @error('city')
                        <p class="text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="district" class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">Kecamatan</label>
                    <input type="text" name="district" id="district" value="{{ old('district', $pop->district) }}" placeholder="Depok"
                           class="w-full px-3 py-2 border @error('district') border-rose-500 focus:ring-rose-500 @else border-slate-300 focus:ring-sky-500 focus:border-sky-500 @enderror rounded-md text-sm text-slate-800 focus:outline-none focus:ring-1">
                    @error('district')
                        <p class="text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="village" class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">Desa / Kelurahan</label>
                    <input type="text" name="village" id="village" value="{{ old('village', $pop->village) }}" placeholder="Caturtunggal"
                           class="w-full px-3 py-2 border @error('village') border-rose-500 focus:ring-rose-500 @else border-slate-300 focus:ring-sky-500 focus:border-sky-500 @enderror rounded-md text-sm text-slate-800 focus:outline-none focus:ring-1">
                    @error('village')
                        <p class="text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Koordinat Geografis -->
            <div class="grid grid-cols-2 gap-3 border-t border-slate-100 pt-4">
                <div>
                    <label for="latitude" class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">Latitude</label>
                    <input type="text" name="latitude" id="latitude" value="{{ old('latitude', $pop->latitude) }}" placeholder="-7.782"
                           class="w-full px-3 py-2 border @error('latitude') border-rose-500 focus:ring-rose-500 @else border-slate-300 focus:ring-sky-500 focus:border-sky-500 @enderror rounded-md text-sm text-slate-800 focus:outline-none focus:ring-1">
                    @error('latitude')
                        <p class="text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="longitude" class="block text-xs font-bold text-slate-700 uppercase tracking-wide mb-1.5">Longitude</label>
                    <input type="text" name="longitude" id="longitude" value="{{ old('longitude', $pop->longitude) }}" placeholder="110.372"
                           class="w-full px-3 py-2 border @error('longitude') border-rose-500 focus:ring-rose-500 @else border-slate-300 focus:ring-sky-500 focus:border-sky-500 @enderror rounded-md text-sm text-slate-800 focus:outline-none focus:ring-1">
                    @error('longitude')
                        <p class="text-[10px] text-rose-500 mt-1 font-semibold">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        <!-- Submit Actions -->
        <div class="flex items-center gap-3">
            <a href="{{ route('master.pop.index') }}" class="flex-1 inline-flex items-center justify-center px-4 py-2.5 border border-slate-300 rounded-md shadow-sm text-sm font-semibold text-slate-700 bg-white hover:bg-slate-50 transition-colors focus:outline-none cursor-pointer">
                Batal
            </a>
            <button type="submit" class="flex-1 inline-flex items-center justify-center px-4 py-2.5 border border-transparent rounded-md shadow-sm text-sm font-semibold text-white bg-sky-600 hover:bg-sky-700 transition-colors focus:outline-none cursor-pointer">
                Perbarui POP
            </button>
        </div>
    </div>
</form>
@endsection
