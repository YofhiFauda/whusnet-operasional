{{--
    Sel angka Laporan Bulanan Admin Collector yang bisa diklik untuk melihat
    rincian baris di baliknya ("siapa saja yang sudah membayar", dst.) — view-only,
    jadi modal (pola 1 CLAUDE.md), bukan halaman/pindah baris.

    $clickable ditentukan pemanggil dari CollectorMonthlyReportService::detailableColumns()
    — kolom komposit (Total Pembayaran, persentase, Sisa Piutang) tidak
    diberi tombol karena bukan hasil satu query tunggal.
--}}
@props(['value', 'block', 'column', 'popId', 'label', 'clickable' => true, 'cls' => ''])

<td class="{{ $cls }}">
    @if($clickable)
        <button type="button"
                onclick="openReportDetail({{ (int) $popId }}, '{{ $block }}', '{{ $column }}', {{ \Illuminate\Support\Js::from($label) }})"
                class="underline decoration-dotted decoration-slate-400 dark:decoration-slate-500 underline-offset-2 cursor-pointer hover:text-sky-600 dark:hover:text-sky-400">
            {{ $value }}
        </button>
    @else
        {{ $value }}
    @endif
</td>
