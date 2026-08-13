{{-- Footer pagination + pemilih baris/halaman.

     Form "Baris" submit ke url()->current(), BUKAN /customers hardcoded seperti
     dulu: dari halaman Putus/Gagal, mengubah jumlah baris melempar user balik ke
     List Pelanggan dan konteks grupnya hilang. --}}
@php
    $cur = $customers->currentPage();
    $last = $customers->lastPage();
    $winStart = max(1, $cur - 2);
    $winEnd = min($last, $cur + 2);
    $btnBase = 'px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/60 text-xs font-semibold transition-colors';
    $btnDisabled = 'px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-400 dark:text-slate-600 text-xs font-semibold opacity-40 cursor-not-allowed';
    $btnActive = 'px-3 py-1.5 rounded-lg bg-sky-600 text-white font-semibold text-xs font-mono';
@endphp
<div class="p-4 border-t border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4 text-xs text-slate-500">

    <div class="flex items-center gap-4">
        <div>
            @if($customers->total() > 0)
                Menampilkan
                <strong class="text-slate-800 dark:text-white font-mono">{{ number_format($customers->firstItem(), 0, ',', '.') }}&ndash;{{ number_format($customers->lastItem(), 0, ',', '.') }}</strong>
                dari
                <strong class="text-slate-800 dark:text-white font-mono">{{ number_format($customers->total(), 0, ',', '.') }}</strong>
                pelanggan
            @else
                Tidak ada data
            @endif
        </div>

        <form method="GET" action="{{ url()->current() }}" class="flex items-center gap-1.5">
            @foreach(request()->except(['per_page', 'page']) as $qk => $qv)
                @if(is_array($qv))
                    @foreach($qv as $qvItem)
                        <input type="hidden" name="{{ $qk }}[]" value="{{ $qvItem }}">
                    @endforeach
                @else
                    <input type="hidden" name="{{ $qk }}" value="{{ $qv }}">
                @endif
            @endforeach
            <span class="hidden sm:inline">Baris</span>
            <select name="per_page" onchange="this.form.submit()"
                    class="h-8 pl-2 pr-7 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 text-xs font-mono focus:outline-none focus:border-sky-600">
                @foreach([10, 25, 50, 100] as $ppOption)
                    <option value="{{ $ppOption }}" {{ (int) request('per_page', 10) === $ppOption ? 'selected' : '' }}>{{ $ppOption }}</option>
                @endforeach
            </select>
        </form>
    </div>

    @if($last > 1)
    <div class="flex items-center gap-1.5">
        @if($customers->onFirstPage())
            <span class="{{ $btnDisabled }}">Prev</span>
        @else
            <a href="{{ $customers->previousPageUrl() }}" id="paginatePrev" class="{{ $btnBase }}">Prev</a>
        @endif

        @if($winStart > 1)
            <a href="{{ $customers->url(1) }}" class="{{ $btnBase }} font-mono">1</a>
            @if($winStart > 2)
                <span class="px-1 text-slate-400">&hellip;</span>
            @endif
        @endif

        @for($n = $winStart; $n <= $winEnd; $n++)
            @if($n === $cur)
                <span aria-current="page" class="{{ $btnActive }}">{{ $n }}</span>
            @else
                <a href="{{ $customers->url($n) }}" class="{{ $btnBase }} font-mono">{{ $n }}</a>
            @endif
        @endfor

        @if($winEnd < $last)
            @if($winEnd < $last - 1)
                <span class="px-1 text-slate-400">&hellip;</span>
            @endif
            <a href="{{ $customers->url($last) }}" class="{{ $btnBase }} font-mono">{{ $last }}</a>
        @endif

        @if($customers->hasMorePages())
            <a href="{{ $customers->nextPageUrl() }}" id="paginateNext" class="{{ $btnBase }}">Next</a>
        @else
            <span class="{{ $btnDisabled }}">Next</span>
        @endif
    </div>
    @endif
</div>
