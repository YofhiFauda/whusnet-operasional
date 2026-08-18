{{--
    Form setor kas admin → Owner/bank.

    Letaknya di Worksheet Admin, bukan halaman sendiri: padanannya di sisi
    kolektor pun begitu — kolektor menyetor dari halaman kerjanya sendiri
    (`/collector-worklist`). Admin bekerja di `/collector-worksheet`, dan di
    situ pula uang kolektor berpindah ke tangannya saat setoran diverifikasi.
    docs/plan/kolektor/analisa-setoran-kas-admin.md §9.

    Target POST dirender server-side lewat `route()` — tidak pernah dirakit di
    klien (ADHOC-20): form yang atribut `action`-nya gagal terisi akan mem-POST
    ke URL halaman sendiri dan setoran gagal tanpa pesan apa pun.

    Parameter: $tunai (float), $sumberCount (int), $idempotencyKey (string)
--}}
<form action="{{ route('cash-deposits.store') }}" method="POST" enctype="multipart/form-data"
      x-data="{ channel: 'tunai_brankas' }"
      class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-4 sm:p-5 space-y-3"
      data-confirm="Setorkan SELURUH saldo tunai Rp{{ number_format($tunai, 0, ',', '.') }} dari {{ $sumberCount }} sumber? Setoran tidak bisa sebagian.">
    @csrf
    {{-- Kunci idempotensi dibuat server-side per pemuatan halaman: klik dobel
         atau retry jaringan tidak melahirkan dua setoran atas uang yang sama. --}}
    <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
    {{-- Penanda halaman asal. Tujuan redirect-nya dipilih dari daftar TERTUTUP
         di server (CashDepositController::store) — nilai ini cuma penanda, bukan
         URL. URL redirect yang datang mentah dari klien adalah open-redirect. --}}
    <input type="hidden" name="redirect_to" value="worksheet">

    <div class="flex items-center justify-between gap-3">
        <div>
            <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">Setorkan Kas ke Owner / Bank</h3>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                Seluruh saldo tunai disetorkan sekaligus — tidak boleh ada saldo mengendap.
            </p>
        </div>
        <span class="font-mono text-sm font-bold text-emerald-600 dark:text-emerald-400 shrink-0">
            Rp {{ number_format($tunai, 0, ',', '.') }}
        </span>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">Diserahkan Ke</label>
            <select name="channel" x-model="channel" required
                    class="w-full text-xs px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-200 focus:outline-none focus:border-sky-500">
                @foreach (\App\Enums\CashDepositChannel::cases() as $pilihan)
                    <option value="{{ $pilihan->value }}">{{ $pilihan->label() }}</option>
                @endforeach
            </select>
        </div>

        <div x-show="channel === 'transfer_bank'" x-cloak>
            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">Bank Tujuan</label>
            <input type="text" name="bank_name" maxlength="100" placeholder="misal: BCA / BRI"
                   class="w-full text-xs px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-200 focus:outline-none focus:border-sky-500">
        </div>

        <div x-show="channel === 'transfer_bank'" x-cloak>
            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">No. Rekening</label>
            <input type="text" name="account_number" maxlength="50"
                   class="w-full text-xs px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-200 focus:outline-none focus:border-sky-500 font-mono">
        </div>

        <div x-show="channel === 'transfer_bank'" x-cloak>
            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">No. Referensi</label>
            <input type="text" name="reference_no" maxlength="100"
                   class="w-full text-xs px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-200 focus:outline-none focus:border-sky-500 font-mono">
        </div>

        <div>
            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">Bukti (opsional, JPG/PNG/PDF)</label>
            <input type="file" name="proof" accept=".jpg,.jpeg,.png,.pdf"
                   class="w-full text-xs text-slate-600 dark:text-slate-300 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-slate-100 dark:file:bg-slate-700 file:text-slate-700 dark:file:text-slate-200">
        </div>

        <div>
            <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">Catatan</label>
            <input type="text" name="note" maxlength="1000" placeholder="opsional"
                   class="w-full text-xs px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-200 focus:outline-none focus:border-sky-500">
        </div>
    </div>

    <div class="flex items-center justify-between gap-3 pt-1">
        {{-- Tautan rincian penuh hanya untuk pemegang `view` (pemeriksa).
             Menawarkannya ke admin biasa cuma melahirkan 403 setelah diklik. --}}
        @if (auth()->user()->hasPermission('cash_deposit.view'))
            <a href="{{ route('cash-deposits.index') }}" class="text-[11px] font-semibold text-sky-600 dark:text-sky-400 hover:underline">
                Lihat rincian sumber &amp; riwayat →
            </a>
        @else
            <span class="text-[11px] text-slate-400 dark:text-slate-500">Seluruh saldo tunai disetorkan sekaligus.</span>
        @endif
        <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold py-2 px-5 rounded-xl transition-all cursor-pointer shadow-xs">
            Setorkan Seluruh Saldo
        </button>
    </div>
</form>
