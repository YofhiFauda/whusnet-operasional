@extends('layouts.app')

@section('title', 'Setoran Kas - Whusnet Operasional')
@section('page_title', 'Setoran Kas')

{{--
    LEMBAR KERJA PENERIMA (Owner/atasan) — bukan halaman kas admin.

    Admin penyetor tidak punya tampilan apa pun di sini: halaman ini menyajikan
    uang milik SELURUH admin dalam scope, lengkap sampai nama pelanggan.
    Halamannya ada di Worksheet Admin.

    docs/plan/kolektor/analisa-setoran-kas-admin.md §11, §12.
--}}

@section('content')
<div class="space-y-6">
    {{-- Page Header --}}
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 mb-1 font-medium">
                <span>Operasional</span>
                <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span>Billing &amp; Tagihan</span>
                <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span class="text-sky-600 dark:text-sky-400 font-semibold">Setoran Kas</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-slate-100 tracking-tight">
                Setoran Kas — Penerimaan dari Admin
            </h1>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-0.5">
                Periksa uang yang disetorkan admin, telusuri sumbernya sampai ke pelanggan, dan pantau posisi kas Anda.
            </p>
        </div>

        @if ($jumlahMenunggu > 0)
            <div class="shrink-0 rounded-2xl border border-amber-200/80 dark:border-amber-500/30 bg-amber-50 dark:bg-amber-500/10 px-4 py-2.5">
                <div class="text-[11px] font-bold uppercase tracking-wider text-amber-700 dark:text-amber-400">Menunggu Diperiksa</div>
                <div class="text-lg font-bold text-amber-700 dark:text-amber-400 font-mono">{{ $jumlahMenunggu }} setoran</div>
            </div>
        @endif
    </div>

    @if ($errors->any())
        <x-ui.alert variant="error" title="Aksi Dibatalkan" class="rounded-2xl">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </x-ui.alert>
    @endif

    @if (session('success'))
        <x-ui.alert variant="success" class="rounded-2xl">{{ session('success') }}</x-ui.alert>
    @endif

    {{-- Titik nol: tanpa banner ini, saldo hari pertama terbaca sebagai seluruh
         riwayat perusahaan (§7.4). --}}
    @if ($zeroPointNote)
        <div class="rounded-2xl border border-slate-200/80 dark:border-slate-700/80 bg-slate-50 dark:bg-slate-800/60 px-4 py-3 text-xs text-slate-600 dark:text-slate-300">
            <span class="font-semibold">Titik nol pencatatan kas.</span> {{ $zeroPointNote }}
        </div>
    @endif

    {{-- ============ CARD ANALISA PENERIMAAN ============ --}}
    @include('partials.owner-cash-balance-card', [
        'brankas' => $ownerBrankas,
        'bank' => $ownerBank,
        'dalamPerjalanan' => $ownerDalamPerjalanan,
        'pemilik' => auth()->user(),
    ])

    {{-- ============ SETORAN MASUK ============ --}}
    <div class="space-y-3">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="text-sm font-bold text-slate-900 dark:text-slate-100">Setoran Masuk</h2>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                    Buka tiap baris untuk menelusuri sumber uangnya: dari kolektor mana, pelanggan siapa, berapa.
                </p>
            </div>

            {{-- Filter status. GET, jadi boleh dirakit klien — yang dilarang
                 dirakit di klien adalah target aksi yang MENGUBAH data. --}}
            <div class="flex items-center gap-1.5 shrink-0">
                @foreach ([null => 'Semua', 'menunggu' => 'Menunggu', 'selesai' => 'Selesai'] as $nilai => $label)
                    <a href="{{ route('cash-deposits.index', $nilai ? ['status' => $nilai] : []) }}"
                       class="px-3 py-1.5 rounded-xl text-xs font-semibold transition-all
                              {{ $status === $nilai ? 'bg-sky-600 text-white shadow-xs' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700/80 hover:bg-slate-50 dark:hover:bg-slate-700/60' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </div>

        @forelse ($deposits as $deposit)
            @php($isPending = $deposit->status === \App\Enums\CashDepositStatus::MENUNGGU_VERIFIKASI)
            @php($tercatat = $deposit->computedAmount())

            <div class="bg-white dark:bg-slate-800/90 border {{ $isPending ? 'border-amber-200/80 dark:border-amber-500/30' : 'border-slate-200/80 dark:border-slate-700/80' }} rounded-2xl overflow-hidden"
                 x-data="{ rincian: false }">
                <div class="p-4 sm:p-5 space-y-2">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-mono text-xs font-bold text-slate-900 dark:text-slate-100">{{ $deposit->deposit_number }}</span>
                                <span class="text-[10px] font-semibold px-2 py-0.5 rounded-lg {{ $deposit->status->badgeClasses() }}">
                                    {{ $deposit->status->label() }}
                                </span>
                                <span class="text-[10px] font-semibold px-2 py-0.5 rounded-lg bg-violet-50 dark:bg-violet-500/10 text-violet-700 dark:text-violet-400">
                                    Penyetor: {{ $deposit->depositor->name ?? '—' }}
                                </span>
                                @if ($deposit->pop)
                                    <span class="text-[10px] font-medium text-slate-400">{{ $deposit->pop->name }}</span>
                                @endif
                            </div>

                            <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                                {{ $deposit->channel?->label() ?? '—' }}
                                @if ($deposit->bank_name)
                                    · {{ $deposit->bank_name }}
                                @endif
                                @if ($deposit->account_number)
                                    · <span class="font-mono">{{ $deposit->account_number }}</span>
                                @endif
                                @if ($deposit->reference_no)
                                    · Ref <span class="font-mono">{{ $deposit->reference_no }}</span>
                                @endif
                                · {{ $deposit->submitted_at?->translatedFormat('d M Y H:i') }}
                            </div>

                            @if ($deposit->note)
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1 italic">{{ $deposit->note }}</p>
                            @endif
                            @if ($deposit->write_off_reason)
                                <p class="text-[11px] text-red-600 dark:text-red-400 mt-1">Ditutup: {{ $deposit->write_off_reason }}</p>
                            @endif

                            <div class="flex items-center gap-3 mt-1.5">
                                <button type="button" @click="rincian = !rincian"
                                        class="inline-flex items-center gap-1 text-[11px] font-semibold text-sky-600 dark:text-sky-400 hover:underline">
                                    <svg class="h-3 w-3 transition-transform" :class="rincian ? 'rotate-90' : ''" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                    </svg>
                                    Rincian sumber ({{ $deposit->collectorDeposits->count() }} setoran kolektor · {{ $deposit->manualPayments->count() }} bayar manual)
                                </button>
                                @if ($deposit->proof_path)
                                    <a href="{{ route('cash-deposits.download', $deposit->id) }}"
                                       class="text-[11px] font-semibold text-sky-600 dark:text-sky-400 hover:underline">Unduh bukti</a>
                                @endif
                            </div>
                        </div>

                        <div class="text-right shrink-0">
                            <div class="font-mono text-sm font-bold text-slate-900 dark:text-slate-100">
                                Rp {{ number_format($tercatat, 0, ',', '.') }}
                            </div>
                            <div class="text-[10px] text-slate-400">tercatat sistem</div>
                            @if (! $isPending)
                                <div class="font-mono text-xs text-slate-600 dark:text-slate-300 mt-1">
                                    Diterima: Rp {{ number_format((float) $deposit->declared_amount, 0, ',', '.') }}
                                </div>
                                @if (! \App\Support\Money::isZero($deposit->difference))
                                    <div class="font-mono text-xs font-bold {{ (float) $deposit->difference < 0 ? 'text-red-600 dark:text-red-400' : 'text-sky-600 dark:text-sky-400' }}">
                                        Selisih Rp {{ number_format(abs((float) $deposit->difference), 0, ',', '.') }}
                                        ({{ (float) $deposit->difference < 0 ? 'kurang' : 'lebih' }})
                                    </div>
                                @endif
                                @if ($deposit->verifier)
                                    <div class="text-[10px] text-slate-400 mt-0.5">oleh {{ $deposit->verifier->name }}</div>
                                @endif
                            @endif
                        </div>
                    </div>

                    {{-- RINCIAN SUMBER — dari kolektor mana, pelanggan siapa, berapa.
                         Seluruhnya turunan dari relasi; tak satu pun angka disimpan. --}}
                    <div x-show="rincian" x-collapse class="space-y-2 pt-1">
                        @forelse ($deposit->collectorDeposits as $setoranKolektor)
                            <div class="rounded-xl border border-slate-100 dark:border-slate-700/60 p-3">
                                <div class="flex items-center justify-between gap-2 mb-1.5">
                                    <div class="min-w-0 truncate">
                                        <span class="font-mono text-[11px] font-bold text-slate-700 dark:text-slate-300">{{ $setoranKolektor->deposit_number }}</span>
                                        <span class="text-[11px] text-slate-500 dark:text-slate-400 ml-1">
                                            Kolektor {{ $setoranKolektor->collector->name ?? '—' }}
                                        </span>
                                    </div>
                                    <span class="font-mono text-xs font-bold text-slate-700 dark:text-slate-300 shrink-0">
                                        Rp {{ number_format($setoranKolektor->cashReceivedByOffice(), 0, ',', '.') }}
                                    </span>
                                </div>
                                <div class="space-y-1">
                                    @foreach ($setoranKolektor->payments as $payment)
                                        <div class="flex items-center justify-between gap-2 text-[11px] py-1 px-2.5 rounded-lg bg-slate-50 dark:bg-slate-900/40">
                                            <span class="truncate text-slate-600 dark:text-slate-300">{{ $payment->customer->full_name ?? '—' }}</span>
                                            <span class="font-mono text-slate-600 dark:text-slate-300 shrink-0">Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @empty
                        @endforelse

                        @if ($deposit->manualPayments->isNotEmpty())
                            <div class="rounded-xl border border-slate-100 dark:border-slate-700/60 p-3">
                                <div class="flex items-center justify-between gap-2 mb-1.5">
                                    <span class="text-[11px] font-bold text-slate-700 dark:text-slate-300">Pembayaran Tunai di Kantor</span>
                                    <span class="font-mono text-xs font-bold text-slate-700 dark:text-slate-300 shrink-0">
                                        Rp {{ number_format(\App\Support\Money::sum($deposit->manualPayments->pluck('amount')), 0, ',', '.') }}
                                    </span>
                                </div>
                                <div class="space-y-1">
                                    @foreach ($deposit->manualPayments as $payment)
                                        <div class="flex items-center justify-between gap-2 text-[11px] py-1 px-2.5 rounded-lg bg-slate-50 dark:bg-slate-900/40">
                                            <div class="min-w-0 truncate">
                                                <span class="text-slate-600 dark:text-slate-300">{{ $payment->customer->full_name ?? '—' }}</span>
                                                <span class="font-mono text-slate-400 ml-1">{{ $payment->payment_number }}</span>
                                            </div>
                                            <span class="font-mono text-slate-600 dark:text-slate-300 shrink-0">Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if ($deposit->collectorDeposits->isEmpty() && $deposit->manualPayments->isEmpty())
                            <p class="text-[11px] text-slate-400 dark:text-slate-500">Tidak ada sumber tertaut pada setoran ini.</p>
                        @endif
                    </div>
                </div>

                {{-- Form pemeriksaan. Pemeriksa ≠ penyetor — guard aslinya di
                     CashDepositService, tombolnya cuma gerbang tampilan. --}}
                @if ($isPending && auth()->user()->hasPermission('cash_deposit.validate') && $deposit->depositor_id !== auth()->id())
                    <form action="{{ route('cash-deposits.verify', $deposit->id) }}" method="POST"
                          class="p-4 sm:p-5 border-t border-slate-100 dark:border-slate-700/80 bg-slate-50/60 dark:bg-slate-900/40 space-y-3"
                          data-confirm="Tutup setoran kas {{ $deposit->deposit_number }} dengan nominal yang Anda hitung?">
                        @csrf
                        <h3 class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">Form Pemeriksaan Kas</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">Uang Diterima (Rp)</label>
                                <input type="text" inputmode="decimal" data-rupiah name="declared_amount" required
                                       value="{{ \App\Helpers\FormatHelper::rupiahInput($tercatat) }}"
                                       class="w-full text-xs px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-200 focus:outline-none focus:border-sky-500 font-mono">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">Catatan (wajib jika ada selisih)</label>
                                <input type="text" name="note" maxlength="1000"
                                       class="w-full text-xs px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-200 focus:outline-none focus:border-sky-500">
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold py-2 px-5 rounded-xl transition-all cursor-pointer shadow-xs">
                                Terima &amp; Tutup Setoran
                            </button>
                        </div>
                    </form>
                @endif

                {{-- Penutupan selisih (Owner) --}}
                @if ($deposit->status->isOpenDifference() && auth()->user()->hasPermission('cash_deposit.approve') && $deposit->depositor_id !== auth()->id())
                    <form action="{{ route('cash-deposits.write-off', $deposit->id) }}" method="POST"
                          class="p-4 sm:p-5 border-t border-slate-100 dark:border-slate-700/80 bg-red-50/30 dark:bg-red-500/5 space-y-2"
                          data-confirm="Tutup selisih {{ $deposit->deposit_number }} sebesar Rp{{ number_format(abs((float) $deposit->difference), 0, ',', '.') }}? Tindakan ini tidak dapat dibatalkan.">
                        @csrf
                        <h3 class="text-xs font-bold text-red-700 dark:text-red-400 uppercase tracking-wider">Penutupan Selisih Kas (Owner)</h3>
                        <div class="flex flex-col sm:flex-row gap-2">
                            <input type="text" name="write_off_reason" required maxlength="1000" placeholder="Alasan penutupan (wajib diisi)..."
                                   class="flex-1 text-xs px-3 py-2 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-200 focus:outline-none focus:border-red-500">
                            <button type="submit" class="bg-red-600 hover:bg-red-700 text-white text-xs font-semibold py-2 px-5 rounded-xl transition-all cursor-pointer shrink-0 shadow-xs">
                                Tutup Selisih
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        @empty
            <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-10 text-center">
                <div class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-700/50 text-slate-400 flex items-center justify-center mx-auto mb-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
                <p class="text-xs font-semibold text-slate-700 dark:text-slate-300">Belum Ada Setoran Masuk</p>
                <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Belum ada admin yang menyerahkan kas dalam jangkauan Anda.</p>
            </div>
        @endforelse

        <div>{{ $deposits->links() }}</div>
    </div>
</div>
@endsection
