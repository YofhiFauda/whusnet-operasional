{{--
    Komponen: Checklist Alat Kerja
    ==============================
    Dipakai TIGA halaman — Laporan Survey, Laporan Pemasangan, Laporan
    Maintenance. Satu komponen, bukan tiga markup kembar: daftar yang dibaca
    teknisi sebelum berangkat harus sama bentuknya dari mana pun diisi.

    Props:
      $name  (string) — nama field array, mis. "work_tools"
      $tools (Collection<WorkTool>) — master alat aktif
      $rows  (array) — baris awal (prefill). Tiap baris: work_tool_id, tool_name, note

    Ini CHECKLIST, bukan daftar material: tidak ada qty. Alat dibawa lalu
    dibawa pulang — yang dijawab cuma "perlu bawa atau tidak". Material yang
    habis dipakai ada di komponen material-rows, tabel berbeda.

    Alat di luar master diisi lewat baris manual di bawah checklist.
--}}

@props([
    'name' => 'work_tools',
    'tools' => null,
    'rows' => [],
    'label' => 'Alat Kerja Yang Perlu Dibawa',
    'hint' => 'Centang alat yang harus dibawa tim ke lokasi. Ini peralatan kerja — dibawa lalu dibawa pulang, bukan material yang dipasang di pelanggan.',
])

@php
    // Fallback query kalau pemanggil belum mengirim prop — tiga halaman memakai
    // komponen ini dan satu di antaranya gampang terlewat.
    $toolOptions = collect($tools ?? \App\Models\WorkTool::options());

    $selectedIds = collect($rows)->pluck('work_tool_id')->filter()->map(fn ($id) => (int) $id)->all();

    // Baris manual = alat yang tersimpan tanpa work_tool_id (di luar master),
    // ATAU yang masternya sudah dihapus. Dua-duanya tetap harus tampil.
    $manualRows = collect($rows)->filter(fn ($row) => empty($row['work_tool_id']))->values();

    // Alat nonaktif yang masih dipakai baris ini tetap ditawarkan — kalau tidak,
    // membuka laporan lama lalu menyimpannya diam-diam menghapus alat itu dari
    // checklist tanpa ada yang menghapusnya.
    $inactiveSelected = collect($rows)
        ->pluck('work_tool_id')
        ->filter()
        ->map(fn ($id) => (int) $id)
        ->reject(fn ($id) => $toolOptions->contains('id', $id));

    $extraTools = $inactiveSelected->isNotEmpty()
        ? \App\Models\WorkTool::whereIn('id', $inactiveSelected)->get()
        : collect();
@endphp

<div x-data="workToolChecklist(@js($manualRows), '{{ $name }}')" class="space-y-3">
    <div>
        <label class="block mb-1 uppercase tracking-wide text-slate-700 dark:text-slate-300">{{ $label }}</label>
        <p class="text-[10px] text-slate-400 dark:text-slate-500 mb-3 leading-relaxed font-normal">{{ $hint }}</p>
    </div>

    @if($toolOptions->isEmpty() && $extraTools->isEmpty())
        <p class="text-[11px] text-slate-400 dark:text-slate-500 italic py-2">
            Master alat kerja masih kosong. Isi lewat Master Data → Alat Kerja, atau tulis manual di bawah.
        </p>
    @else
        <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
            @foreach($toolOptions->concat($extraTools) as $tool)
                <label class="flex items-start gap-2 bg-slate-50 dark:bg-slate-900/50 border border-slate-100 dark:border-slate-700/50 rounded-lg px-3 py-2 cursor-pointer hover:border-sky-300 dark:hover:border-sky-700 transition-colors">
                    <input
                        type="checkbox"
                        name="{{ $name }}_ids[]"
                        value="{{ $tool->id }}"
                        @checked(in_array($tool->id, $selectedIds, true))
                        class="mt-0.5 h-3.5 w-3.5 rounded border-slate-300 dark:border-slate-600 text-sky-600 focus:ring-sky-500 focus:ring-offset-0"
                    >
                    <span class="min-w-0">
                        <span class="block text-xs font-medium text-slate-700 dark:text-slate-300 truncate">
                            {{ $tool->name }}@unless($tool->is_active) <span class="text-[10px] text-slate-400">(nonaktif)</span>@endunless
                        </span>
                        @if($tool->note)
                            <span class="block text-[10px] text-slate-400 dark:text-slate-500 truncate">{{ $tool->note }}</span>
                        @endif
                    </span>
                </label>
            @endforeach
        </div>
    @endif

    {{-- Alat di luar master --}}
    <div class="pt-1 space-y-2">
        <template x-for="(row, index) in manualRows" :key="index">
            <div class="flex items-center gap-2">
                <input
                    type="text"
                    :name="`${fieldName}_manual[${index}][tool_name]`"
                    x-model="row.tool_name"
                    placeholder="Alat lain di luar daftar..."
                    class="flex-1 text-xs font-sans px-2 py-1.5 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:border-sky-500 dark:focus:border-sky-400"
                >
                <input
                    type="text"
                    :name="`${fieldName}_manual[${index}][note]`"
                    x-model="row.note"
                    placeholder="Catatan"
                    class="w-32 text-xs font-sans px-2 py-1.5 border border-slate-200 dark:border-slate-700 rounded-md bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:border-sky-500 dark:focus:border-sky-400"
                >
                <button
                    type="button"
                    @click="removeRow(index)"
                    class="text-red-500 hover:text-red-700 dark:hover:text-red-400 p-1.5 rounded hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
                    title="Hapus baris"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3" />
                    </svg>
                </button>
            </div>
        </template>

        <button
            type="button"
            @click="addRow()"
            class="inline-flex items-center gap-1.5 text-xs font-semibold text-sky-700 dark:text-sky-300 bg-sky-50 dark:bg-sky-950/40 border border-sky-200 dark:border-sky-800 hover:bg-sky-100 dark:hover:bg-sky-900/50 px-3 py-1.5 rounded-md transition-colors"
        >
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            Alat Lain
        </button>
    </div>
</div>

@once
@push('scripts')
<script>
function workToolChecklist(manualRows, fieldName) {
    return {
        manualRows: manualRows,
        fieldName: fieldName,

        addRow() {
            this.manualRows.push({ tool_name: '', note: '' });
        },

        removeRow(index) {
            this.manualRows.splice(index, 1);
        },
    };
}
</script>
@endpush
@endonce
