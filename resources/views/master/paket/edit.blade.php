@extends('layouts.app')

@section('title', 'Edit Paket Internet - Whusnet Operasional')
@section('page_title', 'Edit Paket Internet')

@section('content')
<div class="mb-6">
    <a href="{{ route('master.paket.index') }}" class="inline-flex items-center gap-1.5 text-sm text-slate-500 dark:text-slate-400 hover:text-sky-600">
        Kembali ke Daftar Paket
    </a>
</div>

<div class="max-w-4xl">
    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-sm overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-700/50 bg-slate-50/70 flex items-center justify-between">
            <div>
                <h2 class="text-base font-bold text-slate-800 dark:text-slate-200">Edit Paket Internet</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Ubah data paket dari master internet_packages.</p>
            </div>
            <span class="{{ $paket->is_active ? 'bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400 border-green-100 dark:border-green-800/30' : 'bg-rose-50 dark:bg-rose-900/20 text-rose-700 dark:text-rose-400 border-rose-100 dark:border-rose-800/30' }} inline-flex items-center px-2.5 py-0.5 rounded text-[11px] font-bold border">
                {{ $paket->is_active ? 'Aktif' : 'Nonaktif' }}
            </span>
        </div>

        <form action="{{ route('master.paket.update', $paket) }}" method="POST" class="p-6 space-y-6">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label for="package_code" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Kode Paket <span class="text-rose-500">*</span></label>
                    <input type="text" id="package_code" name="package_code" value="{{ old('package_code', $paket->package_code) }}"
                           class="w-full px-3 py-2 border rounded-md text-sm {{ $errors->has('package_code') ? 'border-rose-400 bg-rose-50 dark:bg-rose-900/20' : 'border-slate-300 dark:border-slate-600' }}">
                    @error('package_code')<p class="mt-1 text-xs text-rose-500">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="name" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Nama Paket <span class="text-rose-500">*</span></label>
                    <input type="text" id="name" name="name" value="{{ old('name', $paket->name) }}"
                           class="w-full px-3 py-2 border rounded-md text-sm {{ $errors->has('name') ? 'border-rose-400 bg-rose-50 dark:bg-rose-900/20' : 'border-slate-300 dark:border-slate-600' }}">
                    @error('name')<p class="mt-1 text-xs text-rose-500">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label for="category" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Kategori <span class="text-rose-500">*</span></label>
                    <select id="category" name="category" class="w-full px-3 py-2 border rounded-md text-sm bg-white dark:bg-slate-800 {{ $errors->has('category') ? 'border-rose-400 bg-rose-50 dark:bg-rose-900/20' : 'border-slate-300 dark:border-slate-600' }}">
                        @foreach(\App\Models\internetPackage::CATEGORIES as $value => $label)
                            <option value="{{ $value }}" {{ old('category', $paket->category) === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('category')<p class="mt-1 text-xs text-rose-500">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="package_group" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Group Paket <span class="text-rose-500">*</span></label>
                    <input type="text" id="package_group" name="package_group" value="{{ old('package_group', $paket->package_group) }}"
                           class="w-full px-3 py-2 border rounded-md text-sm {{ $errors->has('package_group') ? 'border-rose-400 bg-rose-50 dark:bg-rose-900/20' : 'border-slate-300 dark:border-slate-600' }}">
                    @error('package_group')<p class="mt-1 text-xs text-rose-500">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-4 gap-5">
                <div class="sm:col-span-2">
                    <label for="bandwidth_label" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Label Bandwidth <span class="text-rose-500">*</span></label>
                    <input type="text" id="bandwidth_label" name="bandwidth_label" value="{{ old('bandwidth_label', $paket->bandwidth_label) }}"
                           class="w-full px-3 py-2 border rounded-md text-sm {{ $errors->has('bandwidth_label') ? 'border-rose-400 bg-rose-50 dark:bg-rose-900/20' : 'border-slate-300 dark:border-slate-600' }}">
                    @error('bandwidth_label')<p class="mt-1 text-xs text-rose-500">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="download_speed_mbps" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Download Mbps</label>
                    <input type="number" step="0.01" min="0" id="download_speed_mbps" name="download_speed_mbps" value="{{ old('download_speed_mbps', $paket->download_speed_mbps) }}"
                           class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md text-sm">
                </div>
                <div>
                    <label for="upload_speed_mbps" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Upload Mbps</label>
                    <input type="number" step="0.01" min="0" id="upload_speed_mbps" name="upload_speed_mbps" value="{{ old('upload_speed_mbps', $paket->upload_speed_mbps) }}"
                           class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md text-sm">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-4 gap-5">
                <div>
                    <label for="contention_ratio" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Rasio</label>
                    <input type="number" min="1" id="contention_ratio" name="contention_ratio" value="{{ old('contention_ratio', $paket->contention_ratio) }}"
                           class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md text-sm">
                </div>
                <div>
                    <label for="monthly_price" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Harga Bulanan <span class="text-rose-500">*</span></label>
                    <input type="number" min="0" id="monthly_price" name="monthly_price" value="{{ old('monthly_price', $paket->monthly_price) }}"
                           class="w-full px-3 py-2 border rounded-md text-sm {{ $errors->has('monthly_price') ? 'border-rose-400 bg-rose-50 dark:bg-rose-900/20' : 'border-slate-300 dark:border-slate-600' }}">
                    @error('monthly_price')<p class="mt-1 text-xs text-rose-500">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="discount_default" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Diskon Default (%)</label>
                    <input type="number" min="0" max="100" step="0.01" id="discount_default" name="discount_default" value="{{ old('discount_default', $paket->discount_default) }}"
                           class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md text-sm">
                </div>
                <div>
                    <label for="ppn" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">PPN (%)</label>
                    <input type="number" min="0" max="100" step="0.01" id="ppn" name="ppn" value="{{ old('ppn', $paket->ppn) }}"
                           class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md text-sm">
                </div>
            </div>

            <div class="bg-sky-50 dark:bg-sky-900/20 border border-sky-100 dark:border-sky-800/30 rounded-lg px-4 py-3 flex items-center justify-between">
                <span class="text-xs font-semibold text-sky-700 dark:text-sky-400">Estimasi Total Harga Setelah Diskon dan PPN</span>
                <span id="total-preview" class="text-sm font-bold text-sky-900 data-text">{{ $paket->total_price_formatted }}</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                <div>
                    <label for="installation_fee" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Biaya Pasang</label>
                    <input type="number" min="0" id="installation_fee" name="installation_fee" value="{{ old('installation_fee', $paket->installation_fee) }}"
                           class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md text-sm">
                </div>
                <div>
                    <label for="contract_period_months" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Kontrak Bulan</label>
                    <input type="number" min="1" id="contract_period_months" name="contract_period_months" value="{{ old('contract_period_months', $paket->contract_period_months) }}"
                           class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md text-sm">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                <div>
                    <label for="modem" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Modem</label>
                    <input type="text" id="modem" name="modem" value="{{ old('modem', $paket->modem) }}" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md text-sm">
                </div>
                <div>
                    <label for="max_users" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Maks User</label>
                    <input type="number" min="1" id="max_users" name="max_users" value="{{ old('max_users', $paket->max_users) }}" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md text-sm">
                </div>
                <div>
                    <label for="ip_address_type" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Jenis IP</label>
                    <input type="text" id="ip_address_type" name="ip_address_type" value="{{ old('ip_address_type', $paket->ip_address_type) }}" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md text-sm">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label for="installation_fee_label" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Label Biaya Pasang</label>
                    <input type="text" id="installation_fee_label" name="installation_fee_label" value="{{ old('installation_fee_label', $paket->installation_fee_label) }}"
                           class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md text-sm">
                </div>
                <div>
                    <label for="profile" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Profile Teknis</label>
                    <input type="text" id="profile" name="profile" value="{{ old('profile', $paket->profile) }}" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md text-sm">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label for="technical_profile" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Technical Profile</label>
                    <input type="text" id="technical_profile" name="technical_profile" value="{{ old('technical_profile', $paket->technical_profile) }}"
                           class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md text-sm">
                    <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Field tambahan dari Internet Package.</p>
                </div>
                <div>
                    <label for="description" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Description</label>
                    <input type="text" id="description" name="description" value="{{ old('description', $paket->description) }}"
                           class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md text-sm">
                    <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">Field tambahan dari Internet Package.</p>
                </div>
            </div>

            <div>
                <label for="terms" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Syarat dan Ketentuan</label>
                <textarea id="terms" name="terms" rows="3" class="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md text-sm resize-none">{{ old('terms', $paket->terms) }}</textarea>
            </div>

            <div>
                <label for="is_active" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Status <span class="text-rose-500">*</span></label>
                <select id="is_active" name="is_active" class="w-full sm:w-48 px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-md text-sm bg-white dark:bg-slate-800">
                    <option value="1" {{ old('is_active', $paket->is_active ? '1' : '0') === '1' ? 'selected' : '' }}>Aktif</option>
                    <option value="0" {{ old('is_active', $paket->is_active ? '1' : '0') === '0' ? 'selected' : '' }}>Nonaktif</option>
                </select>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-700/50">
                <a href="{{ route('master.paket.index') }}" class="px-4 py-2 text-sm font-semibold text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 rounded-md hover:bg-slate-50 dark:hover:bg-slate-700/50 dark:hover:bg-slate-800/50">Batal</a>
                <button type="submit" class="px-5 py-2 text-sm font-semibold text-white bg-sky-600 dark:bg-sky-500 rounded-md hover:bg-sky-700">Perbarui Paket</button>
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
