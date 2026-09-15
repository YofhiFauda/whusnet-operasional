@extends('layouts.app')

@section('title', 'Verifikasi Registrasi - Whusnet Operasional')
@section('page_title', 'Verifikasi Registrasi')

@section('content')
{{-- Flash messages ditangani otomatis oleh global Component Toast (<x-toast />) --}}

<div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-6 mb-6">
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
            <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-200">Registrasi Menunggu Verifikasi</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 max-w-3xl">
                Pelanggan baru diregistrasi (non-Skip-Survey) <span class="font-semibold text-slate-600 dark:text-slate-300">belum punya Task/FopTask Survey</span> sampai Admin/CS menyetujui di sini — begitu disetujui, pelanggan masuk antrean survey dan muncul di Task FOP. Registrasi Skip Survey tidak melewati gerbang ini.
            </p>
        </div>
        <div class="text-center shrink-0">
            <span class="block text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">MENUNGGU</span>
            <span class="text-lg font-bold text-slate-800 dark:text-slate-200 data-text">{{ $customers->total() }}</span>
        </div>
    </div>
</div>

<div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-4 mb-4">
    <form method="GET" action="{{ route('customer-registration-verifications.index') }}" class="flex items-center gap-2">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama, NIK, atau ID REG..."
               class="w-full max-w-xs text-xs font-sans px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20 transition-colors">
        <button type="submit" class="px-3 py-2 text-xs font-semibold rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-600 transition-colors">Cari</button>
        @if(request('search'))
            <a href="{{ route('customer-registration-verifications.index') }}" class="text-xs text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300">Reset</a>
        @endif
    </form>
</div>

<div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden shadow-xs">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
            <thead>
                <tr class="bg-white dark:bg-slate-800 border-b border-slate-100 dark:border-slate-700/60 text-slate-400 dark:text-slate-500 uppercase text-[10px] font-bold tracking-wider">
                    <th class="px-4 py-2.5">No.</th>
                    <th class="px-4 py-2.5">ID REG</th>
                    <th class="px-4 py-2.5">Nama</th>
                    <th class="px-4 py-2.5">POP/Cabang</th>
                    <th class="px-4 py-2.5">Paket</th>
                    <th class="px-4 py-2.5">Diregistrasi</th>
                    <th class="px-4 py-2.5 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                @forelse($customers as $i => $customer)
                    <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-900/30 transition-colors">
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $customers->firstItem() + $i }}</td>
                        <td class="px-4 py-3 font-mono text-slate-500 dark:text-slate-400">{{ $customer->customer_code }}</td>
                        <td class="px-4 py-3 font-semibold text-slate-700 dark:text-slate-200">{{ $customer->full_name }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $customer->pop?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $customer->customerService?->internetPackage?->name ?? $customer->customerService?->package_name_snapshot ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ \App\Support\IndonesianDate::dateTime($customer->created_at) }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('customer-registration-verifications.show', $customer) }}" class="px-2.5 py-1 text-[11px] font-semibold rounded-lg border border-sky-200 dark:border-sky-800/60 text-sky-600 dark:text-sky-400 hover:bg-sky-50 dark:hover:bg-sky-900/20 transition-colors">
                                Tinjau
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-slate-400 dark:text-slate-500">
                            Tidak ada registrasi yang menunggu verifikasi saat ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($customers->hasPages())
        <div class="px-4 py-3 border-t border-slate-100 dark:border-slate-700/60">
            {{ $customers->links() }}
        </div>
    @endif
</div>
@endsection
