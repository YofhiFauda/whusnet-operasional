@extends('layouts.app')

@section('title', 'Kelola Wilayah — ' . $levelLabel)
@section('page_title', 'Kelola Wilayah')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-2 text-xs font-medium text-slate-500 dark:text-slate-400">
            <a href="{{ route('master.wilayah.index') }}" class="text-sky-600 dark:text-sky-400 hover:underline">Master Wilayah</a>
            <span>/</span>
            <span class="text-slate-700 dark:text-slate-200 font-semibold">Kelola</span>
        </div>
        @if(auth()->user()->hasPermission('master_wilayah.create'))
        <a href="{{ route('master.wilayah.create', ['level' => $level]) }}"
           class="inline-flex items-center gap-1.5 px-3.5 py-2 text-sm font-semibold text-white bg-sky-600 rounded-lg hover:bg-sky-700">
            Tambah {{ $levelLabel }}
        </a>
        @endif
    </div>

    @if(session('success'))
        <div class="px-4 py-3 rounded-lg text-sm bg-emerald-50 text-emerald-800 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="px-4 py-3 rounded-lg text-sm bg-rose-50 text-rose-800 border border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800">{{ session('error') }}</div>
    @endif

    <div class="flex flex-wrap items-center gap-2 text-xs">
        @foreach([
            'kota' => 'Kota/Kabupaten',
            'kecamatan' => 'Kecamatan',
            'desa' => 'Desa/Kelurahan',
        ] as $key => $label)
        <a href="{{ route('master.wilayah.kelola', ['level' => $key]) }}"
           class="px-3 py-1.5 rounded-full border {{ $level === $key ? 'bg-sky-600 text-white border-sky-600' : 'bg-white text-slate-600 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700' }}">
            {{ $label }}
        </a>
        @endforeach
    </div>

    <form method="GET" action="{{ route('master.wilayah.kelola') }}" class="flex gap-2">
        <input type="hidden" name="level" value="{{ $level }}">
        <input type="text" name="search" value="{{ $search }}" placeholder="Cari nama {{ strtolower($levelLabel) }}..."
               class="w-full max-w-sm px-3 py-1.5 text-sm rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100">
        <button type="submit" class="px-3 py-1.5 text-sm font-semibold text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg">Cari</button>
    </form>

    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-900/60 text-xs uppercase tracking-wider text-slate-500 dark:text-slate-400">
                <tr>
                    <th class="px-4 py-3 text-left">Nama</th>
                    @if($level === 'kecamatan')
                        <th class="px-4 py-3 text-left">Kota/Kabupaten</th>
                    @elseif($level === 'desa')
                        <th class="px-4 py-3 text-left">Kecamatan</th>
                        <th class="px-4 py-3 text-left">Kode Pos</th>
                    @endif
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                @forelse($rows as $row)
                <tr>
                    <td class="px-4 py-2.5 font-medium text-slate-900 dark:text-slate-100">{{ $row->name }}</td>
                    @if($level === 'kecamatan')
                        <td class="px-4 py-2.5 text-slate-600 dark:text-slate-300">{{ $row->city?->name }}</td>
                    @elseif($level === 'desa')
                        <td class="px-4 py-2.5 text-slate-600 dark:text-slate-300">{{ $row->district?->name }}, {{ $row->district?->city?->name }}</td>
                        <td class="px-4 py-2.5 text-slate-600 dark:text-slate-300">{{ $row->postal_code ?? '-' }}</td>
                    @endif
                    <td class="px-4 py-2.5 text-right whitespace-nowrap">
                        @if(auth()->user()->hasPermission('master_wilayah.update'))
                        <a href="{{ route('master.wilayah.edit', ['level' => $level, 'id' => $row->id]) }}" class="text-sky-600 dark:text-sky-400 hover:underline text-xs font-semibold">Ubah</a>
                        @endif
                        @if(auth()->user()->hasPermission('master_wilayah.delete'))
                        <form action="{{ route('master.wilayah.destroy', ['level' => $level, 'id' => $row->id]) }}" method="POST" class="inline ml-3">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-rose-600 dark:text-rose-400 hover:underline text-xs font-semibold"
                                    onclick="event.preventDefault(); window.confirmDelete(@js('Hapus '.$levelLabel.' '.$row->name.'? Ditolak jika masih dipakai pelanggan atau punya wilayah di bawahnya.'), this.closest('form'))">Hapus</button>
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="px-4 py-6 text-center text-slate-500 dark:text-slate-400">Belum ada data {{ $levelLabel }}.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div>{{ $rows->links() }}</div>
</div>
@endsection
