@extends('layouts.app')

@section('title', 'Master Internet Package - Whusnet Operasional')
@section('page_title', 'Master Internet Package')

@section('content')
@if(session('success'))
<div class="mb-6 bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-r-lg shadow-sm">
    <span class="text-sm font-medium text-emerald-800">{{ session('success') }}</span>
</div>
@endif

<div class="bg-white border border-slate-200 rounded-lg p-6 mb-6">
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
            <h3 class="text-sm font-semibold text-slate-800">Manajemen Internet Package WHUSNET</h3>
            <p class="text-xs text-slate-500 mt-0.5">Nama modul Internet Package, struktur dan data menggunakan internet_packages.</p>
        </div>
        <div class="flex items-center gap-6">
            <div class="text-center">
                <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">TOTAL PACKAGE</span>
                <span class="text-lg font-bold text-slate-800 data-text">{{ \App\Models\internetPackage::count() }}</span>
            </div>
            <div class="h-8 w-px bg-slate-200"></div>
            <div class="text-center">
                <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">AKTIF</span>
                <span class="text-lg font-bold text-emerald-600 data-text">{{ \App\Models\internetPackage::active()->count() }}</span>
            </div>
            <div class="h-8 w-px bg-slate-200"></div>
            <div class="text-center">
                <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">KATEGORI</span>
                <span class="text-lg font-bold text-slate-800 data-text">{{ \App\Models\internetPackage::query()->distinct('category')->count('category') }}</span>
            </div>
        </div>
    </div>
</div>

<div class="bg-white border border-slate-200 rounded-lg p-5 mb-6 shadow-sm">
    <form action="{{ route('master.paket.index') }}" method="GET" class="flex flex-col lg:flex-row items-end lg:items-center justify-between gap-4">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 w-full lg:max-w-4xl">
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1.5">Pencarian</label>
                <input type="text" name="search" value="{{ $search }}" placeholder="Kode, nama, group, bandwidth..."
                       class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm text-slate-800 focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1.5">Kategori</label>
                <select name="category" class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm text-slate-800 bg-white focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500">
                    <option value="">Semua Kategori</option>
                    @foreach(\App\Models\internetPackage::CATEGORIES as $value => $label)
                        <option value="{{ $value }}" {{ $category === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1.5">Status</label>
                <select name="status" class="w-full px-3 py-2 border border-slate-300 rounded-md text-sm text-slate-800 bg-white focus:outline-none focus:ring-1 focus:ring-sky-500 focus:border-sky-500">
                    <option value="">Semua Status</option>
                    <option value="active" {{ $status === 'active' ? 'selected' : '' }}>Aktif</option>
                    <option value="inactive" {{ $status === 'inactive' ? 'selected' : '' }}>Nonaktif</option>
                </select>
            </div>
        </div>

        <div class="flex items-center gap-2 w-full lg:w-auto shrink-0 justify-end">
            <button type="submit" class="flex-1 lg:flex-none px-4 py-2 border border-slate-300 rounded-md text-sm font-semibold text-slate-700 bg-white hover:bg-slate-50">
                Filter
            </button>
            @if($search || $category || $status)
            <a href="{{ route('master.paket.index') }}" class="px-4 py-2 border border-slate-300 rounded-md text-sm font-semibold text-slate-700 bg-white hover:bg-slate-50">
                Reset
            </a>
            @endif
            @if(auth()->user()->hasPermission('manage_packages'))
            <a href="{{ route('master.paket.create') }}" class="flex-1 lg:flex-none px-4 py-2 rounded-md text-sm font-semibold text-white bg-sky-600 hover:bg-sky-700">
                Tambah Package
            </a>
            @endif
        </div>
    </form>
</div>

@php
    $groupedPackages = $packages->getCollection()->groupBy('category');
@endphp

<div class="mb-6 border-b border-slate-200 flex flex-wrap gap-1">
    <button onclick="filterCategory('all', this)" class="px-4 py-2 border-b-2 font-medium text-sm transition-all focus:outline-none cursor-pointer border-sky-600 text-sky-600 active-tab-btn">
        Semua Package
    </button>
    @foreach($groupedPackages as $categoryName => $categoryPackages)
        @php $slug = Str::slug($categoryName); @endphp
        <button onclick="filterCategory('{{ $slug }}', this)" class="px-4 py-2 border-b-2 border-transparent text-slate-500 hover:text-slate-800 hover:border-slate-300 font-medium text-sm transition-all focus:outline-none cursor-pointer">
            {{ $categoryName }}
        </button>
    @endforeach
</div>

@if($packages->isEmpty())
<div class="bg-white border border-slate-200 rounded-lg p-16 text-center">
    <h4 class="text-sm font-bold text-slate-800">Tidak ada package ditemukan</h4>
    <p class="text-xs text-slate-400 mt-1">Ubah filter atau tambahkan Internet Package baru.</p>
</div>
@else
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6" id="packages-container">
    @foreach($packages as $package)
        @php $slug = Str::slug($package->category); @endphp
        <div class="bg-white border border-slate-200 rounded-lg overflow-hidden flex flex-col hover:shadow-md transition-shadow package-card" data-category="{{ $slug }}">
            <div class="p-5 border-b border-slate-100 flex items-start justify-between bg-slate-50/50">
                <div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-sky-50 text-sky-700 border border-sky-100 mb-1.5 uppercase">
                        {{ $package->category }}
                    </span>
                    <h4 class="text-lg font-bold text-slate-900 tracking-tight">{{ $package->package_code }}</h4>
                    <p class="text-xs text-slate-500 mt-0.5 truncate max-w-[220px]">{{ $package->package_group }}</p>
                </div>
                <span class="{{ $package->is_active ? 'bg-green-50 text-green-700 border-green-100' : 'bg-rose-50 text-rose-700 border-rose-100' }} inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium border">
                    {{ $package->is_active ? 'Aktif' : 'Nonaktif' }}
                </span>
            </div>

            <div class="p-5 flex-1 flex flex-col justify-between">
                <div class="mb-4">
                    <div class="flex items-baseline justify-between mb-2">
                        <span class="text-xs font-semibold text-slate-400">Kecepatan</span>
                        <span class="text-sm font-semibold text-slate-700 data-text">{{ $package->bandwidth_label }}</span>
                    </div>
                    <div class="flex items-baseline gap-1 mt-1 py-2 border-y border-dashed border-slate-100">
                        <span class="text-xs text-slate-400 font-medium">Harga Bulanan</span>
                        <span class="ml-auto text-xl font-bold text-slate-800 data-text">{{ $package->monthly_price_formatted }}</span>
                        <span class="text-[10px] text-slate-400 font-medium">/bln</span>
                    </div>
                    @if((float) $package->ppn > 0 || (float) $package->discount_default > 0)
                    <div class="mt-2 text-[11px] text-slate-500">
                        Total setelah diskon/PPN:
                        <span class="font-semibold text-slate-700">{{ $package->total_price_formatted }}</span>
                    </div>
                    @endif
                </div>

                <div class="space-y-2 mb-5">
                    @if($package->modem)
                    <div class="flex justify-between text-xs py-1 border-b border-slate-50">
                        <span class="text-slate-400">Tipe Modem</span>
                        <span class="text-slate-700 font-medium">{{ $package->modem }}</span>
                    </div>
                    @endif
                    @if($package->contention_ratio)
                    <div class="flex justify-between text-xs py-1 border-b border-slate-50">
                        <span class="text-slate-400">Rasio</span>
                        <span class="text-slate-700 font-medium data-text">1:{{ $package->contention_ratio }}</span>
                    </div>
                    @endif
                    @if($package->max_users)
                    <div class="flex justify-between text-xs py-1 border-b border-slate-50">
                        <span class="text-slate-400">Maks Pengguna</span>
                        <span class="text-slate-700 font-medium data-text">{{ $package->max_users }} User</span>
                    </div>
                    @endif
                    @if($package->ip_address_type)
                    <div class="flex justify-between text-xs py-1 border-b border-slate-50">
                        <span class="text-slate-400">Jenis IP</span>
                        <span class="text-slate-700 font-medium">{{ $package->ip_address_type }}</span>
                    </div>
                    @endif
                    @if($package->contract_period_months)
                    <div class="flex justify-between text-xs py-1 border-b border-slate-50">
                        <span class="text-slate-400">Masa Kontrak</span>
                        <span class="text-slate-700 font-medium data-text">{{ $package->contract_period_months }} Bulan</span>
                    </div>
                    @endif
                    <div class="flex justify-between text-xs py-1 border-b border-slate-50">
                        <span class="text-slate-400">Biaya Pasang</span>
                        <span class="text-slate-700 font-medium">{{ $package->installation_fee_label ?? $package->installation_fee_formatted }}</span>
                    </div>
                </div>

                @if(!empty($package->features))
                <div class="mb-4">
                    <span class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">FITUR PACKAGE</span>
                    <ul class="space-y-1.5">
                        @foreach($package->features as $feature)
                        <li class="flex items-center gap-2 text-xs text-slate-600">
                            <span class="h-1.5 w-1.5 rounded-full bg-green-500 shrink-0"></span>
                            <span>{{ $feature }}</span>
                        </li>
                        @endforeach
                    </ul>
                </div>
                @endif

                @if(auth()->user()->hasPermission('manage_packages'))
                <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100">
                    <a href="{{ route('master.paket.edit', $package) }}" class="px-3 py-1.5 text-xs font-semibold text-sky-700 bg-sky-50 border border-sky-100 rounded-md hover:bg-sky-100">
                        Edit
                    </a>
                    <form action="{{ route('master.paket.toggle', $package) }}" method="POST">
                        @csrf
                        <button type="submit" class="px-3 py-1.5 text-xs font-semibold text-slate-700 bg-white border border-slate-200 rounded-md hover:bg-slate-50" onclick="return confirm('Ubah status package {{ $package->package_code }}?')">
                            {{ $package->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                        </button>
                    </form>
                </div>
                @endif
            </div>

            @if($package->terms || $package->description)
            <div class="px-5 py-3.5 bg-slate-50 border-t border-slate-100 space-y-1">
                @if($package->terms)
                <p class="text-[10px] text-slate-500 leading-relaxed">
                    <span class="font-semibold text-slate-700">S&K:</span> {{ $package->terms }}
                </p>
                @endif
                @if($package->description)
                <p class="text-[10px] text-slate-500 leading-relaxed">
                    <span class="font-semibold text-slate-700">Deskripsi:</span> {{ $package->description }}
                </p>
                @endif
            </div>
            @endif
        </div>
    @endforeach
</div>

@if($packages->hasPages())
<div class="mt-6">
    {{ $packages->links() }}
</div>
@endif
@endif
@endsection

@section('scripts')
<script>
    function filterCategory(slug, btn) {
        document.querySelectorAll('.active-tab-btn').forEach(button => {
            button.classList.remove('border-sky-600', 'text-sky-600', 'active-tab-btn');
            button.classList.add('border-transparent', 'text-slate-500');
        });

        btn.classList.add('border-sky-600', 'text-sky-600', 'active-tab-btn');
        btn.classList.remove('border-transparent', 'text-slate-500');

        document.querySelectorAll('.package-card').forEach(card => {
            if (slug === 'all' || card.getAttribute('data-category') === slug) {
                card.classList.remove('hidden');
            } else {
                card.classList.add('hidden');
            }
        });
    }
</script>
@endsection
