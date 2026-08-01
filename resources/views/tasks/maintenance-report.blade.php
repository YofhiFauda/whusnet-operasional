@extends('layouts.app')

@section('title', 'Laporan Maintenance — ' . $task->task_number)

@section('content')
<div class="mx-auto px-4 py-6 sm:px-6" style="max-width:960px">

    {{-- ══ Page Header — naked, no card ══════════════════════════════════ --}}
    <div class="mb-5">

        {{-- Breadcrumb / Back --}}
        <a href="{{ route('tasks.show', $task) }}"
           class="inline-flex items-center gap-1.5 text-xs font-medium mb-3 transition-colors"
           style="color:var(--color-text-muted)"
           onmouseover="this.style.color='var(--color-primary)'"
           onmouseout="this.style.color='var(--color-text-muted)'">
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Kembali ke Detail Task
        </a>

        {{-- Title Row --}}
        <h1 class="page-header-title mb-1">Laporan Maintenance</h1>

        {{-- Subtitle: task number + customer --}}
        <p class="page-header-subtitle flex flex-wrap items-center gap-1.5">
            <span>Task</span>
            <span class="font-semibold" style="font-family:var(--font-data,'JetBrains Mono',monospace);color:var(--color-primary);font-size:13px">{{ $task->task_number }}</span>
            @if($task->customer)
            <span style="color:var(--color-border)">·</span>
            <span style="color:var(--color-text-muted)">{{ $task->customer->name }}</span>
            @endif
        </p>
    </div>


    {{-- ══ Error Banner ════════════════════════════════════════════════════ --}}
    @if ($errors->any())
    <div class="rounded-lg px-4 py-3 mb-5 flex items-start gap-3"
         style="background:#FEF2F2;border:1px solid #FECACA">
        <svg class="h-4 w-4 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="color:#DC2626">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <ul class="text-sm space-y-0.5" style="color:#DC2626">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <form action="{{ route('tasks.maintenance.store', $task) }}" method="POST" enctype="multipart/form-data">
        @csrf

        {{-- ══ Main Form Panel — satu card utama ══════════════════════════ --}}
        <div class="bg-surface rounded-lg" style="border:1px solid var(--color-border)">

            {{-- ── Section 1: Kendala Teknis ──────────────────────────── --}}
            <div class="px-6 py-4" style="border-bottom:1px solid var(--color-border)">
                <p class="mb-4" style="font-size:11px;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;color:var(--color-text-muted)">
                    Kendala Teknis
                </p>
                <div>
                    <label class="block mb-1.5" style="font-size:13px;font-weight:500;color:var(--color-text-secondary)">
                        Detail Kendala &amp; Solusi <span style="color:var(--color-error)">*</span>
                    </label>
                    <textarea name="kendala_teknis"
                              rows="4"
                              required
                              placeholder="Jelaskan kendala teknis yang ditemukan dan solusi yang telah dilakukan..."
                              class="w-full rounded-md text-sm transition-colors"
                              style="border:1px solid var(--color-border);background:var(--color-background);color:var(--color-text-main);padding:10px 12px;resize:vertical;outline:none;font-family:var(--font-ui,'Inter',sans-serif)"
                              onfocus="this.style.borderColor='var(--color-primary)';this.style.boxShadow='0 0 0 3px rgba(2,132,199,0.15)'"
                              onblur="this.style.borderColor='var(--color-border)';this.style.boxShadow='none'">{{ old('kendala_teknis') }}</textarea>
                </div>
            </div>

            {{-- ── Section 2: Material Terpakai ────────────────────────
                 Menggantikan lima kolom teks lama (kabel/modem/patchcord/
                 sleeve/lainnya) — satu kolom per jenis barang, hardcode, tidak
                 bisa dijumlah dan tidak bisa disambung ke master. Kolomnya
                 masih ada di DB untuk laporan lama, tapi tidak lagi diisi dari
                 form ini. --}}
            <div class="px-6 py-4 text-xs" style="border-bottom:1px solid var(--color-border)">
                <p class="mb-1" style="font-size:11px;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;color:var(--color-text-muted)">
                    Material Terpakai
                </p>
                <p class="text-[10px] mb-3 leading-relaxed font-normal" style="color:var(--color-text-muted)">
                    Barang yang <b>habis dipakai</b> dan ditinggal di pelanggan — patch cord diganti, splitter diganti, kabel sambungan. Peralatan kerja yang dibawa pulang dicatat terpisah di bawah.
                </p>
                <x-material-rows
                    name="materials"
                    :items="$items"
                    :categories="$itemCategories"
                    :rows="$materialRows"
                    empty-label="Belum ada material dicatat. Kalau perbaikan ini tidak memakai material, biarkan kosong."
                />
            </div>

            {{-- ── Section 3: Alat Kerja ───────────────────────────────── --}}
            <div class="px-6 py-4 text-xs" style="border-bottom:1px solid var(--color-border)">
                <x-work-tool-checklist
                    name="work_tools"
                    :tools="$workTools"
                    :rows="$workToolRows"
                    label="Alat Kerja Yang Dipakai"
                    hint="Peralatan yang dibawa ke lokasi lalu dibawa pulang — tangga, splicer, OPM. Bukan material yang ditinggal di pelanggan."
                />
            </div>

            {{-- ── Section 4: Foto Bukti ───────────────────────────────── --}}
            <div class="px-6 py-4" style="border-bottom:1px solid var(--color-border)">
                <p class="mb-4" style="font-size:11px;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;color:var(--color-text-muted)">
                    Foto Bukti
                </p>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">

                    {{-- Foto OPM --}}
                    <div>
                        <label class="block mb-2" style="font-size:13px;font-weight:500;color:var(--color-text-secondary)">
                            Foto Hasil OPM
                            <span class="ml-1 text-xs font-normal" style="color:var(--color-text-muted)">(gunakan tag lokasi)</span>
                            <span style="color:var(--color-error)"> *</span>
                        </label>

                        {{-- Drop zone OPM --}}
                        <label for="opm_photo"
                               class="flex flex-col items-center justify-center gap-2 rounded-lg cursor-pointer transition-colors"
                               style="border:2px dashed var(--color-border);background:var(--color-background);padding:28px 16px;text-align:center"
                               onmouseover="this.style.borderColor='var(--color-primary)';this.style.background='var(--color-primary-soft,#E0F2FE)'"
                               onmouseout="this.style.borderColor='var(--color-border)';this.style.background='var(--color-background)'"
                               id="opm_label">
                            <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" style="color:var(--color-text-muted)">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z"/>
                            </svg>
                            <div>
                                <p class="text-sm font-medium" style="color:var(--color-primary)">Pilih Foto OPM</p>
                                <p class="text-xs mt-0.5" style="color:var(--color-text-muted)">JPG, PNG, HEIC · Maks 2MB</p>
                            </div>
                            <input type="file" id="opm_photo" name="opm_photo"
                                   accept="image/*" capture="environment" required class="sr-only"
                                   onchange="previewImage(this, 'opm_preview', 'opm_label')">
                        </label>

                        {{-- Preview OPM --}}
                        <div id="opm_preview" class="mt-2 hidden">
                            <div class="relative rounded-lg overflow-hidden" style="border:1px solid var(--color-border)">
                                <img id="opm_preview_img" src="" alt="Preview OPM" class="w-full object-cover" style="max-height:180px">
                                <div class="absolute top-2 right-2">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-semibold text-white" style="background:rgba(0,0,0,0.55)">
                                        <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                                        Foto dipilih
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Foto Speedtest --}}
                    <div>
                        <label class="block mb-2" style="font-size:13px;font-weight:500;color:var(--color-text-secondary)">
                            Foto Hasil Speedtest
                            <span style="color:var(--color-error)"> *</span>
                        </label>

                        {{-- Drop zone Speedtest --}}
                        <label for="speedtest_photo"
                               class="flex flex-col items-center justify-center gap-2 rounded-lg cursor-pointer transition-colors"
                               style="border:2px dashed var(--color-border);background:var(--color-background);padding:28px 16px;text-align:center"
                               onmouseover="this.style.borderColor='var(--color-primary)';this.style.background='var(--color-primary-soft,#E0F2FE)'"
                               onmouseout="this.style.borderColor='var(--color-border)';this.style.background='var(--color-background)'"
                               id="speedtest_label">
                            <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" style="color:var(--color-text-muted)">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/>
                            </svg>
                            <div>
                                <p class="text-sm font-medium" style="color:var(--color-primary)">Pilih Foto Speedtest</p>
                                <p class="text-xs mt-0.5" style="color:var(--color-text-muted)">JPG, PNG, HEIC · Maks 2MB</p>
                            </div>
                            <input type="file" id="speedtest_photo" name="speedtest_photo"
                                   accept="image/*" capture="environment" required class="sr-only"
                                   onchange="previewImage(this, 'speedtest_preview', 'speedtest_label')">
                        </label>

                        {{-- Preview Speedtest --}}
                        <div id="speedtest_preview" class="mt-2 hidden">
                            <div class="relative rounded-lg overflow-hidden" style="border:1px solid var(--color-border)">
                                <img id="speedtest_preview_img" src="" alt="Preview Speedtest" class="w-full object-cover" style="max-height:180px">
                                <div class="absolute top-2 right-2">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-semibold text-white" style="background:rgba(0,0,0,0.55)">
                                        <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                                        Foto dipilih
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            {{-- ── Form Footer: Actions ────────────────────────────────── --}}
            <div class="px-6 py-4 flex items-center justify-between gap-3" style="background:var(--color-background);border-radius:0 0 0.5rem 0.5rem">
                <a href="{{ route('tasks.show', $task) }}"
                   class="inline-flex items-center gap-2 text-sm font-medium px-4 py-2 rounded-md transition-colors"
                   style="border:1px solid var(--color-border);color:var(--color-text-secondary);background:var(--color-surface)"
                   onmouseover="this.style.background='var(--color-surface-muted,#F1F5F9)'"
                   onmouseout="this.style.background='var(--color-surface)'">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    Batal
                </a>

                <button type="submit"
                        id="submit-btn"
                        class="inline-flex items-center gap-2 text-sm font-semibold px-6 py-2 rounded-md text-white transition-colors"
                        style="background:var(--color-success);cursor:pointer"
                        onmouseover="this.style.opacity='0.9'"
                        onmouseout="this.style.opacity='1'">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Selesaikan &amp; Simpan Laporan
                </button>
            </div>

        </div>{{-- /main panel --}}

    </form>
</div>

<script>
function previewImage(input, previewContainerId, labelId) {
    const file = input.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = function(e) {
        const container = document.getElementById(previewContainerId);
        const img = document.getElementById(previewContainerId + '_img');
        img.src = e.target.result;
        container.classList.remove('hidden');

        // Hide the drop zone label once photo is chosen
        const label = document.getElementById(labelId);
        label.style.display = 'none';
    };
    reader.readAsDataURL(file);
}

// Show loading state on submit
document.querySelector('form').addEventListener('submit', function() {
    const btn = document.getElementById('submit-btn');
    btn.disabled = true;
    btn.innerHTML = `
        <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
        </svg>
        Menyimpan...
    `;
    btn.style.opacity = '0.75';
    btn.style.cursor = 'not-allowed';
});
</script>
@endsection
