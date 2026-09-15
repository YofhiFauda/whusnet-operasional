@extends('layouts.app')

@section('title', 'Verifikasi BD - Whusnet Operasional')
@section('page_title', 'Menunggu Verifikasi BD')

@section('content')
{{-- Flash messages ditangani otomatis oleh global Component Toast (<x-toast />) --}}

<div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-6 mb-6">
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div>
            <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-200">Pelanggan Bisnis Menunggu Aktivasi</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 max-w-3xl">
                Pelanggan kategori Bisnis sudah lolos verifikasi CS (tagihan awal terbit, teknis kelar) tapi <span class="font-semibold text-slate-600 dark:text-slate-300">belum resmi aktif</span> — nunggu BD isi Biaya Instalasi & menekan "Verifikasi &amp; Aktifkan". Pelanggan Home Broadband tidak pernah muncul di sini, tetap langsung aktif seperti biasa.
            </p>
        </div>
        <div class="text-center shrink-0">
            <span class="block text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">MENUNGGU</span>
            <span class="text-lg font-bold text-slate-800 dark:text-slate-200 data-text">{{ $customers->count() }}</span>
        </div>
    </div>
</div>

<div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden shadow-xs">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse text-xs">
            <thead>
                <tr class="bg-white dark:bg-slate-800 border-b border-slate-100 dark:border-slate-700/60 text-slate-400 dark:text-slate-500 uppercase text-[10px] font-bold tracking-wider">
                    <th class="px-4 py-2.5">No.</th>
                    <th class="px-4 py-2.5">Nama</th>
                    <th class="px-4 py-2.5">POP/Cabang</th>
                    <th class="px-4 py-2.5">Paket</th>
                    <th class="px-4 py-2.5 text-right">Biaya Langganan</th>
                    <th class="px-4 py-2.5">Menunggu Sejak</th>
                    <th class="px-4 py-2.5 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                @forelse($customers as $i => $customer)
                    <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-900/30 transition-colors">
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $i + 1 }}</td>
                        <td class="px-4 py-3 font-semibold text-slate-700 dark:text-slate-200">{{ $customer->full_name }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $customer->pop?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $customer->customerService?->internetPackage?->name ?? $customer->customerService?->package_name_snapshot ?? '—' }}</td>
                        <td class="px-4 py-3 text-right font-mono text-slate-700 dark:text-slate-300">
                            {{ $customer->customerService ? \App\Helpers\FormatHelper::rupiah($customer->customerService->total_monthly_bill) : '—' }}
                        </td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ \App\Support\IndonesianDate::dateTime($customer->updated_at) }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('business-development-verifications.show', $customer) }}" class="px-2.5 py-1 text-[11px] font-semibold rounded-lg border border-sky-200 dark:border-sky-800/60 text-sky-600 dark:text-sky-400 hover:bg-sky-50 dark:hover:bg-sky-900/20 transition-colors">
                                Verifikasi
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-6 text-center text-slate-400 dark:text-slate-500">
                            Tidak ada pelanggan yang menunggu verifikasi BD saat ini.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
