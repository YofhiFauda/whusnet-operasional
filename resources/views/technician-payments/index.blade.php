@extends('layouts.app')

@section('title', 'Catat Pembayaran Teknisi - Whusnet Operasional')
@section('page_title', 'Catat Pembayaran')

@section('content')
<div class="space-y-6 max-w-7xl mx-auto pb-16"
     x-data="technicianPayment({
        searchUrl: @js($searchUrl),
        storeUrl: @js($storeUrl),
        idempotencyKey: @js($idempotencyKey),
        today: @js(now()->toDateString()),
        bankAccounts: @js($bankAccounts),
     })">

    {{-- Header & Breadcrumbs --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 mb-1.5 font-medium">
                <span>Operasional</span>
                <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span>Billing &amp; Kasir</span>
                <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span class="text-amber-600 dark:text-amber-400 font-semibold">Jalur Lapangan</span>
            </div>
            <div class="flex flex-wrap items-center gap-2.5">
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-slate-100 tracking-tight">Catat Pembayaran Lapangan</h1>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-500/30">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                    Mode Teknisi
                </span>
            </div>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1">
                Pencatatan langsung tagihan pelanggan dalam POP Anda (tanpa antrean penugasan rute).
            </p>
        </div>

        {{-- Quick Guide Chip --}}
        <div class="hidden sm:flex items-center gap-2 px-3.5 py-2 rounded-2xl bg-white dark:bg-slate-800/80 border border-slate-200/80 dark:border-slate-700/80 shadow-2xs text-xs text-slate-600 dark:text-slate-300">
            <div class="w-7 h-7 rounded-xl bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <span>Batas setor harian pukul <strong>{{ $closeTime }} WIB</strong></span>
        </div>
    </div>

    {{-- Bento Overview: Saldo & Status Setoran --}}
    <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
        {{-- Card Saldo Kas di Tangan --}}
        <div class="md:col-span-7 bg-gradient-to-br from-slate-900 via-slate-850 to-slate-900 text-white rounded-3xl p-5 sm:p-6 shadow-sm border border-slate-800 relative overflow-hidden flex flex-col justify-between">
            <div class="absolute -right-8 -bottom-8 w-40 h-40 bg-amber-500/10 rounded-full blur-2xl pointer-events-none"></div>
            
            <div>
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></div>
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Kas di Tangan (Belum Disetor)</span>
                    </div>
                    <span class="px-2.5 py-0.5 rounded-full bg-slate-800 text-slate-300 border border-slate-700 text-[11px] font-medium font-mono">
                        {{ auth()->user()->name }}
                    </span>
                </div>

                <div class="mt-3 flex items-baseline gap-2">
                    <span class="text-2xl sm:text-4xl font-bold font-mono tracking-tight text-white">
                        Rp {{ number_format($saldo, 0, ',', '.') }}
                    </span>
                </div>

                <p class="text-xs text-slate-400 mt-1">
                    @if($saldo > 0)
                        Uang tunai pelanggan yang wajib diserahkan ke admin kasir.
                    @else
                        Tidak ada saldo tertahan di tangan Anda saat ini.
                    @endif
                </p>
            </div>

            <div class="mt-5 pt-4 border-t border-slate-800 flex flex-wrap items-center justify-between gap-3">
                <div class="text-[11px] text-slate-400 flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>Cut-off setor: {{ $closeTime }} WIB</span>
                </div>

                @if($saldo > 0)
                    <form method="POST" action="{{ $depositUrl }}"
                          onsubmit="return confirm('Setorkan seluruh saldo Rp {{ number_format($saldo, 0, ',', '.') }} sekarang? Setoran akan menunggu verifikasi kasir/admin.')">
                        @csrf
                        <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                        <button type="submit"
                                class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-400 hover:to-teal-500 text-slate-950 font-bold text-xs sm:text-sm shadow-md transition-all active:scale-95 cursor-pointer">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                            <span>Setor Seluruh Saldo</span>
                        </button>
                    </form>
                @endif
            </div>
        </div>

        {{-- Card Status Kepatuhan Setoran --}}
        <div class="md:col-span-5 rounded-3xl p-5 sm:p-6 border shadow-2xs flex flex-col justify-between transition-all {{ $belumSetor ? 'bg-amber-50/90 dark:bg-amber-500/10 border-amber-300 dark:border-amber-500/30' : 'bg-white dark:bg-slate-800/90 border-slate-200/80 dark:border-slate-700/80' }}">
            <div>
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider {{ $belumSetor ? 'text-amber-700 dark:text-amber-400' : 'text-slate-400 dark:text-slate-500' }}">
                        Status Rekonsiliasi
                    </span>
                    <div class="w-8 h-8 rounded-xl {{ $belumSetor ? 'bg-amber-100 dark:bg-amber-500/20 text-amber-600 dark:text-amber-400' : 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' }} flex items-center justify-center shrink-0">
                        @if($belumSetor)
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        @else
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        @endif
                    </div>
                </div>

                <div class="mt-2.5">
                    @if($belumSetor)
                        <div class="text-lg font-bold text-amber-800 dark:text-amber-300 flex items-center gap-2">
                            <span>Perlu Setor Segera</span>
                        </div>
                        <p class="text-xs text-amber-700/90 dark:text-amber-300/90 mt-1 leading-relaxed">
                            Terdapat penerimaan uang sebelum pukul {{ $closeTime }} yang belum disetor. Ini adalah pengingat operasional, pencatatan baru tetap dapat dilakukan.
                        </p>
                    @else
                        <div class="text-lg font-bold text-emerald-700 dark:text-emerald-400 flex items-center gap-1.5">
                            <span>Status Tertib</span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                            Tidak ada penerimaan tertunda dari hari sebelumnya. Seluruh setoran telah sinkron dengan kasir.
                        </p>
                    @endif
                </div>
            </div>

            <div class="mt-4 pt-3 border-t {{ $belumSetor ? 'border-amber-200 dark:border-amber-500/20' : 'border-slate-100 dark:border-slate-700/60' }} text-[11px] text-slate-400 dark:text-slate-500">
                Pencatatan menggunakan enkripsi &amp; token idempotensi aman.
            </div>
        </div>
    </div>

    {{-- Main Working Deck: Pencarian & Hasil Tagihan --}}
    <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-3xl p-5 sm:p-6 shadow-xs space-y-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 dark:border-slate-700/70 pb-4">
            <div>
                <h2 class="text-base font-bold text-slate-900 dark:text-slate-100 flex items-center gap-2">
                    <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <span>Cari Tagihan Pelanggan</span>
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    Masukkan CID atau nama pelanggan (min. 3 karakter) dalam jangkauan POP Anda.
                </p>
            </div>
            <div class="text-[11px] text-slate-400 dark:text-slate-500 font-mono">
                Scope POP Aktif
            </div>
        </div>

        {{-- Omnibox Search Field --}}
        <div class="space-y-2">
            <div class="relative flex items-center">
                <div class="absolute left-4 text-slate-400 dark:text-slate-500 pointer-events-none">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                
                <input id="tp-search"
                       type="search"
                       x-model="query"
                       @keydown.enter.prevent="search()"
                       placeholder="Ketik CID pelanggan atau nama lengkap..."
                       class="w-full pl-11 pr-28 py-3.5 rounded-2xl bg-slate-50 dark:bg-slate-900/90 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 text-sm focus:ring-2 focus:ring-amber-500/30 focus:border-amber-500 transition-all">

                <div class="absolute right-2 flex items-center gap-1.5">
                    <button type="button"
                            x-show="query.length > 0"
                            x-cloak
                            @click="query = ''; customers = []; searched = false; searchError = ''"
                            class="p-1.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-lg hover:bg-slate-200 dark:hover:bg-slate-800 transition-colors cursor-pointer"
                            title="Bersihkan pencarian">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>

                    <button type="button"
                            @click="search()"
                            :disabled="searching"
                            class="px-4 py-2 rounded-xl bg-slate-900 dark:bg-slate-700 hover:bg-slate-800 dark:hover:bg-slate-600 disabled:opacity-50 text-white text-xs font-semibold shadow-xs transition-all flex items-center gap-1.5 cursor-pointer">
                        <svg x-show="searching" class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        <span x-text="searching ? 'Mencari...' : 'Cari'"></span>
                    </button>
                </div>
            </div>

            <div x-show="searchError" x-cloak class="flex items-center gap-1.5 text-xs text-rose-600 dark:text-rose-400 font-medium pl-1">
                <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span x-text="searchError"></span>
            </div>
        </div>

        {{-- Hasil Pencarian Pelanggan --}}
        <div class="space-y-3 pt-2">
            {{-- State: Loading --}}
            <div x-show="searching" x-cloak class="py-8 text-center space-y-2">
                <div class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 animate-spin">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                </div>
                <div class="text-xs text-slate-500 dark:text-slate-400">Mencari tagihan belum lunas di database...</div>
            </div>

            {{-- State: Ada Hasil --}}
            <template x-if="customers.length > 0 && !searching">
                <div class="space-y-4">
                    <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400 px-1">
                        <span x-text="'Ditemukan ' + customers.length + ' pelanggan dengan tagihan aktif:'"></span>
                        <span class="text-[11px]">Klik "Tambah ke Pembayaran" untuk memproses</span>
                    </div>

                    <div class="grid grid-cols-1 gap-3.5">
                        <template x-for="customer in customers" :key="customer.customer_id">
                            <div class="rounded-2xl border border-slate-200/90 dark:border-slate-700/80 bg-slate-50/70 dark:bg-slate-900/50 p-4 transition-all hover:border-amber-300 dark:hover:border-amber-500/40">
                                {{-- Customer Header --}}
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-200/60 dark:border-slate-700/60 pb-3">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 font-bold text-xs flex items-center justify-center shrink-0">
                                            <span x-text="(customer.full_name || 'P').substring(0, 2).toUpperCase()"></span>
                                        </div>
                                        <div>
                                            <div class="font-bold text-slate-900 dark:text-slate-100 text-sm sm:text-base" x-text="customer.full_name"></div>
                                            <div class="flex items-center gap-2 mt-0.5">
                                                <span class="font-mono text-xs px-2 py-0.5 rounded-md bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 font-semibold" x-text="customer.cid || '-'"></span>
                                                <span class="text-xs text-slate-400 font-medium" x-text="customer.invoices.length + ' Tagihan Tertunda'"></span>
                                            </div>
                                        </div>
                                    </div>

                                    <button type="button"
                                            @click="addAllInvoices(customer)"
                                            x-show="hasUnselectedInvoices(customer)"
                                            class="self-start sm:self-auto text-xs font-semibold text-amber-700 dark:text-amber-400 hover:text-amber-800 dark:hover:text-amber-300 px-3 py-1.5 rounded-xl bg-amber-100/70 dark:bg-amber-500/20 border border-amber-300/60 dark:border-amber-500/30 transition-colors cursor-pointer">
                                        + Tambah Semua (Pelanggan Ini)
                                    </button>
                                </div>

                                {{-- Invoices Sub-list --}}
                                <div class="mt-3 space-y-2">
                                    <template x-for="invoice in customer.invoices" :key="invoice.invoice_id">
                                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3 rounded-xl bg-white dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700/80 shadow-2xs">
                                            <div class="flex flex-wrap items-center gap-2.5 text-xs">
                                                <div class="font-mono font-semibold text-slate-800 dark:text-slate-200 px-2 py-1 rounded-lg bg-slate-100 dark:bg-slate-700/80 text-[11px]" x-text="invoice.invoice_number"></div>
                                                <span class="px-2 py-0.5 rounded-md bg-sky-50 dark:bg-sky-500/10 text-sky-700 dark:text-sky-300 border border-sky-200/70 dark:border-sky-500/30 font-medium text-[11px]" x-text="'Periode ' + invoice.billing_period"></span>
                                                <div class="text-slate-600 dark:text-slate-300">
                                                    Sisa: <strong class="font-mono text-slate-900 dark:text-white" x-text="'Rp ' + format(invoice.remaining_amount)"></strong>
                                                </div>
                                            </div>

                                            <button type="button"
                                                    @click="addInvoice(customer, invoice)"
                                                    :disabled="isSelected(invoice.invoice_id)"
                                                    :class="isSelected(invoice.invoice_id) ? 'bg-slate-100 dark:bg-slate-700/60 text-slate-400 dark:text-slate-500 border border-slate-200 dark:border-slate-700 cursor-not-allowed' : 'bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-bold shadow-xs cursor-pointer active:scale-95'"
                                                    class="inline-flex items-center justify-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-all shrink-0">
                                                <template x-if="isSelected(invoice.invoice_id)">
                                                    <span class="flex items-center gap-1">
                                                        <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                        <span>Sudah di Keranjang</span>
                                                    </span>
                                                </template>
                                                <template x-if="!isSelected(invoice.invoice_id)">
                                                    <span class="flex items-center gap-1">
                                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                                        <span>Tambah ke Pembayaran</span>
                                                    </span>
                                                </template>
                                            </button>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </template>

            {{-- State: Kosong Setelah Dicari --}}
            <div x-show="searched && customers.length === 0 && !searchError && !searching" x-cloak class="py-10 text-center space-y-2">
                <div class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-400 mx-auto flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div class="text-sm font-semibold text-slate-800 dark:text-slate-200">Tidak Ada Tagihan Belum Lunas</div>
                <p class="text-xs text-slate-500 dark:text-slate-400 max-w-sm mx-auto">
                    Pelanggan tidak ditemukan, sudah lunas seluruhnya, atau berada di luar cakupan POP cabang Anda.
                </p>
            </div>

            {{-- State: Belum Mencari --}}
            <div x-show="!searched && !searching" class="py-8 text-center space-y-2 border border-dashed border-slate-200 dark:border-slate-700/80 rounded-2xl">
                <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 mx-auto flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16l2.879-2.879m0 0a3 3 0 104.243-4.242 3 3 0 00-4.243 4.242zM21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div class="text-xs font-semibold text-slate-700 dark:text-slate-300">Cari pelanggan untuk mulai pencatatan</div>
                <p class="text-[11px] text-slate-400 dark:text-slate-500">
                    Ketik nama atau CID pada kolom di atas, lalu tekan enter atau klik Cari.
                </p>
            </div>
        </div>
    </div>

    {{-- Batch Payment Queue / Keranjang Catat Pembayaran --}}
    <div x-show="rows.length > 0"
         x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-4"
         x-transition:enter-end="opacity-100 translate-y-0"
         class="bg-white dark:bg-slate-800/95 border-2 border-amber-300 dark:border-amber-500/40 rounded-3xl p-5 sm:p-6 shadow-md space-y-5">
        
        {{-- Queue Header --}}
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-700 pb-4">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-amber-500 text-slate-950 font-bold flex items-center justify-center text-sm shadow-xs">
                    <span x-text="rows.length"></span>
                </div>
                <div>
                    <h3 class="text-base font-bold text-slate-900 dark:text-slate-100">Daftar Tagihan Siap Dicatat</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Pastikan nominal, metode pembayaran, dan rincian transaksi sudah sesuai.</p>
                </div>
            </div>

            <button type="button"
                    @click="if(confirm('Kosongkan semua daftar pembayaran yang belum disimpan?')) { rows = []; }"
                    class="text-xs font-semibold text-rose-600 dark:text-rose-400 hover:text-rose-700 dark:hover:text-rose-300 px-3 py-1.5 rounded-xl hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-colors cursor-pointer">
                Kosongkan Daftar
            </button>
        </div>

        {{-- Row Items --}}
        <div class="space-y-3.5">
            <template x-for="(row, index) in rows" :key="row.invoice_id">
                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-900/70 border border-slate-200/80 dark:border-slate-700/70 space-y-3 transition-all">
                    {{-- Row Top: Customer info & delete --}}
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="w-6 h-6 rounded-lg bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-bold flex items-center justify-center shrink-0" x-text="index + 1"></span>
                            <div class="min-w-0">
                                <div class="font-bold text-sm text-slate-900 dark:text-slate-100 truncate" x-text="row.full_name"></div>
                                <div class="flex items-center gap-2 text-xs text-slate-500">
                                    <span class="font-mono text-[11px]" x-text="row.invoice_number"></span>
                                    <span>&bull;</span>
                                    <span>Sisa Tagihan: <strong class="font-mono text-slate-700 dark:text-slate-300" x-text="'Rp ' + format(row.remaining_amount)"></strong></span>
                                </div>
                            </div>
                        </div>

                        <button type="button"
                                @click="rows.splice(index, 1)"
                                class="text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 p-1.5 rounded-lg hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-colors cursor-pointer"
                                title="Hapus dari daftar">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </div>

                    {{-- Row Inputs Grid --}}
                    <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 pt-1">
                        {{-- Nominal Input --}}
                        <div class="sm:col-span-4 space-y-1">
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Nominal Bayar (Rp)</label>
                            <div class="relative">
                                <span class="absolute left-3 top-2.5 text-xs font-bold text-slate-400">Rp</span>
                                <input type="text"
                                       inputmode="numeric"
                                       x-model="row.amount_text"
                                       @input="row.amount_text = formatInput($event.target.value)"
                                       placeholder="0"
                                       class="w-full pl-9 pr-3 py-2 rounded-xl bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-sm font-mono font-bold text-slate-900 dark:text-slate-100 focus:ring-2 focus:ring-amber-500/30 focus:border-amber-500">
                            </div>
                            <div class="flex items-center gap-1.5 pt-0.5">
                                <button type="button"
                                        @click="row.amount_text = format(row.remaining_amount)"
                                        class="text-[10px] font-semibold text-amber-600 dark:text-amber-400 hover:underline">
                                    Set Lunas Penuh (Rp <span x-text="format(row.remaining_amount)"></span>)
                                </button>
                            </div>
                        </div>

                        {{-- Metode Pembayaran --}}
                        <div class="sm:col-span-4 space-y-1">
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Metode Pembayaran</label>
                            <select x-model="row.payment_method"
                                    class="w-full py-2 px-3 rounded-xl bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-sm text-slate-900 dark:text-slate-100 focus:ring-2 focus:ring-amber-500/30 focus:border-amber-500 font-medium">
                                <option value="cash">Tunai (Cash di Tangan)</option>
                                <option value="transfer">Transfer Bank</option>
                                <option value="lainnya">Lainnya</option>
                            </select>
                        </div>

                        {{-- Tanggal Terima --}}
                        <div class="sm:col-span-4 space-y-1">
                            <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Tanggal Diterima</label>
                            <input type="date"
                                   x-model="row.collected_date"
                                   :max="today"
                                   class="w-full py-2 px-3 rounded-xl bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-sm text-slate-900 dark:text-slate-100 focus:ring-2 focus:ring-amber-500/30 focus:border-amber-500">
                        </div>
                    </div>

                    {{-- Conditional Fields: Transfer or Lainnya --}}
                    <div x-show="row.payment_method === 'transfer'" x-cloak class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2 border-t border-slate-200/50 dark:border-slate-700/50">
                        <div class="space-y-1">
                            <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-300">Pilih Rekening Tujuan</label>
                            <select x-model="row.bank_account_id"
                                    class="w-full py-2 px-3 rounded-xl bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-xs text-slate-900 dark:text-slate-100 focus:ring-2 focus:ring-amber-500/30">
                                <option value="">-- Pilih Rekening Kasir / Bank --</option>
                                <template x-for="bank in bankAccounts" :key="bank.id">
                                    <option :value="bank.id" x-text="bank.name"></option>
                                </template>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-300">Nama Pengirim (Opsional)</label>
                            <input type="text"
                                   x-model="row.sender_name"
                                   placeholder="Nama pemilik rekening pengirim"
                                   maxlength="150"
                                   class="w-full py-2 px-3 rounded-xl bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-xs text-slate-900 dark:text-slate-100 focus:ring-2 focus:ring-amber-500/30">
                        </div>
                    </div>

                    <div x-show="row.payment_method === 'lainnya'" x-cloak class="pt-2 border-t border-slate-200/50 dark:border-slate-700/50 space-y-1">
                        <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-300">Keterangan Tambahan Metode</label>
                        <input type="text"
                               x-model="row.note"
                               placeholder="Contoh: QRIS statis warung, titip via RT, dll."
                               maxlength="255"
                               class="w-full py-2 px-3 rounded-xl bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-xs text-slate-900 dark:text-slate-100 focus:ring-2 focus:ring-amber-500/30">
                    </div>
                </div>
            </template>
        </div>

        {{-- Queue Footer Summary --}}
        <div class="pt-4 border-t border-slate-200 dark:border-slate-700 flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-slate-50 dark:bg-slate-900/50 p-4 rounded-2xl">
            <div>
                <div class="text-xs text-slate-500 dark:text-slate-400">Total Nominal Pembayaran:</div>
                <div class="text-xl sm:text-2xl font-bold font-mono text-emerald-600 dark:text-emerald-400" x-text="'Rp ' + format(total())"></div>
                <div class="text-[11px] text-slate-400" x-text="rows.length + ' tagihan akan diproses sekaligus'"></div>
            </div>

            <button type="button"
                    @click="submit()"
                    :disabled="submitting || rows.length === 0"
                    class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 disabled:opacity-50 text-white font-bold text-sm shadow-md transition-all active:scale-95 cursor-pointer">
                <svg x-show="submitting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
                <svg x-show="!submitting" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span x-text="submitting ? 'Menyimpan Pembayaran...' : 'Simpan Pembayaran'"></span>
            </button>
        </div>

        {{-- Result Messages & Failure Items --}}
        <div x-show="resultMessage" x-cloak class="p-4 rounded-2xl text-sm font-semibold flex items-start gap-2.5"
             :class="resultOk ? 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-500/30' : 'bg-rose-50 dark:bg-rose-500/10 text-rose-800 dark:text-rose-300 border border-rose-200 dark:border-rose-500/30'">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path x-show="resultOk" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                <path x-show="!resultOk" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <div class="space-y-1">
                <div x-text="resultMessage"></div>
                <ul class="text-xs list-disc pl-4 space-y-1 font-normal" x-show="failures.length > 0">
                    <template x-for="failure in failures" :key="failure.reason">
                        <li x-text="failure.reason"></li>
                    </template>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
    function technicianPayment(config) {
        return {
            query: '',
            searching: false,
            searched: false,
            searchError: '',
            customers: [],
            rows: [],
            submitting: false,
            resultMessage: '',
            resultOk: false,
            failures: [],
            today: config.today,
            idempotencyKey: config.idempotencyKey,
            bankAccounts: config.bankAccounts,

            format(value) {
                return Number(value || 0).toLocaleString('id-ID', { maximumFractionDigits: 0 });
            },

            formatInput(raw) {
                const digits = String(raw).replace(/\D/g, '');
                return digits === '' ? '' : Number(digits).toLocaleString('id-ID');
            },

            parseAmount(text) {
                return Number(String(text).replace(/\./g, '').replace(/,/g, '') || 0);
            },

            total() {
                return this.rows.reduce((sum, row) => sum + this.parseAmount(row.amount_text), 0);
            },

            isSelected(invoiceId) {
                return this.rows.some((row) => row.invoice_id === invoiceId);
            },

            hasUnselectedInvoices(customer) {
                return customer.invoices.some((inv) => !this.isSelected(inv.invoice_id));
            },

            addAllInvoices(customer) {
                customer.invoices.forEach((inv) => {
                    if (!this.isSelected(inv.invoice_id)) {
                        this.addInvoice(customer, inv);
                    }
                });
            },

            addInvoice(customer, invoice) {
                this.rows.push({
                    invoice_id: invoice.invoice_id,
                    invoice_number: invoice.invoice_number,
                    full_name: customer.full_name,
                    remaining_amount: invoice.remaining_amount,
                    amount_text: this.format(invoice.remaining_amount),
                    payment_method: 'cash',
                    bank_account_id: '',
                    sender_name: '',
                    collected_date: this.today,
                    note: '',
                });
            },

            async search() {
                this.searchError = '';
                this.resultMessage = '';
                if (this.query.trim().length < 3) {
                    this.searchError = 'Ketik minimal 3 karakter untuk mencari.';
                    return;
                }
                this.searching = true;
                try {
                    const response = await fetch(config.searchUrl + '?q=' + encodeURIComponent(this.query.trim()), {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin',
                    });
                    const data = await response.json();
                    if (!response.ok) {
                        this.searchError = data.message || 'Pencarian gagal dilakukan.';
                        return;
                    }
                    this.customers = data.customers || [];
                    this.searched = true;
                } catch (error) {
                    this.searchError = 'Koneksi bermasalah, silakan coba lagi.';
                } finally {
                    this.searching = false;
                }
            },

            async submit() {
                this.failures = [];
                this.resultMessage = '';
                if (this.rows.length === 0) {
                    return;
                }
                this.submitting = true;
                try {
                    const response = await fetch(config.storeUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            idempotency_key: this.idempotencyKey,
                            rows: this.rows.map((row) => ({
                                invoice_id: row.invoice_id,
                                amount: this.parseAmount(row.amount_text),
                                payment_method: row.payment_method,
                                bank_account_id: row.payment_method === 'transfer' && row.bank_account_id ? parseInt(row.bank_account_id, 10) : null,
                                sender_name: row.payment_method === 'transfer' ? (row.sender_name || '').trim() : '',
                                collected_date: row.collected_date,
                                note: row.note || '',
                            })),
                        }),
                    });
                    const data = await response.json();
                    this.resultOk = response.ok && data.success;
                    this.resultMessage = data.message || (this.resultOk ? 'Pembayaran berhasil disimpan.' : 'Gagal menyimpan pembayaran.');
                    this.failures = data.failures || [];
                    if (this.resultOk) {
                        // Sukses: Refresh halaman agar saldo & kunci idempotensi baru terbit.
                        window.location.reload();
                    }
                } catch (error) {
                    this.resultOk = false;
                    this.resultMessage = 'Koneksi bermasalah. Coba kirim lagi — pembayaran yang sudah tercatat tidak akan dobel.';
                } finally {
                    this.submitting = false;
                }
            },
        };
    }
</script>
@endsection

