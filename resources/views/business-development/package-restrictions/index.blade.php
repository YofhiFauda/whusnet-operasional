@extends('layouts.app')

@section('title', 'Restriksi Paket - Whusnet Operasional')
@section('page_title', 'Restriksi Paket per Role')

@section('content')
<div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-6 mb-6">
    <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-200">Daftar Paket Terbatas</h3>
    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-3xl">
        Paket yang dicentang di bawah ini adalah SATU-SATUNYA paket yang boleh dipilih role berikut saat registrasi/ubah paket pelanggan:
        @if($restrictedRoles->isEmpty())
            <span class="italic">belum ada role yang ditandai restricted.</span>
        @else
            @foreach($restrictedRoles as $role)
                <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 border border-amber-200 dark:border-amber-800/60 ml-1">{{ $role->name }}</span>
            @endforeach
        @endif
        Role lain (tanpa penanda ini) tetap melihat semua paket aktif. Menambah/mencabut role dari daftar restriksi dilakukan lewat halaman <span class="font-semibold">Role Management</span>.
    </p>
</div>

<form action="{{ route('business-development.package-restrictions.update') }}" method="POST" class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-6">
    @csrf
    @method('PUT')

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 mb-6">
        @forelse($packages as $package)
            <label class="flex items-center gap-3 px-3 py-2.5 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-900/30 cursor-pointer">
                <input type="checkbox" name="package_ids[]" value="{{ $package->id }}"
                       {{ in_array($package->id, $selectedIds, true) ? 'checked' : '' }}
                       class="rounded border-slate-300 text-sky-600 focus:ring-sky-500">
                <span class="text-sm text-slate-700 dark:text-slate-200">
                    {{ $package->name }}
                    <span class="block text-[10px] text-slate-400 dark:text-slate-500">{{ $package->package_code }} — {{ $package->monthly_price_formatted }}</span>
                </span>
            </label>
        @empty
            <p class="text-sm text-slate-400 dark:text-slate-500 col-span-full">Belum ada paket aktif.</p>
        @endforelse
    </div>

    <button type="submit" class="px-4 py-2 text-sm font-semibold rounded-lg bg-sky-600 hover:bg-sky-700 text-white transition-colors">
        Simpan Daftar Restriksi
    </button>
</form>
@endsection
