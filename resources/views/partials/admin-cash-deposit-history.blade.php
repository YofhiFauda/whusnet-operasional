{{--
    Riwayat setoran kas SENDIRI — pandangan PENYETOR.

    Ada DUA tingkat rincian di modul ini, dan bedanya bukan estetika melainkan
    kewenangan (docs/plan/kolektor/analisa-setoran-kas-admin.md §10):

      - PENYETOR (admin, `cash_deposit.create`) → berkas ini. Pertanyaannya
        cuma "setoran saya sudah diperiksa belum, hasilnya apa". Tidak memuat
        nama pelanggan, nama kolektor, maupun kas orang lain.
      - PEMERIKSA (Owner/atasan, `cash_deposit.view`) → halaman `/cash-deposits`.
        Posisi kas admin mana pun dalam scope-nya, antrean pemeriksaan, dan
        rincian sampai tingkat pelanggan.

    Menaruh pandangan pemeriksa di depan penyetor bukan cuma berlebihan — ia
    membocorkan sebaran uang lintas admin ke orang yang cuma perlu tahu nasib
    setorannya sendiri.

    Parameter: $riwayat (LengthAwarePaginator<CashDeposit>)
--}}
<div x-data="{
        isOpen: localStorage.getItem('admin_deposit_history_open') === 'true' || {{ request()->has('kas_page') ? 'true' : 'false' }}
     }"
     class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl overflow-hidden transition-all duration-200">
    
    <button type="button" 
            @click="isOpen = !isOpen; localStorage.setItem('admin_deposit_history_open', isOpen)"
            :class="isOpen 
                ? 'border-sky-500 dark:border-sky-400 bg-slate-50/50 dark:bg-slate-800/40 border-b border-slate-100 dark:border-slate-700/80' 
                : 'border-slate-300 dark:border-slate-600 hover:border-sky-500 bg-white dark:bg-slate-800'
            "
            class="w-full flex items-center justify-between px-4 sm:px-5 py-4 transition-all text-left focus:outline-none select-none cursor-pointer group border-l-4">
        <div class="min-w-0 pr-4">
            <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2 group-hover:text-sky-600 dark:group-hover:text-sky-400 transition-colors duration-150">
                <span>Riwayat Setoran Kas Anda</span>
                @if ($riwayat->total() > 0)
                    <span class="inline-flex items-center justify-center px-2 py-0.5 text-[10px] font-bold leading-none text-slate-600 dark:text-slate-300 bg-slate-150 dark:bg-slate-700 rounded-full font-mono transition-colors duration-150">
                        {{ $riwayat->total() }}
                    </span>
                @endif
            </h3>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 truncate flex items-center gap-1.5 flex-wrap">
                <span>Uang yang Anda serahkan ke Owner/bank beserta hasil pemeriksaannya.</span>
                <span class="text-slate-300 dark:text-slate-600 hidden sm:inline">•</span>
                <span class="text-sky-600 dark:text-sky-400 font-semibold group-hover:underline" x-text="isOpen ? 'Tutup riwayat' : 'Lihat riwayat'">Lihat riwayat</span>
            </p>
        </div>
        <div class="shrink-0">
            <div class="w-7 h-7 rounded-full bg-slate-100 dark:bg-slate-700/60 group-hover:bg-sky-100 dark:group-hover:bg-sky-950/40 flex items-center justify-center text-slate-500 dark:text-slate-400 group-hover:text-sky-600 dark:group-hover:text-sky-400 transition-all duration-150 shadow-xs">
                <svg class="h-4 w-4 transition-transform duration-200" 
                     :class="isOpen ? 'rotate-180' : ''" 
                     fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                </svg>
            </div>
        </div>
    </button>

    <div x-show="isOpen" x-collapse x-cloak>
        <div class="divide-y divide-slate-100 dark:divide-slate-700/60 max-h-[320px] overflow-y-auto custom-scrollbar">
            @forelse ($riwayat as $setoran)
                @php($tercatat = $setoran->computedAmount())
                <div class="px-4 sm:px-5 py-3 flex items-start justify-between gap-3 hover:bg-slate-50/40 dark:hover:bg-slate-700/10 transition-colors">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-mono text-xs font-bold text-slate-900 dark:text-slate-100">{{ $setoran->deposit_number }}</span>
                            <span class="text-[10px] font-semibold px-2 py-0.5 rounded-lg {{ $setoran->status->badgeClasses() }}">
                                {{ $setoran->status->label() }}
                            </span>
                        </div>
                        <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                            {{ $setoran->channel?->label() ?? '—' }}
                            @if ($setoran->bank_name)
                                · {{ $setoran->bank_name }}
                            @endif
                            @if ($setoran->reference_no)
                                · Ref <span class="font-mono">{{ $setoran->reference_no }}</span>
                            @endif
                            · {{ $setoran->submitted_at?->translatedFormat('d M Y H:i') }}
                        </div>
                        @if ($setoran->verifier)
                            <div class="text-[11px] text-slate-400 mt-0.5">Diperiksa {{ $setoran->verifier->name }}</div>
                        @endif
                        @if ($setoran->note)
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1 italic">{{ $setoran->note }}</p>
                        @endif
                        @if ($setoran->proof_path)
                            <a href="{{ route('cash-deposits.download', $setoran->id) }}"
                               class="inline-block mt-1 text-[11px] font-semibold text-sky-600 dark:text-sky-400 hover:underline">Unduh bukti</a>
                        @endif
                    </div>

                    <div class="text-right shrink-0">
                        <div class="font-mono text-sm font-bold text-slate-900 dark:text-slate-100">
                            Rp {{ number_format($tercatat, 0, ',', '.') }}
                        </div>
                        <div class="text-[10px] text-slate-400">tercatat sistem</div>
                        @if ($setoran->status->isVerified())
                            <div class="font-mono text-[11px] text-slate-600 dark:text-slate-300 mt-1">
                                Diterima: Rp {{ number_format((float) $setoran->declared_amount, 0, ',', '.') }}
                            </div>
                            @if (! \App\Support\Money::isZero($setoran->difference))
                                {{-- Selisih ditampilkan apa adanya ke penyetor: ini
                                     kewajiban (atau kelebihan) yang menyangkut dirinya
                                     langsung, bukan temuan internal pemeriksa. --}}
                                <div class="font-mono text-[11px] font-bold {{ (float) $setoran->difference < 0 ? 'text-red-600 dark:text-red-400' : 'text-sky-600 dark:text-sky-400' }}">
                                    Selisih Rp {{ number_format(abs((float) $setoran->difference), 0, ',', '.') }}
                                    ({{ (float) $setoran->difference < 0 ? 'kurang' : 'lebih' }})
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
            @empty
                <div class="px-4 sm:px-5 py-8 text-center">
                    <p class="text-xs font-semibold text-slate-700 dark:text-slate-300">Belum Ada Setoran Kas</p>
                    <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Uang yang Anda terima belum pernah diteruskan ke Owner/bank.</p>
                </div>
            @endforelse
        </div>

        @if ($riwayat->hasPages())
            <div class="px-4 sm:px-5 py-3 border-t border-slate-100 dark:border-slate-700/80 bg-slate-50/30 dark:bg-slate-800/10">
                {{ $riwayat->links() }}
            </div>
        @endif
    </div>
</div>
