{{--
    Komponen: Patokan Estimasi Survey
    =================================
    Menampilkan estimasi barang dari Laporan Survey di atas seksi realisasi
    yang sesuai (SN Perangkat Aktif / Roll Kabel). Ini HANYA referensi — tidak
    ikut tersimpan. Realisasi tetap dipilih teknisi dari custody-nya.

    Props:
      $rows  (Collection<TaskMaterial>) — baris estimasi, sudah difilter ke
                                          jenis barang yang tepat oleh controller.
      $title (string)                    — label patokan.
--}}

@props([
    'rows',
    'title' => 'Estimasi Survey',
])

@if($rows->isNotEmpty())
    <div class="rounded-lg border border-sky-200 dark:border-sky-800/60 bg-sky-50/60 dark:bg-sky-950/30 px-3 py-2 text-[11px] text-slate-700 dark:text-slate-300 space-y-1">
        <p class="font-semibold text-sky-800 dark:text-sky-300">{{ $title }} <span class="font-normal text-slate-500 dark:text-slate-400">(patokan)</span></p>
        <ul class="flex flex-wrap gap-1.5">
            @foreach($rows as $row)
                <li class="inline-flex items-center gap-1 rounded-md bg-white dark:bg-slate-900 border border-sky-100 dark:border-sky-900/60 px-2 py-0.5">
                    <span class="font-semibold">{{ $row->item_name }}</span>
                    <span class="font-mono">{{ rtrim(rtrim(number_format((float) $row->qty, 2, ',', '.'), '0'), ',') }} {{ $row->unit }}</span>
                </li>
            @endforeach
        </ul>
        <p class="text-slate-500 dark:text-slate-400">Estimasi per kategori dari survey. Realisasi dipilih dari custody; patokan ini tidak otomatis tersimpan.</p>
    </div>
@endif
