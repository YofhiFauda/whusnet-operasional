{{--
    Daftar teknisi + status saldo/overdue — diekstrak dari index.blade.php
    biar dipakai ULANG oleh endpoint collector-worksheet.summary-panel
    (live-refresh, lihat CollectorWorksheetController::summaryPanel()).
    x-show di sini merujuk state Alpine (`activeStaffTab`, `searchFilter`)
    yang didefinisikan di x-data PARENT (tabnya) — tetap jalan selama
    elemen ini di-swap sebagai descendant parent yang sama, bukan dipindah
    ke luar DOM tree-nya.
--}}
<div id="staff-list-technicians" role="tabpanel" aria-labelledby="staff-tab-technicians"
     x-show="activeStaffTab === 'technicians'" x-cloak
     class="divide-y divide-slate-100 dark:divide-slate-700/50 max-h-[36rem] overflow-y-auto custom-scrollbar">
    <p class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400">Pilih Teknisi untuk membuka Pembayaran, Sudah Dibayar, Setor, dan Riwayat Pembayaran.</p>
    @forelse ($technicianRows as $row)
        <a href="{{ route('collector-worksheet.technicians.show', $row['user']->id) }}"
           x-show="searchFilter === '' || @js(mb_strtolower($row['user']->name)).includes(searchFilter.toLowerCase())"
           class="group block p-4 hover:bg-slate-50/80 dark:hover:bg-slate-700/30 transition-all border-l-2 border-transparent hover:border-amber-500">
            <div class="flex items-center gap-3">
                <div class="h-10 w-10 rounded-xl bg-amber-500 text-slate-950 font-bold text-sm flex items-center justify-center shrink-0">
                    {{ strtoupper(substr($row['user']->name, 0, 1)) }}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="font-bold text-sm text-slate-900 dark:text-slate-100 truncate group-hover:text-amber-700 dark:group-hover:text-amber-300">{{ $row['user']->name }}</div>
                    <div class="flex flex-wrap items-center gap-2 mt-1 text-[11px]">
                        <span class="{{ $row['user']->status === 'active' ? 'text-emerald-700 dark:text-emerald-400' : 'text-slate-500 dark:text-slate-400' }}">{{ $row['user']->status === 'active' ? 'Aktif' : 'Nonaktif' }}</span>
                        <span class="{{ $row['overdue'] ? 'text-amber-700 dark:text-amber-300' : 'text-slate-500 dark:text-slate-400' }}">{{ $row['overdue'] ? 'Melewati batas setor' : ($row['balance'] > 0 ? 'Belum disetor' : 'Tidak ada saldo') }}</span>
                    </div>
                </div>
                <div class="text-right shrink-0">
                    <div class="text-[10px] uppercase font-bold tracking-wider text-slate-500 dark:text-slate-400">Belum Disetor</div>
                    <div class="font-bold text-sm text-amber-700 dark:text-amber-300 font-mono">Rp {{ number_format($row['balance'], 0, ',', '.') }}</div>
                </div>
                <svg class="w-4 h-4 text-slate-300 dark:text-slate-600 group-hover:text-amber-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </div>
        </a>
    @empty
        <div class="p-8 text-center text-xs text-slate-500 dark:text-slate-400">Belum ada Teknisi dalam POP yang dapat Anda kelola.</div>
    @endforelse
</div>
