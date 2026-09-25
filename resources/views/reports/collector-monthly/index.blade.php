@extends('layouts.app')

@section('title', 'Laporan Bulanan Admin - Whusnet Operasional')
@section('page_title', 'Laporan Bulanan Admin')

@section('content')
@php
    use App\Services\CollectorMonthlyReportService as Report;

    $rp = fn ($v) => number_format((float) $v, 0, ',', '.');
    $pct = fn ($bagian, $total) => number_format(Report::percentage($bagian, $total), 1, ',', '.').'%';

    $card = 'bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-sm';
    $th = 'px-3 py-2 text-xs font-bold text-center text-slate-900 bg-[#8ea9db] border border-slate-400/60 whitespace-nowrap';
    $td = 'px-3 py-1.5 text-sm text-right font-mono border border-slate-300 dark:border-slate-600 whitespace-nowrap';
    $tdText = 'px-3 py-1.5 text-sm text-left border border-slate-300 dark:border-slate-600 whitespace-nowrap';
    $tdNo = 'px-3 py-1.5 text-sm text-center border border-slate-300 dark:border-slate-600';
    $tf = 'px-3 py-2 text-sm text-right font-mono font-bold bg-[#c6e0b4] text-slate-900 border border-slate-400/60 whitespace-nowrap';
    $tfText = 'px-3 py-2 text-sm text-left font-bold bg-[#c6e0b4] text-slate-900 border border-slate-400/60';

    $anyDrift = collect($rows)->contains('drift', true);
    $can = fn (string $block, string $column) => in_array($column, $detailable[$block] ?? [], true);
@endphp

<div class="space-y-6">
    {{-- Filter --}}
    <div class="{{ $card }} p-5">
        <form method="GET" action="{{ route('reports.collector-monthly.index') }}" class="flex flex-col md:flex-row md:items-end gap-4">
            <div>
                <label for="period" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Periode</label>
                <input type="month" id="period" name="period" value="{{ $period }}" class="rounded-md border-slate-300 dark:border-slate-600 text-sm">
            </div>
            <div>
                <label for="pop_id" class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">POP / Cabang</label>
                <select id="pop_id" name="pop_id" class="rounded-md border-slate-300 dark:border-slate-600 text-sm">
                    <option value="">Semua POP Akses</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((int) $popId === (int) $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="px-4 py-2 text-sm font-semibold rounded-md bg-sky-600 hover:bg-sky-700 text-white transition-colors">Tampilkan</button>
                @if($canExport)
                    <a href="{{ route('reports.collector-monthly.export', array_filter(['period' => $period, 'pop_id' => $popId])) }}" class="px-4 py-2 text-sm font-semibold rounded-md border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">Export Excel</a>
                @endif
            </div>
        </form>
    </div>

    {{-- Status pembukuan periode --}}
    <div class="{{ $card }} p-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-3">
            <div>
                <h2 class="text-sm font-bold text-slate-800 dark:text-slate-100">Pembukuan {{ $periodLabel }}</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Tutup buku otomatis saat bulan berganti dan terkunci permanen — pembayaran piutang masuk ke bulan uang diterima, tidak menggeser laporan bulan ini.
                </p>
            </div>
            @if($locked)
                <span class="text-xs font-semibold px-3 py-1.5 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200">Terkunci</span>
            @else
                <span class="text-xs font-semibold px-3 py-1.5 rounded-full bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800/50">Periode berjalan — ditutup otomatis tanggal 1 bulan depan</span>
            @endif
        </div>

        @if($anyDrift)
            <div class="mb-3 text-xs rounded-md border border-amber-300 bg-amber-50 dark:bg-amber-900/20 dark:border-amber-800/50 text-amber-800 dark:text-amber-300 p-3">
                Ada transaksi bertanggal periode ini yang tercatat <strong>setelah</strong> ditutup (mis. impor legacy). Pengembalian pembayaran tidak memicu tanda ini — dibukukan di bulan pengembaliannya. Angka yang tampil tetap snapshot; hitung ulang live berbeda untuk POP bertanda ⚠.
            </div>
        @endif

        <ul class="divide-y divide-slate-100 dark:divide-slate-700">
            @forelse($rows as $row)
                <li class="py-2 flex flex-wrap items-center gap-3 text-sm">
                    <span class="font-semibold text-slate-800 dark:text-slate-100 w-40">{{ $row['pop']->name }}</span>
                    @if($row['closing'])
                        <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200">
                            Dibekukan {{ $row['closing']->closed_at->format('d/m/Y H:i') }}@if($row['drift']) ⚠@endif
                        </span>
                    @elseif($locked)
                        {{-- Terkunci tapi snapshot belum ada (bulan sebelum fitur ini, atau scheduler belum jalan). --}}
                        <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200">Terkunci (angka live)</span>
                    @else
                        <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/50">Terbuka (angka live)</span>
                    @endif
                </li>
            @empty
                <li class="py-4 text-sm text-slate-500">Tidak ada POP dalam akses Anda.</li>
            @endforelse
        </ul>
        @error('period')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>

    {{-- 1. Tagihan --}}
    <div class="{{ $card }} p-5">
        <h3 class="text-sm font-bold uppercase text-slate-800 dark:text-slate-100 mb-2">Tagihan {{ $periodLabel }}</h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 mb-2">Angka bergaris putus-putus bisa diklik untuk melihat rincian pelanggannya.</p>
        <div class="overflow-x-auto">
            <table class="w-full border-collapse">
                <thead>
                    <tr>
                        @foreach(['No', 'OLT', 'Tagihan Terbit', 'Dimuka', 'Diskon', 'Bulanan', 'Total Pembayaran', 'Piutang', 'Pendapatan %', 'Piutang %'] as $h)
                            <th class="{{ $th }}">{{ $h }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $i => $row)
                        @php $t = $row['figures']['tagihan']; $pid = $row['pop']->id; @endphp
                        <tr>
                            <td class="{{ $tdNo }}">{{ $i + 1 }}</td>
                            <td class="{{ $tdText }}">{{ $row['pop']->name }}</td>
                            <x-reports.detail-cell :cls="$td" :value="$rp($t['tagihan_terbit'])" block="tagihan" column="tagihan_terbit" :pop-id="$pid" label="Tagihan Terbit — {{ $row['pop']->name }}" :clickable="$can('tagihan', 'tagihan_terbit')" />
                            <x-reports.detail-cell :cls="$td" :value="$rp($t['dimuka'])" block="tagihan" column="dimuka" :pop-id="$pid" label="Dimuka (saldo) — {{ $row['pop']->name }}" :clickable="$can('tagihan', 'dimuka')" />
                            <x-reports.detail-cell :cls="$td" :value="$rp($t['diskon'])" block="tagihan" column="diskon" :pop-id="$pid" label="Diskon — {{ $row['pop']->name }}" :clickable="$can('tagihan', 'diskon')" />
                            <x-reports.detail-cell :cls="$td" :value="$rp($t['bulanan'])" block="tagihan" column="bulanan" :pop-id="$pid" label="Bulanan (bayar tunai) — {{ $row['pop']->name }}" :clickable="$can('tagihan', 'bulanan')" />
                            <td class="{{ $td }}">{{ $rp($t['total_pembayaran']) }}</td>
                            <x-reports.detail-cell :cls="$td" :value="$rp($t['piutang'])" block="tagihan" column="piutang" :pop-id="$pid" label="Piutang bulan ini — {{ $row['pop']->name }}" :clickable="$can('tagihan', 'piutang')" />
                            <td class="{{ $td }}">{{ $pct($t['total_pembayaran'], $t['tagihan_terbit']) }}</td>
                            <td class="{{ $td }}">{{ $pct($t['piutang'], $t['tagihan_terbit']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                @php $t = $totals['tagihan']; @endphp
                <tfoot>
                    <tr>
                        <td class="{{ $tfText }}"></td>
                        <td class="{{ $tfText }}">Total</td>
                        <td class="{{ $tf }}">{{ $rp($t['tagihan_terbit']) }}</td>
                        <td class="{{ $tf }}">{{ $rp($t['dimuka']) }}</td>
                        <td class="{{ $tf }}">{{ $rp($t['diskon']) }}</td>
                        <td class="{{ $tf }}">{{ $rp($t['bulanan']) }}</td>
                        <td class="{{ $tf }}">{{ $rp($t['total_pembayaran']) }}</td>
                        <td class="{{ $tf }}">{{ $rp($t['piutang']) }}</td>
                        <td class="{{ $tf }}">{{ $pct($t['total_pembayaran'], $t['tagihan_terbit']) }}</td>
                        <td class="{{ $tf }}">{{ $pct($t['piutang'], $t['tagihan_terbit']) }}</td>
                    </tr>
                    <tr>
                        <td class="{{ $tfText }} bg-[#e2efda]"></td>
                        <td class="{{ $tfText }} bg-[#e2efda]">Piutang {{ $previousLabel }}</td>
                        <td class="{{ $tf }} bg-[#e2efda]">{{ $rp($totals['piutang_lalu']['belum_dibayar']) }}</td>
                        <td class="{{ $tf }} bg-[#e2efda]" colspan="7"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    {{-- 2. Piutang bulan lalu --}}
    <div class="{{ $card }} p-5">
        <h3 class="text-sm font-bold uppercase text-slate-800 dark:text-slate-100 mb-2">Piutang Bulan Lalu (s.d. {{ $previousLabel }})</h3>
        <div class="overflow-x-auto">
            <table class="w-full border-collapse">
                <thead>
                    <tr>
                        @foreach(['No', 'OLT', 'Piutang Bulan Lalu', 'Sudah Dibayar', 'Belum Dibayar', 'Sudah Dibayar %', 'Belum Dibayar %', 'Piutang tak Tertagih', 'Sisa Piutang'] as $h)
                            <th class="{{ $th }}">{{ $h }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $i => $row)
                        @php $p = $row['figures']['piutang_lalu']; $pid = $row['pop']->id; @endphp
                        <tr>
                            <td class="{{ $tdNo }}">{{ $i + 1 }}</td>
                            <td class="{{ $tdText }}">{{ $row['pop']->name }}</td>
                            <x-reports.detail-cell :cls="$td" :value="$rp($p['pembuka'])" block="piutang_lalu" column="pembuka" :pop-id="$pid" label="Piutang bulan lalu (pembuka) — {{ $row['pop']->name }}" :clickable="$can('piutang_lalu', 'pembuka')" />
                            <x-reports.detail-cell :cls="$td" :value="$rp($p['sudah_dibayar'])" block="piutang_lalu" column="sudah_dibayar" :pop-id="$pid" label="Piutang lalu — sudah dibayar bulan ini — {{ $row['pop']->name }}" :clickable="$can('piutang_lalu', 'sudah_dibayar')" />
                            <x-reports.detail-cell :cls="$td" :value="$rp($p['belum_dibayar'])" block="piutang_lalu" column="belum_dibayar" :pop-id="$pid" label="Piutang lalu — belum dibayar — {{ $row['pop']->name }}" :clickable="$can('piutang_lalu', 'belum_dibayar')" />
                            <td class="{{ $td }}">{{ $pct($p['sudah_dibayar'], $p['pembuka']) }}</td>
                            <td class="{{ $td }}">{{ $pct($p['belum_dibayar'], $p['pembuka']) }}</td>
                            <x-reports.detail-cell :cls="$td" :value="$rp($p['tak_tertagih'])" block="piutang_lalu" column="tak_tertagih" :pop-id="$pid" label="Piutang tak tertagih (hapus buku) — {{ $row['pop']->name }}" :clickable="$can('piutang_lalu', 'tak_tertagih')" />
                            <td class="{{ $td }}">{{ $rp(max(0, $p['belum_dibayar'] - $p['tak_tertagih'])) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                @php $p = $totals['piutang_lalu']; @endphp
                <tfoot>
                    <tr>
                        <td class="{{ $tfText }}"></td>
                        <td class="{{ $tfText }}">Total</td>
                        <td class="{{ $tf }}">{{ $rp($p['pembuka']) }}</td>
                        <td class="{{ $tf }}">{{ $rp($p['sudah_dibayar']) }}</td>
                        <td class="{{ $tf }}">{{ $rp($p['belum_dibayar']) }}</td>
                        <td class="{{ $tf }}">{{ $pct($p['sudah_dibayar'], $p['pembuka']) }}</td>
                        <td class="{{ $tf }}">{{ $pct($p['belum_dibayar'], $p['pembuka']) }}</td>
                        <td class="{{ $tf }}">{{ $rp($p['tak_tertagih']) }}</td>
                        <td class="{{ $tf }}">{{ $rp(max(0, $p['belum_dibayar'] - $p['tak_tertagih'])) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Piutang tak tertagih = tagihan yang dihapus buku pada bulan ini (tombol "Hapus Buku" di detail tagihan).</p>
    </div>

    {{-- 3. Pelanggan --}}
    <div class="{{ $card }} p-5">
        <h3 class="text-sm font-bold uppercase text-slate-800 dark:text-slate-100 mb-2">Pelanggan ({{ $periodLabel }})</h3>
        <div class="overflow-x-auto">
            <table class="w-full border-collapse">
                <thead>
                    <tr>
                        @foreach(['No', 'OLT', 'Total Pelanggan', 'Bayar Dimuka', 'Sudah Bayar', 'Belum Bayar'] as $h)
                            <th class="{{ $th }}">{{ $h }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $i => $row)
                        @php $c = $row['figures']['pelanggan']; $pid = $row['pop']->id; @endphp
                        <tr>
                            <td class="{{ $tdNo }}">{{ $i + 1 }}</td>
                            <td class="{{ $tdText }}">{{ $row['pop']->name }}</td>
                            <x-reports.detail-cell :cls="$td" :value="$rp($c['total'])" block="pelanggan" column="total" :pop-id="$pid" label="Pelanggan tagihan bulan ini — {{ $row['pop']->name }}" :clickable="$can('pelanggan', 'total')" />
                            <x-reports.detail-cell :cls="$td" :value="$rp($c['dimuka'])" block="pelanggan" column="dimuka" :pop-id="$pid" label="Pelanggan lunas dari saldo (dimuka) — {{ $row['pop']->name }}" :clickable="$can('pelanggan', 'dimuka')" />
                            <x-reports.detail-cell :cls="$td" :value="$rp($c['sudah_bayar'])" block="pelanggan" column="sudah_bayar" :pop-id="$pid" label="Pelanggan sudah bayar — {{ $row['pop']->name }}" :clickable="$can('pelanggan', 'sudah_bayar')" />
                            <x-reports.detail-cell :cls="$td" :value="$rp($c['belum_bayar'])" block="pelanggan" column="belum_bayar" :pop-id="$pid" label="Pelanggan belum bayar — {{ $row['pop']->name }}" :clickable="$can('pelanggan', 'belum_bayar')" />
                        </tr>
                    @endforeach
                </tbody>
                @php $c = $totals['pelanggan']; @endphp
                <tfoot>
                    <tr>
                        <td class="{{ $tfText }}"></td>
                        <td class="{{ $tfText }}">Total</td>
                        <td class="{{ $tf }}">{{ $rp($c['total']) }}</td>
                        <td class="{{ $tf }}">{{ $rp($c['dimuka']) }}</td>
                        <td class="{{ $tf }}">{{ $rp($c['sudah_bayar']) }}</td>
                        <td class="{{ $tf }}">{{ $rp($c['belum_bayar']) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    {{-- 4. Uang diterima --}}
    <div class="{{ $card }} p-5">
        <h3 class="text-sm font-bold uppercase text-slate-800 dark:text-slate-100 mb-2">Uang Diterima {{ $periodLabel }}</h3>
        <div class="overflow-x-auto">
            <table class="w-full border-collapse">
                <thead>
                    <tr>
                        @foreach(['No', 'OLT', 'Bulanan', 'Piutang', 'Lebih Bayar', 'Aktivasi', 'Lainnya', 'Dikembalikan', 'Total Uang Diterima'] as $h)
                            <th class="{{ $th }}">{{ $h }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $i => $row)
                        @php $u = $row['figures']['uang_diterima']; $pid = $row['pop']->id; @endphp
                        <tr>
                            <td class="{{ $tdNo }}">{{ $i + 1 }}</td>
                            <td class="{{ $tdText }}">{{ $row['pop']->name }}</td>
                            <x-reports.detail-cell :cls="$td" :value="$rp($u['bulanan'])" block="uang_diterima" column="bulanan" :pop-id="$pid" label="Uang diterima — bulanan — {{ $row['pop']->name }}" :clickable="$can('uang_diterima', 'bulanan')" />
                            <x-reports.detail-cell :cls="$td" :value="$rp($u['piutang'])" block="uang_diterima" column="piutang" :pop-id="$pid" label="Uang diterima — piutang lama — {{ $row['pop']->name }}" :clickable="$can('uang_diterima', 'piutang')" />
                            <x-reports.detail-cell :cls="$td" :value="$rp($u['lebih_bayar'])" block="uang_diterima" column="lebih_bayar" :pop-id="$pid" label="Uang diterima — lebih bayar — {{ $row['pop']->name }}" :clickable="$can('uang_diterima', 'lebih_bayar')" />
                            <x-reports.detail-cell :cls="$td" :value="$rp($u['aktivasi'])" block="uang_diterima" column="aktivasi" :pop-id="$pid" label="Uang diterima — aktivasi — {{ $row['pop']->name }}" :clickable="$can('uang_diterima', 'aktivasi')" />
                            <x-reports.detail-cell :cls="$td" :value="$rp($u['lainnya'])" block="uang_diterima" column="lainnya" :pop-id="$pid" label="Uang diterima — lainnya — {{ $row['pop']->name }}" :clickable="$can('uang_diterima', 'lainnya')" />
                            {{-- Pengurang: pembayaran bulan terkunci yang dikembalikan bulan ini. Snapshot lama tak punya kuncinya. --}}
                            <x-reports.detail-cell :cls="$td" :value="$rp(-($u['dikembalikan'] ?? 0))" block="uang_diterima" column="dikembalikan" :pop-id="$pid" label="Uang diterima — dikembalikan — {{ $row['pop']->name }}" :clickable="$can('uang_diterima', 'dikembalikan')" />
                            <x-reports.detail-cell :cls="$td" :value="$rp($u['total'])" block="uang_diterima" column="total" :pop-id="$pid" label="Uang diterima — total — {{ $row['pop']->name }}" :clickable="$can('uang_diterima', 'total')" />
                        </tr>
                    @endforeach
                </tbody>
                @php $u = $totals['uang_diterima']; @endphp
                <tfoot>
                    <tr>
                        <td class="{{ $tfText }}"></td>
                        <td class="{{ $tfText }}">Jumlah</td>
                        <td class="{{ $tf }}">{{ $rp($u['bulanan']) }}</td>
                        <td class="{{ $tf }}">{{ $rp($u['piutang']) }}</td>
                        <td class="{{ $tf }}">{{ $rp($u['lebih_bayar']) }}</td>
                        <td class="{{ $tf }}">{{ $rp($u['aktivasi']) }}</td>
                        <td class="{{ $tf }}">{{ $rp($u['lainnya']) }}</td>
                        <td class="{{ $tf }}">{{ $rp(-($u['dikembalikan'] ?? 0)) }}</td>
                        <td class="{{ $tf }}">{{ $rp($u['total']) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

{{-- Modal rincian per sel (drill-down) — SATU modal dipakai gantian oleh
     semua sel yang bisa diklik di 4 tabel di atas, sama polanya dengan
     Alpine.store('qrPeek') di verifications/queue.blade.php. Selalu
     mengambil data LIVE (lihat docblock CollectorMonthlyReportService::detail()). --}}
<x-ui.modal name="report-detail" title="Rincian Laporan" maxWidth="2xl">
    <div class="space-y-3">
        <div class="flex items-center justify-between gap-3">
            <h4 class="font-bold text-sm text-text-main" x-text="$store.reportDetail.label"></h4>
            <span class="text-xs text-text-muted shrink-0" x-text="$store.reportDetail.count + ' baris'"></span>
        </div>

        <div class="flex items-center justify-between gap-3 text-xs font-bold pb-2 border-b border-border">
            <span>Total <span x-text="'Rp ' + Number($store.reportDetail.total).toLocaleString('id-ID')"></span></span>
            @if($canExport)
                {{-- Daftar tagih/kejar untuk audit — bukan sekadar dilihat di modal.
                     href dirakit dari param yang store SIMPAN saat modal dibuka
                     (bukan input bebas), sama seperti url fetch di atas. --}}
                <a :href="$store.reportDetail.exportUrl()" x-show="$store.reportDetail.rows.length > 0"
                   class="px-3 py-1.5 rounded-md border border-border text-text-secondary hover:bg-surface-muted font-semibold cursor-pointer">
                    Unduh Excel
                </a>
            @endif
        </div>

        <template x-if="$store.reportDetail.fromClosedPeriod">
            <p class="text-xs rounded-md border border-amber-300 dark:border-amber-800/50 bg-amber-50 dark:bg-amber-900/20 text-amber-800 dark:text-amber-300 p-2.5">
                Periode ini sudah ditutup — tabel laporan menampilkan angka beku (snapshot). Rincian di bawah selalu dari data terkini dan bisa sedikit berbeda kalau ada transaksi susulan.
            </p>
        </template>

        <template x-if="$store.reportDetail.error">
            <p class="text-xs rounded-md border border-red-300 dark:border-red-800/50 bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 p-2.5" x-text="$store.reportDetail.error"></p>
        </template>

        <div class="relative">
            <svg class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
            <input type="text" x-model="$store.reportDetail.search" placeholder="Cari pelanggan, akun, atau referensi..."
                   class="w-full pl-9 pr-3 py-2 rounded-md border border-slate-300 dark:border-slate-600 text-sm text-text-main placeholder-slate-400 bg-slate-50 dark:bg-slate-900 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-sky-500">
        </div>

        <div class="max-h-[50vh] overflow-y-auto border border-border rounded-md">
            <table class="w-full text-xs border-collapse">
                <thead class="bg-surface-muted sticky top-0">
                    <tr>
                        <th class="text-left p-2 font-bold">Pelanggan</th>
                        <th class="text-left p-2 font-bold">Akun</th>
                        <th class="text-left p-2 font-bold">Referensi</th>
                        <th class="text-left p-2 font-bold">Tanggal</th>
                        <th class="text-right p-2 font-bold">Nominal</th>
                        <th class="text-left p-2 font-bold">Jenis</th>
                        <th class="text-left p-2 font-bold">Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-if="$store.reportDetail.loading">
                        <tr><td colspan="7" class="p-4 text-center text-text-muted">Memuat…</td></tr>
                    </template>
                    <template x-if="!$store.reportDetail.loading && $store.reportDetail.filteredRows().length === 0">
                        <tr><td colspan="7" class="p-4 text-center text-text-muted">Tidak ada baris untuk sel ini.</td></tr>
                    </template>
                    <template x-for="(row, idx) in $store.reportDetail.filteredRows()" :key="idx">
                        <tr class="border-t border-border">
                            <td class="p-2" x-text="row.pelanggan"></td>
                            <td class="p-2 font-mono" x-text="row.akun"></td>
                            <td class="p-2 font-mono" x-text="row.referensi"></td>
                            <td class="p-2 whitespace-nowrap" x-text="row.tanggal ? row.tanggal.substring(0, 10) : '-'"></td>
                            <td class="p-2 text-right font-mono" x-text="'Rp ' + Number(row.nominal).toLocaleString('id-ID')"></td>
                            <td class="p-2">
                                <div class="flex flex-wrap gap-1">
                                    <template x-for="(label, lIdx) in (row.jenis || [])" :key="lIdx">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold" :class="label.badge_class" x-text="label.label"></span>
                                    </template>
                                </div>
                            </td>
                            <td class="p-2" x-text="row.keterangan"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </div>
</x-ui.modal>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.store('reportDetail', {
            loading: false,
            error: null,
            label: '',
            search: '',
            rows: [],
            total: 0,
            count: 0,
            fromClosedPeriod: false,
            lastPopId: null,
            lastBlock: null,
            lastColumn: null,

            async open(popId, block, column, label) {
                this.label = label;
                this.search = '';
                this.rows = [];
                this.total = 0;
                this.count = 0;
                this.error = null;
                this.loading = true;
                this.lastPopId = popId;
                this.lastBlock = block;
                this.lastColumn = column;
                window.dispatchEvent(new CustomEvent('open-modal', { detail: 'report-detail' }));

                try {
                    // Query string ditambahkan di sini dari popId/block/column yang
                    // SUDAH ditentukan server (data-* tombol, bukan input bebas
                    // pengguna) — endpoint GET filter, pola sama filter reports lain.
                    const url = `{{ $detailUrl }}?period={{ $period }}&pop_id=${popId}&block=${encodeURIComponent(block)}&column=${encodeURIComponent(column)}`;
                    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });

                    if (!res.ok) {
                        this.error = 'Gagal memuat rincian (HTTP ' + res.status + ').';

                        return;
                    }

                    const data = await res.json();
                    this.rows = data.rows;
                    this.total = data.total;
                    this.count = data.count;
                    this.fromClosedPeriod = data.from_closed_period;
                } catch (e) {
                    this.error = 'Gagal memuat rincian — cek koneksi.';
                } finally {
                    this.loading = false;
                }
            },

            filteredRows() {
                if (!this.search) {
                    return this.rows;
                }

                const q = this.search.toLowerCase();

                return this.rows.filter((r) => `${r.pelanggan} ${r.akun} ${r.referensi} ${r.keterangan}`.toLowerCase().includes(q));
            },

            exportUrl() {
                if (this.lastPopId === null) {
                    return '#';
                }

                return `{{ $detailExportUrl }}?period={{ $period }}&pop_id=${this.lastPopId}&block=${encodeURIComponent(this.lastBlock)}&column=${encodeURIComponent(this.lastColumn)}`;
            },
        });
    });

    function openReportDetail(popId, block, column, label) {
        Alpine.store('reportDetail').open(popId, block, column, label);
    }
</script>
@endsection
