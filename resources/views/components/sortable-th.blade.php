@props([
    'sortKey' => null,
    'label' => '',
    'currentSort' => null,
    'currentDir' => 'asc',
    'align' => 'left',
])
{{--
    Header kolom tabel yang bisa diklik buat sort ASC/DESC, konsisten sama pola
    yang sudah dipakai List Pelanggan (`customers/partials/_list_table.blade.php`).
    Klik 1: kolom baru → ASC. Klik 2 (kolom yang sama, lagi ASC) → DESC.
    Klik 3 (lagi DESC) → balik ke urutan default (sort/dir dibuang dari query).
    URL dirender server (`fullUrlWithQuery`) supaya filter/pencarian lain ikut
    terbawa, konsisten sama CLAUDE.md ("target aksi dirender server-side").
    `sortKey` null/kosong = header biasa, gak ada link.
--}}
@php
    $alignClass = match ($align) { 'right' => 'text-right', 'center' => 'text-center', default => '' };
    $isSorted = $sortKey && $currentSort === $sortKey;
    $nextQuery = ! $isSorted
        ? ['sort' => $sortKey, 'dir' => 'asc', 'page' => null]
        : ($currentDir === 'asc'
            ? ['sort' => $sortKey, 'dir' => 'desc', 'page' => null]
            : ['sort' => null, 'dir' => null, 'page' => null]);
@endphp
<th {{ $attributes->merge(['scope' => 'col', 'class' => "py-3 px-3 whitespace-nowrap select-none {$alignClass}"]) }}>
    @if($sortKey)
        <a href="{{ request()->fullUrlWithQuery($nextQuery) }}"
           class="inline-flex items-center gap-1 hover:text-sky-600 dark:hover:text-sky-400 {{ $isSorted ? 'text-sky-600 dark:text-sky-400' : '' }}"
           title="Klik untuk urutkan"
           aria-label="Urutkan kolom {{ $label }}">
            <span>{{ $label }}</span>
            <span class="text-[9px]" aria-hidden="true">{{ $isSorted ? ($currentDir === 'desc' ? '▼' : '▲') : '' }}</span>
        </a>
    @else
        {{ $label }}
    @endif
</th>
