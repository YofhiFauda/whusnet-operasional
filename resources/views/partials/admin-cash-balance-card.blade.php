{{--
    Kartu posisi kas admin — TIGA angka yang sengaja TIDAK PERNAH dijumlahkan
    (docs/plan/kolektor/analisa-setoran-kas-admin.md §2):

      1. Tunai belum disetor  → satu-satunya angka yang jadi kewajiban setor
      2. Non-tunai            → sudah di bank, tak pernah lewat tangan admin
      3. Selisih terbuka      → hasil pemeriksaan yang belum ditutup Owner

    Kalau ketiganya digabung, "saldo 0" jadi ambigu: beres, atau nombok yang
    tak tercatat. Dipakai bersama oleh halaman Setoran Kas & Worksheet Admin,
    supaya angkanya mustahil menyimpang antar-halaman.

    Parameter: $tunai (float), $nonTunai (array{total,per_metode}),
               $selisihTerbuka (float), $ringkas (bool, opsional),
               $dapatSetor (bool, opsional), $sumberCount (int, opsional),
               $idempotencyKey (string, opsional — wajib bila $dapatSetor)

    `$dapatSetor` menempelkan tombol + panel setor ke kartu Tunai. Dinyalakan di
    Worksheet Admin — halaman kerja admin, tempat uang kolektor benar-benar
    berpindah tangan (§9). Halaman Setoran Kas memanggil partial ini dengan
    `dapatSetor = false`: di sana perannya arsip & pemeriksaan, dan satu aksi
    tidak boleh punya dua pintu yang bisa saling menyimpang.
--}}
@php($ringkas = $ringkas ?? false)
{{-- Dua keadaan yang SENGAJA dipisah, karena artinya berbeda bagi pembaca:
       $bolehSetor → berwenang memegang & menyetorkan kas (permission);
       $dapatSetor → berwenang DAN memang ada uang yang bisa disetorkan.
     Sebelum dipisah, admin ber-permission dengan saldo nol melihat kartu tanpa
     tombol sama sekali — tak bisa dibedakan dari "hak saya dicabut". --}}
@php($bolehSetor = ($dapatSetor ?? false) && auth()->user()->hasPermission('cash_deposit.create'))
@php($dapatSetor = $bolehSetor && $tunai > 0)
@php($sumberCount = $sumberCount ?? 0)

<div x-data="{ panelSetor: false }" class="space-y-3">
<div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
    {{-- 1. Tunai belum disetor --}}
    <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-4 shadow-xs">
        <div class="flex items-center justify-between">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Tunai Belum Disetor</span>
            <div class="w-8 h-8 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            </div>
        </div>
        <div class="mt-2 text-lg sm:text-2xl font-bold text-slate-900 dark:text-slate-100 font-mono truncate">
            Rp {{ number_format($tunai, 0, ',', '.') }}
        </div>
        <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Uang fisik di tangan Anda — wajib disetor ke Owner/bank.</p>

        @if ($dapatSetor)
            {{-- Jumlah sumber, bukan rincian: admin perlu tahu APA yang akan
                 disetor tanpa berpindah halaman, sementara rincian per pelanggan
                 tetap jadi urusan halaman Setoran Kas (§9.1). --}}
            <p class="mt-2 text-[11px] font-semibold text-slate-600 dark:text-slate-300">
                {{ $sumberCount }} sumber uang menunggu disetorkan
            </p>
            <button type="button" @click="panelSetor = !panelSetor"
                    class="mt-2 w-full inline-flex items-center justify-center gap-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold py-2 px-4 rounded-xl transition-all cursor-pointer shadow-xs">
                <span x-text="panelSetor ? 'Tutup Form Setoran' : 'Setorkan Kas'">Setorkan Kas</span>
                <svg class="h-3.5 w-3.5 transition-transform" :class="panelSetor ? 'rotate-180' : ''"
                     fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
        @elseif ($bolehSetor)
            {{-- Berwenang, tapi tak ada uangnya. Dikatakan eksplisit supaya
                 keadaan ini tidak terbaca sebagai "hak setor saya dicabut" —
                 dan supaya jelas dari mana saldo ini seharusnya datang. --}}
            <div class="mt-2 rounded-xl bg-slate-50 dark:bg-slate-900/40 px-3 py-2">
                <p class="text-[11px] font-semibold text-slate-600 dark:text-slate-300">
                    Belum ada uang tunai yang perlu disetorkan.
                </p>
                <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5 leading-relaxed">
                    Saldo terisi ketika <span class="font-semibold">Anda</span> memverifikasi setoran kolektor,
                    atau menerima pembayaran tunai di kantor. Setoran yang diverifikasi orang lain masuk ke saldo orang tersebut.
                </p>
            </div>
        @endif
    </div>

    {{-- 2. Non-tunai (INFORMASI, bukan kewajiban setor) --}}
    <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-4 shadow-xs">
        <div class="flex items-center justify-between">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Non-Tunai Bulan Ini</span>
            <div class="w-8 h-8 rounded-xl bg-sky-50 dark:bg-sky-500/10 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
            </div>
        </div>
        <div class="mt-2 text-lg sm:text-2xl font-bold text-slate-900 dark:text-slate-100 font-mono truncate">
            Rp {{ number_format($nonTunai['total'], 0, ',', '.') }}
        </div>
        <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">
            Transfer &amp; QRIS — sudah di rekening, <span class="font-semibold">bukan</span> kewajiban setor.
        </p>
        @if (! $ringkas && ! empty($nonTunai['per_metode']))
            <div class="mt-2 flex flex-wrap gap-1.5">
                @foreach ($nonTunai['per_metode'] as $metode => $nominal)
                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-700/60 text-slate-600 dark:text-slate-300 font-mono">
                        {{ strtoupper($metode) }} · Rp {{ number_format($nominal, 0, ',', '.') }}
                    </span>
                @endforeach
            </div>
        @endif
    </div>

    {{-- 3. Selisih terbuka --}}
    <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-4 shadow-xs">
        <div class="flex items-center justify-between">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Selisih Kas Terbuka</span>
            <div class="w-8 h-8 rounded-xl {{ $selisihTerbuka > 0 ? 'bg-red-50 dark:bg-red-500/10 text-red-600 dark:text-red-400' : 'bg-slate-100 dark:bg-slate-700/50 text-slate-400' }} flex items-center justify-center shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5 19h14a2 2 0 001.84-2.75L13.74 4a2 2 0 00-3.48 0L3.16 16.25A2 2 0 005 19z"/></svg>
            </div>
        </div>
        <div class="mt-2 text-lg sm:text-2xl font-bold font-mono truncate {{ $selisihTerbuka > 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-900 dark:text-slate-100' }}">
            Rp {{ number_format($selisihTerbuka, 0, ',', '.') }}
        </div>
        <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Belum ditutup Owner. Tidak ikut nol saat Anda menyetor.</p>
    </div>
</div>

@if ($dapatSetor)
    <div x-show="panelSetor" x-collapse x-cloak>
        @include('partials.cash-deposit-form', [
            'tunai' => $tunai,
            'sumberCount' => $sumberCount,
            'idempotencyKey' => $idempotencyKey,
        ])
    </div>
@endif
</div>
