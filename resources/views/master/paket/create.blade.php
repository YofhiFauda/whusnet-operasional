@extends('layouts.app')

@section('title', 'Tambah Paket Internet - Whusnet Operasional')
@section('page_title', 'Tambah Paket Internet')

@section('content')
<div class="mb-6">
    <a href="{{ route('master.paket.index') }}" class="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-sky-600">
        Kembali ke Daftar Paket
    </a>
</div>

<div class="max-w-4xl">
    <div class="bg-white border border-slate-200 rounded-lg shadow-sm overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/70">
            <h2 class="text-base font-bold text-slate-800">Form Tambah Paket Internet</h2>
            <p class="text-xs text-slate-500 mt-0.5">Data disimpan ke master internet_packages sesuai rancangan WHUSNET.</p>
        </div>

        <form action="{{ route('master.paket.store') }}" method="POST" class="p-6 space-y-6">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label for="package_code" class="block text-xs font-semibold text-slate-600 mb-1.5">Kode Paket <span class="text-rose-500">*</span></label>
                    <input type="text" id="package_code" name="package_code" value="{{ old('package_code') }}" placeholder="Net138"
                           class="w-full px-3 py-2 border rounded-md text-sm {{ $errors->has('package_code') ? 'border-rose-400 bg-rose-50' : 'border-slate-300' }}">
                    @error('package_code')<p class="mt-1 text-xs text-rose-500">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="name" class="block text-xs font-semibold text-slate-600 mb-1.5">Nama Paket <span class="text-rose-500">*</span></label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}" placeholder="Net138"
                           class="w-full px-3 py-2 border rounded-md text-sm {{ $errors->has('name') ? 'border-rose-400 bg-rose-50' : 'border-slate-300' }}">
                    @error('name')<p class="mt-1 text-xs text-rose-500">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label for="category" class="block text-xs font-semibold text-slate-600 mb-1.5">Kategori <span class="text-rose-500">*</span></label>
                    <select id="category" name="category" class="w-full px-3 py-2 border rounded-md text-sm bg-white {{ $errors->has('category') ? 'border-rose-400 bg-rose-50' : 'border-slate-300' }}">
                        <option value="">-- Pilih Kategori --</option>
                        @foreach(\App\Models\internetPackage::CATEGORIES as $value => $label)
                            <option value="{{ $value }}" {{ old('category') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('category')<p class="mt-1 text-xs text-rose-500">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="package_group" class="block text-xs font-semibold text-slate-600 mb-1.5">Group Paket <span class="text-rose-500">*</span></label>
                    <input type="text" id="package_group" name="package_group" value="{{ old('package_group') }}" placeholder="Reguler Broadband Home Internet Only"
                           class="w-full px-3 py-2 border rounded-md text-sm {{ $errors->has('package_group') ? 'border-rose-400 bg-rose-50' : 'border-slate-300' }}">
                    @error('package_group')<p class="mt-1 text-xs text-rose-500">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-4 gap-5">
                <div class="sm:col-span-2">
                    <label for="bandwidth_label" class="block text-xs font-semibold text-slate-600 mb-1.5">Label Bandwidth <span class="text-rose-500">*</span></label>
                    <input type="text" id="bandwidth_label" name="bandwidth_label" value="{{ old('bandwidth_label') }}" placeholder="70 Mbps 1:8"
                           class="w-full px-3 py-2 border rounded-md text-sm {{ $errors->has('bandwidth_label') ? 'border-rose-400 bg-rose-50' : 'border-slate-300' }}">
                    @error('bandwidth_label')<p class="mt-1 text-xs text-rose-500">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="download_speed_mbps" class="block text-xs font-semibold text-slate-600 mb-1.5">Download Mbps</label>
                    <input type="number" step="0.01" min="0" id="download_speed_mbps" name="download_speed_mbps" value="{{ old('download_speed_mbps') }}"
                           class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm">
                </div>
                <div>
                    <label for="upload_speed_mbps" class="block text-xs font-semibold text-slate-600 mb-1.5">Upload Mbps</label>
                    <input type="number" step="0.01" min="0" id="upload_speed_mbps" name="upload_speed_mbps" value="{{ old('upload_speed_mbps') }}"
                           class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-4 gap-5">
                <div>
                    <label for="contention_ratio" class="block text-xs font-semibold text-slate-600 mb-1.5">Rasio</label>
                    <input type="number" min="1" id="contention_ratio" name="contention_ratio" value="{{ old('contention_ratio') }}" placeholder="4"
                           class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm">
                </div>
                <div>
                    <label for="monthly_price" class="block text-xs font-semibold text-slate-600 mb-1.5">Harga Bulanan <span class="text-rose-500">*</span></label>
                    <input type="number" min="0" id="monthly_price" name="monthly_price" value="{{ old('monthly_price') }}" placeholder="138000"
                           class="w-full px-3 py-2 border rounded-md text-sm {{ $errors->has('monthly_price') ? 'border-rose-400 bg-rose-50' : 'border-slate-300' }}">
                    @error('monthly_price')<p class="mt-1 text-xs text-rose-500">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="discount_default" class="block text-xs font-semibold text-slate-600 mb-1.5">Diskon Default (%)</label>
                    <input type="number" min="0" max="100" step="0.01" id="discount_default" name="discount_default" value="{{ old('discount_default', 0) }}"
                           class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm">
                </div>
                <div>
                    <label for="ppn" class="block text-xs font-semibold text-slate-600 mb-1.5">PPN (%)</label>
                    <input type="number" min="0" max="100" step="0.01" id="ppn" name="ppn" value="{{ old('ppn', 0) }}"
                           class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm">
                </div>
            </div>

            <div class="bg-sky-50 border border-sky-100 rounded-lg px-4 py-3 flex items-center justify-between">
                <span class="text-xs font-semibold text-sky-700">Estimasi Total Harga Setelah Diskon dan PPN</span>
                <span id="total-preview" class="text-sm font-bold text-sky-900 data-text">Rp 0</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                <div>
                    <label for="installation_fee" class="block text-xs font-semibold text-slate-600 mb-1.5">Biaya Pasang</label>
                    <input type="number" min="0" id="installation_fee" name="installation_fee" value="{{ old('installation_fee') }}"
                           class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm">
                </div>
                <div>
                    <label for="contract_period_months" class="block text-xs font-semibold text-slate-600 mb-1.5">Kontrak Bulan</label>
                    <input type="number" min="1" id="contract_period_months" name="contract_period_months" value="{{ old('contract_period_months') }}"
                           class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                <div>
                    <label for="modem" class="block text-xs font-semibold text-slate-600 mb-1.5">Modem</label>
                    <input type="text" id="modem" name="modem" value="{{ old('modem') }}" class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm">
                </div>
                <div>
                    <label for="max_users" class="block text-xs font-semibold text-slate-600 mb-1.5">Maks User</label>
                    <input type="number" min="1" id="max_users" name="max_users" value="{{ old('max_users') }}" class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm">
                </div>
                <div>
                    <label for="ip_address_type" class="block text-xs font-semibold text-slate-600 mb-1.5">Jenis IP</label>
                    <input type="text" id="ip_address_type" name="ip_address_type" value="{{ old('ip_address_type') }}" class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label for="installation_fee_label" class="block text-xs font-semibold text-slate-600 mb-1.5">Label Biaya Pasang</label>
                    <input type="text" id="installation_fee_label" name="installation_fee_label" value="{{ old('installation_fee_label') }}" placeholder="Gratis"
                           class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm">
                </div>
                <div>
                    <label for="profile" class="block text-xs font-semibold text-slate-600 mb-1.5">Profile Teknis</label>
                    <input type="text" id="profile" name="profile" value="{{ old('profile') }}" class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label for="technical_profile" class="block text-xs font-semibold text-slate-600 mb-1.5">Technical Profile</label>
                    <input type="text" id="technical_profile" name="technical_profile" value="{{ old('technical_profile') }}"
                           class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm">
                    <p class="mt-1 text-xs text-slate-400">Field tambahan dari Internet Package.</p>
                </div>
                <div>
                    <label for="description" class="block text-xs font-semibold text-slate-600 mb-1.5">Description</label>
                    <input type="text" id="description" name="description" value="{{ old('description') }}"
                           class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm">
                    <p class="mt-1 text-xs text-slate-400">Field tambahan dari Internet Package.</p>
                </div>
            </div>

            <div>
                <label for="terms" class="block text-xs font-semibold text-slate-600 mb-1.5">Syarat dan Ketentuan</label>
                <textarea id="terms" name="terms" rows="3" class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm resize-none">{{ old('terms') }}</textarea>
            </div>

            <div>
                <label for="is_active" class="block text-xs font-semibold text-slate-600 mb-1.5">Status <span class="text-rose-500">*</span></label>
                <select id="is_active" name="is_active" class="w-full sm:w-48 px-3 py-2 border border-slate-300 rounded-md text-sm bg-white">
                    <option value="1" {{ old('is_active', '1') === '1' ? 'selected' : '' }}>Aktif</option>
                    <option value="0" {{ old('is_active') === '0' ? 'selected' : '' }}>Nonaktif</option>
                </select>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                <a href="{{ route('master.paket.index') }}" class="px-4 py-2 text-sm font-semibold text-slate-700 bg-white border border-slate-300 rounded-md hover:bg-slate-50">Batal</a>
                <button type="submit" class="px-5 py-2 text-sm font-semibold text-white bg-sky-600 rounded-md hover:bg-sky-700">Simpan Paket</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
    function updateTotalPreview() {
        const price = parseFloat(document.getElementById('monthly_price').value) || 0;
        const discount = parseFloat(document.getElementById('discount_default').value) || 0;
        const ppn = parseFloat(document.getElementById('ppn').value) || 0;

        let total = price;
        if (discount > 0) total -= total * (discount / 100);
        if (ppn > 0) total += total * (ppn / 100);

        document.getElementById('total-preview').textContent = 'Rp ' + Math.round(total).toLocaleString('id-ID');
    }

    ['monthly_price', 'discount_default', 'ppn'].forEach(id => {
        document.getElementById(id).addEventListener('input', updateTotalPreview);
    });

    updateTotalPreview();
</script>
@endsection
