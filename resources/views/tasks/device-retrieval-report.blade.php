@extends('layouts.app')

@section('title', 'Laporan Ambil Alat — ' . $task->task_number)

@section('content')
@php
    $existing = $task->deviceRetrieval;
    $initialOutcome = old('outcome', $existing?->outcome->value ?? 'diambil');
    $legacyItemId = $items->firstWhere('code', 'MODEM-PELANGGAN-LAMA')?->id;

    // Baris SN awal: (1) isian sebelumnya kalau validasi gagal, (2) SN yang
    // tercatat terpasang di pelanggan ini, (3) SN dari data lama pelanggan
    // (modem pelanggan lama) dengan item "Modem Pelanggan Lama" terpilih, (4) satu baris kosong.
    $initialRows = collect(old('serials', []))->map(fn ($row) => [
        'serial_number' => $row['serial_number'] ?? '',
        'item_id' => $row['item_id'] ?? '',
    ])->values();

    if ($initialRows->isEmpty()) {
        $initialRows = $installedSerials->map(fn ($serial) => [
            'serial_number' => $serial->serial_number,
            'item_id' => $serial->item_id,
        ])->values();
    }

    if ($initialRows->isEmpty() && $legacySerial !== '') {
        // Model dipilih otomatis kalau merek di data lama jelas (mis. "ZTE F609");
        // selain itu "Modem Pelanggan Lama". Hanya tebakan awal — teknisi bisa mengubahnya.
        $initialRows = collect([['serial_number' => $legacySerial, 'item_id' => $legacyHint['item']?->id ?? $legacyItemId ?? '']]);
    }

    if ($initialRows->isEmpty()) {
        $initialRows = collect([['serial_number' => '', 'item_id' => '']]);
    }

    $checkedAccessories = old('accessories', $existing?->accessories ?? []);
@endphp

<div class="max-w-4xl mx-auto space-y-4 pb-8">

    {{-- ══ Breadcrumb (Design.md §13) ════════════════════════════════════════ --}}
    <nav class="flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
        <a href="{{ route('fop.dashboard') }}" class="hover:text-sky-600 dark:hover:text-sky-400 transition-colors font-ui">Home</a>
        <svg class="h-3 w-3 shrink-0 text-slate-400 dark:text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
        </svg>
        @can('viewAll', \App\Models\Task::class)
        <a href="{{ auth()->user()->hasPermission('task.view.own') ? route('tasks.own') : route('fop.dashboard') }}" class="hover:text-sky-600 dark:hover:text-sky-400 transition-colors font-ui">Task</a>
        @else
        <a href="{{ route('tasks.own') }}" class="hover:text-sky-600 dark:hover:text-sky-400 transition-colors font-ui">Task Saya</a>
        @endcan
        <svg class="h-3 w-3 shrink-0 text-slate-400 dark:text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
        </svg>
        <a href="{{ route('tasks.show', $task) }}" class="font-mono hover:text-sky-600 dark:hover:text-sky-400 transition-colors">{{ $task->task_number }}</a>
        <svg class="h-3 w-3 shrink-0 text-slate-400 dark:text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
        </svg>
        <span class="text-slate-800 dark:text-slate-200 font-medium font-ui" aria-current="page">Laporan Ambil Alat</span>
    </nav>

    {{-- ══ Page Header ══════════════════════════════════════════════ --}}
    <div class="flex items-start justify-between gap-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('tasks.show', $task) }}"
               class="h-9 w-9 flex items-center justify-center rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors shrink-0"
               title="Kembali ke Detail Task">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h1 class="text-lg sm:text-xl font-bold text-slate-900 dark:text-slate-100 font-ui tracking-tight">Laporan Penarikan Perangkat</h1>
                    <span class="font-mono text-xs font-semibold text-sky-700 dark:text-sky-400 bg-sky-50 dark:bg-sky-950/50 px-2 py-0.5 rounded-md border border-sky-200 dark:border-sky-800/60">
                        {{ $task->task_number }}
                    </span>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 flex items-center gap-1.5 font-ui">
                    <span>Pelanggan:</span>
                    <span class="font-mono font-medium text-slate-700 dark:text-slate-300">{{ $task->customer?->display_id }}</span>
                    <span>—</span>
                    <span class="font-medium text-slate-800 dark:text-slate-200">{{ $task->customer?->full_name }}</span>
                </p>
            </div>
        </div>
    </div>

    {{-- ══ Error Notification Banner ════════════════════════════════ --}}
    @if ($errors->any())
    <div class="rounded-lg p-4 bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-800/60 text-red-800 dark:text-red-300 text-xs sm:text-sm flex items-start gap-3">
        <svg class="h-5 w-5 text-red-600 dark:text-red-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <div>
            <p class="font-semibold mb-1">Terdapat kesalahan pada isian form:</p>
            <ul class="list-disc list-inside space-y-0.5 text-xs">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
    @endif

    {{-- ══ Rejected Reason Alert Banner ═════════════════════════════ --}}
    @if($task->reject_reason && $task->fop_review_status === 'rejected')
    <div class="rounded-lg p-4 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 text-amber-800 dark:text-amber-300 text-xs sm:text-sm flex items-start gap-3">
        <svg class="h-5 w-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
        </svg>
        <div>
            <p class="font-semibold mb-0.5">Laporan sebelumnya ditolak FOP:</p>
            <p class="text-xs text-amber-700 dark:text-amber-400">{{ $task->reject_reason }}</p>
        </div>
    </div>
    @endif

    {{-- ══ Main Single Panel Form (Design.md §23 & §1) ═══════════════ --}}
    <form action="{{ route('tasks.device-retrieval.store', $task) }}" method="POST" enctype="multipart/form-data"
          x-data="{ outcome: @js($initialOutcome), rows: @js($initialRows->all()), add() { this.rows.push({ serial_number: '', item_id: '' }) }, remove(i) { this.rows.splice(i, 1) } }">
        @csrf

        <div class="bg-white dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-800 overflow-hidden shadow-xs">

            {{-- ── Section 1: HASIL DI LAPANGAN (Design.md §4: 10px uppercase #707881) ── --}}
            <div class="p-5 sm:p-6 border-b border-slate-200 dark:border-slate-800 space-y-3">
                <h2 class="text-[10px] font-semibold tracking-wider uppercase text-slate-500 dark:text-slate-400 font-ui">
                    Hasil di Lapangan
                </h2>
                
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    @foreach($outcomes as $option)
                    <label class="relative flex items-center p-3 rounded-lg border border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 bg-slate-50/50 dark:bg-slate-800/40 cursor-pointer transition-all has-[:checked]:border-sky-600 dark:has-[:checked]:border-sky-500 has-[:checked]:bg-sky-50/60 dark:has-[:checked]:bg-sky-950/40">
                        <input type="radio" name="outcome" value="{{ $option->value }}" x-model="outcome" @checked($initialOutcome === $option->value)
                               class="h-4 w-4 text-sky-600 dark:text-sky-500 border-slate-300 dark:border-slate-700 focus:ring-sky-500 dark:focus:ring-sky-400 accent-sky-600">
                        <span class="ml-2.5 text-xs font-medium text-slate-800 dark:text-slate-200 font-ui">{{ $option->label() }}</span>
                    </label>
                    @endforeach
                </div>
            </div>

            {{-- ── Section 2: MODEM YANG DIBAWA (hanya jika diambil) ── --}}
            <div class="p-5 sm:p-6 border-b border-slate-200 dark:border-slate-800 space-y-5" x-show="outcome === 'diambil'" x-cloak>
                <div>
                    <h2 class="text-[10px] font-semibold tracking-wider uppercase text-slate-500 dark:text-slate-400 font-ui mb-1">
                        Modem yang Dibawa
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed font-ui">
                        Isi nomor seri (SN) sesuai <strong class="text-slate-700 dark:text-slate-300 font-semibold">yang tertera di perangkat fisik</strong>, bukan dari data sistem. Pilih model jika SN belum pernah tercatat (modem pelanggan lama) — jika tidak tahu modelnya, pilih "Modem Pelanggan Lama". Staf Gudang akan mengoreksinya saat menerima.
                    </p>
                </div>

                @if($installedSerials->isNotEmpty())
                <div class="p-3 rounded-lg bg-sky-50/70 dark:bg-sky-950/40 border border-sky-200 dark:border-sky-800/60 text-xs text-sky-800 dark:text-sky-300 flex items-start gap-2 font-ui">
                    <svg class="h-4 w-4 text-sky-600 dark:text-sky-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div>
                        <span class="font-semibold">Tercatat terpasang di pelanggan ini:</span>
                        <ul class="mt-1 space-y-0.5">
                            @foreach($installedSerials as $s)
                            <li class="font-mono text-sky-900 dark:text-sky-200 font-medium">
                                {{ $s->serial_number }} <span class="font-sans text-sky-700 dark:text-sky-400">({{ $s->item?->name ?? 'Perangkat' }})</span>
                            </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
                @elseif($legacySerial !== '')
                <div class="p-3 rounded-lg bg-amber-50/70 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 text-xs text-amber-800 dark:text-amber-300 flex items-start gap-2 font-ui">
                    <svg class="h-4 w-4 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <div>
                        <span class="font-semibold">Data lama pelanggan mencatat SN:</span>
                        <span class="font-mono font-bold text-amber-900 dark:text-amber-200 mx-1">{{ $legacySerial }}</span>
                        @if($legacyHint['label']) <span class="font-sans text-amber-700 dark:text-amber-400">(perangkat: "{{ $legacyHint['label'] }}")</span> @endif
                        <span class="block mt-0.5 text-[11px] text-amber-700 dark:text-amber-400">Cocokkan dengan perangkat di lokasi. Data lama bisa keliru, SN di stiker yang berlaku.</span>
                    </div>
                </div>
                @endif

                {{-- Dynamic Serial Input Rows --}}
                <div class="space-y-3">
                    <template x-for="(row, i) in rows" :key="i">
                        <div class="grid grid-cols-1 sm:grid-cols-[1fr_1fr_auto] gap-2.5 items-center p-3 rounded-lg bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-slate-800">
                            <div>
                                <label class="block text-[10px] font-semibold uppercase text-slate-400 dark:text-slate-500 mb-1 font-ui">Nomor Seri (SN)</label>
                                <input type="text" :name="`serials[${i}][serial_number]`" x-model="row.serial_number" maxlength="100"
                                       placeholder="Contoh: ZTEG12345678"
                                       class="w-full rounded-lg text-xs sm:text-sm font-mono bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-slate-100 px-3 py-2 focus:ring-2 focus:ring-sky-500 focus:border-sky-500 outline-none transition-colors">
                            </div>
                            <div>
                                <label class="block text-[10px] font-semibold uppercase text-slate-400 dark:text-slate-500 mb-1 font-ui">Model Perangkat</label>
                                <select :name="`serials[${i}][item_id]`" x-model="row.item_id"
                                        class="w-full rounded-lg text-xs sm:text-sm font-ui bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-slate-100 px-3 py-2 focus:ring-2 focus:ring-sky-500 focus:border-sky-500 outline-none transition-colors">
                                    <option value="">— Model (isi kalau SN belum tercatat) —</option>
                                    @foreach($items as $item)
                                    <option value="{{ $item->id }}">{{ $item->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="flex items-end sm:pt-4">
                                <button type="button" @click="remove(i)" x-show="rows.length > 1"
                                        class="h-9 px-3 text-xs font-semibold text-red-600 dark:text-red-400 hover:text-red-700 dark:hover:text-red-300 hover:bg-red-50 dark:hover:bg-red-950/50 rounded-lg transition-colors flex items-center gap-1">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                    Hapus
                                </button>
                            </div>
                        </div>
                    </template>
                </div>

                <button type="button" @click="add()"
                        class="inline-flex items-center gap-1.5 text-xs font-semibold text-sky-600 dark:text-sky-400 hover:text-sky-700 dark:hover:text-sky-300 transition-colors font-ui">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                    </svg>
                    Tambah SN
                </button>

                {{-- Foto Kondisi Alat --}}
                <div class="pt-3 border-t border-slate-200 dark:border-slate-800 space-y-1.5">
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 font-ui">
                        Foto Kondisi Alat <span class="text-red-500">*</span>
                    </label>
                    <input type="file" name="condition_photo" accept="image/jpeg,image/png,image/webp,image/jpg,.jpg,.jpeg,.png,.webp"
                           class="block w-full text-xs text-slate-500 dark:text-slate-400 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-sky-50 file:text-sky-700 hover:file:bg-sky-100 dark:file:bg-sky-950/60 dark:file:text-sky-300 font-ui transition-colors cursor-pointer">
                    @if($existing?->condition_photo)
                    <p class="text-[11px] text-slate-400 dark:text-slate-500 font-ui">Foto sebelumnya tersimpan — unggah lagi jika ingin menggantinya.</p>
                    @endif
                </div>

                {{-- Kelengkapan Aksesori --}}
                <div class="pt-3 border-t border-slate-200 dark:border-slate-800 space-y-2">
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 font-ui">
                        Kelengkapan yang Ikut Dibawa
                    </label>
                    <div class="flex flex-wrap gap-x-6 gap-y-2">
                        @foreach($accessoryOptions as $key => $label)
                        <label class="inline-flex items-center gap-2 text-xs text-slate-700 dark:text-slate-300 font-ui cursor-pointer">
                            <input type="checkbox" name="accessories[]" value="{{ $key }}" @checked(in_array($key, $checkedAccessories, true))
                                   class="h-4 w-4 text-sky-600 dark:text-sky-500 rounded border-slate-300 dark:border-slate-700 focus:ring-sky-500 dark:focus:ring-sky-400 accent-sky-600">
                            <span>{{ $label }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- ── Section 3: CATATAN / ALASAN (Design.md §4) ── --}}
            <div class="p-5 sm:p-6 space-y-2">
                <label class="block text-[10px] font-semibold tracking-wider uppercase text-slate-500 dark:text-slate-400 font-ui">
                    <span x-show="outcome === 'diambil'">Catatan (opsional)</span>
                    <span x-show="outcome !== 'diambil'" x-cloak>Alasan alat tidak diambil <span class="text-red-500">*</span></span>
                </label>
                <textarea name="notes" rows="3" maxlength="1000"
                          placeholder="Kondisi alat, kelengkapan yang kurang, atau alasan alat tidak berhasil diambil..."
                          class="w-full rounded-lg text-xs sm:text-sm font-ui bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-slate-100 p-3 focus:ring-2 focus:ring-sky-500 focus:border-sky-500 outline-none transition-colors placeholder-slate-400 dark:placeholder-slate-500 resize-y">{{ old('notes', $existing?->notes) }}</textarea>
                <p class="text-[11px] text-slate-400 dark:text-slate-500 font-ui" x-show="outcome !== 'diambil'" x-cloak>
                    Task tetap selesai, tapi alat belum tercatat diambil — tombol "Ambil Alat" muncul kembali di List Putus Langganan.
                </p>
            </div>
        </div>

        {{-- ══ Submit Actions (Design.md §9 & §15: Solid Sky Blue, rounded-lg 8px) ══ --}}
        <div class="mt-4 flex items-center justify-end gap-3">
            <a href="{{ route('tasks.show', $task) }}"
               class="px-4 py-2.5 text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-lg transition-colors font-ui">
                Batal
            </a>
            <button type="submit"
                    class="inline-flex items-center justify-center gap-2 px-5 py-2.5 text-xs sm:text-sm font-semibold text-white bg-sky-600 hover:bg-sky-700 active:bg-sky-800 rounded-lg transition-colors shadow-xs font-ui cursor-pointer">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
                Simpan Laporan &amp; Selesaikan Task
            </button>
        </div>
    </form>
</div>
@endsection
