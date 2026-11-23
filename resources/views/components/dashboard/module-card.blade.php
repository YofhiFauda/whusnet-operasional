{{--
    Kartu ringkas modul Dashboard Owner Fase 2 (ADHOC-313). Satu kartu =
    beberapa angka + SATU link drilldown ke halaman modulnya — sengaja tanpa
    tabel detail (prinsip "ringkasan + link", docs/plan/analisa-dashboard-owner-fase-2.md §2).
    Baris dipisah divider 1px, bukan kotak per baris (Design.md §1.5).
--}}
@props(['title', 'subtitle' => null, 'href' => null, 'linkLabel' => 'Detail', 'accent' => null])

<x-dashboard.panel :title="$title" :subtitle="$subtitle" :href="$href" :linkLabel="$linkLabel" :accent="$accent" {{ $attributes }}>
    <dl class="divide-y divide-border text-[13px] -my-2">
        {{ $slot }}
    </dl>
</x-dashboard.panel>
