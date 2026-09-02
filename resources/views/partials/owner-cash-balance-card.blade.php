{{--
    Posisi kas OWNER — ujung rantai uang, dan jawaban atas pertanyaan yang
    selama ini tak terjawab: "uang yang sudah disetorkan admin, sekarang di
    mana?"

    Tiga angka yang SENGAJA tidak pernah dijumlahkan
    (docs/plan/kolektor/analisa-setoran-kas-admin.md §11):

      1. Brankas         → uang fisik yang benar-benar dipegang Owner
      2. Masuk Bank      → sudah di rekening, tak pernah lewat tangan Owner
      3. Dalam Perjalanan → klaim admin yang BELUM dihitung Owner — bukan kas

    Menjumlahkan (1) dan (2) melahirkan "uang tunai" yang mustahil dihitung
    ulang di meja. Menjumlahkan (3) ke mana pun berarti mengakui uang yang
    belum pernah dilihat.

    Parameter: $brankas (float), $bank (array{total,per_bank}),
               $dalamPerjalanan (float), $pemilik (User)
--}}
<div class="space-y-2">
    <div>
        <h2 class="text-sm font-bold text-slate-900 dark:text-slate-100">Kas Diterima — {{ $pemilik->name }}</h2>
        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
            Uang yang sudah Anda periksa dan terima dari para admin.
        </p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
        {{-- 1. Brankas --}}
        <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-4 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Brankas (Tunai)</span>
                <div class="w-8 h-8 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h12a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm8 6a2 2 0 100-4 2 2 0 000 4zm0 0v4"/></svg>
                </div>
            </div>
            <div class="mt-2 text-lg sm:text-2xl font-bold text-slate-900 dark:text-slate-100 font-mono truncate">
                Rp {{ number_format($brankas, 0, ',', '.') }}
            </div>
            <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Uang fisik hasil setoran tunai admin yang sudah Anda hitung.</p>
        </div>

        {{-- 2. Masuk bank --}}
        <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-4 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Masuk Bank Bulan Ini</span>
                <div class="w-8 h-8 rounded-xl bg-sky-50 dark:bg-sky-500/10 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                </div>
            </div>
            <div class="mt-2 text-lg sm:text-2xl font-bold text-slate-900 dark:text-slate-100 font-mono truncate">
                Rp {{ number_format($bank['total'], 0, ',', '.') }}
            </div>
            <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Sudah di rekening — <span class="font-semibold">bukan</span> uang di tangan Anda.</p>
            @if (! empty($bank['per_bank']))
                <div class="mt-2 flex flex-wrap gap-1.5">
                    @foreach ($bank['per_bank'] as $namaBank => $nominal)
                        <span class="text-[10px] font-semibold px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-700/60 text-slate-600 dark:text-slate-300 font-mono">
                            {{ strtoupper($namaBank) }} · Rp {{ number_format($nominal, 0, ',', '.') }}
                        </span>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- 3. Dalam perjalanan --}}
        <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-4 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Dalam Perjalanan</span>
                <div class="w-8 h-8 rounded-xl {{ $dalamPerjalanan > 0 ? 'bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400' : 'bg-slate-100 dark:bg-slate-700/50 text-slate-400' }} flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
            <div class="mt-2 text-lg sm:text-2xl font-bold font-mono truncate {{ $dalamPerjalanan > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-900 dark:text-slate-100' }}">
                Rp {{ number_format($dalamPerjalanan, 0, ',', '.') }}
            </div>
            <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Dikirim admin, belum Anda periksa. Masih klaim, belum jadi kas.</p>
        </div>
    </div>
</div>
