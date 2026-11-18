{{-- Pemilih jumlah baris per halaman (pasangan App\Support\PerPage).

     Submit ke url()->current() + bawa ulang semua query string kecuali
     per_page & page, supaya filter/sort/tab yang sedang aktif tidak hilang
     dan user kembali ke halaman 1 (halaman lama bisa jadi di luar jangkauan). --}}
@props(['paginator'])
<form method="GET" action="{{ url()->current() }}" class="flex items-center gap-1.5 text-xs text-slate-600 dark:text-slate-400">
    @foreach(request()->except(['per_page', 'page']) as $qk => $qv)
        @if(is_array($qv))
            @foreach($qv as $qvItem)
                <input type="hidden" name="{{ $qk }}[]" value="{{ $qvItem }}">
            @endforeach
        @else
            <input type="hidden" name="{{ $qk }}" value="{{ $qv }}">
        @endif
    @endforeach
    <span aria-hidden="true">Baris</span>
    <select name="per_page" onchange="this.form.submit()" aria-label="Jumlah baris per halaman"
            class="h-8 pl-2 pr-7 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 text-xs font-mono focus:outline-none focus:border-sky-600">
        @foreach(\App\Support\PerPage::OPTIONS as $option)
            <option value="{{ $option }}" @selected($paginator->perPage() === $option)>{{ $option }}</option>
        @endforeach
    </select>
</form>
