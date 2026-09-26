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

    <div id="client-error-banner" class="hidden rounded-lg px-4 py-3 mb-5 flex items-start gap-3 bg-rose-50 border border-rose-200 text-rose-700 text-sm">
        <svg class="h-4 w-4 mt-0.5 shrink-0 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <span id="client-error-text"></span>
    </div>

    <form id="maintenance-form" action="{{ route('tasks.maintenance.store', $task) }}" method="POST" enctype="multipart/form-data" class="no-confirm">
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
                    Barang yang <b>habis dipakai</b> dan ditinggal di pelanggan — patch cord diganti, splitter diganti, kabel sambungan. Peralatan kerja yang dibawa pulang dicatat terpisah di bawah. Cuma barang yang beneran ada di custody tim ini (sudah di-<i>issue</i> Gudang) yang bisa dipilih; sisa custody tercantum di tiap baris.
                </p>
                {{-- Sengaja TANPA ringkasan statis "Sisa custody tim" (dicoba
                     2026-09-12, langsung direvisi user hari sama — makan tempat).
                     Sisa custody per-item CUMA tampil kalau barangnya lagi
                     dipilih di salah satu baris — lihat itemOptionLabel() &
                     availableFor() di material-rows.blade.php. --}}
                @if($eligiblePassiveCustody->isEmpty())
                    <p class="text-[11px] mb-2 font-semibold" style="color:var(--color-warning,#d97706)">⚠ Tim ini belum punya custody Perangkat Pasif apa pun — ambil barang dari Gudang dulu kalau perbaikan ini butuh material, atau baris di bawah cuma bisa nampilin material yang sudah tersimpan sebelumnya.</p>
                @endif
                <x-material-rows
                    name="materials"
                    :categories="$itemCategories"
                    :rows="$materialRows"
                    :restrict-to-custody="true"
                    :custody-options="$eligiblePassiveCustody"
                    empty-label="Belum ada material dicatat. Kalau perbaikan ini tidak memakai material, biarkan kosong."
                />
            </div>

            {{-- ── Section 2b: Modem/Perangkat Aktif (Opsional) ────────
                 Beda dari Laporan Pemasangan: SN di sini OPSIONAL. Maintenance
                 gak selalu ganti modem — kalau gak bawa/ganti, biarkan
                 "Tidak ganti modem" (default) dan SN gak akan tersimpan. --}}
            <div class="px-6 py-4 text-xs" style="border-bottom:1px solid var(--color-border)">
                <p class="mb-1" style="font-size:11px;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;color:var(--color-text-muted)">
                    Modem/Perangkat Aktif <span class="font-normal normal-case" style="color:var(--color-text-muted)">(opsional)</span>
                </p>
                <p class="text-[10px] mb-3 leading-relaxed font-normal" style="color:var(--color-text-muted)">
                    Isi HANYA kalau teknisi membawa &amp; memasang modem/ONT pengganti dari Gudang. Kalau tidak bawa/ganti modem, biarkan kosong.
                </p>
                @if($eligibleSerials->isNotEmpty())
                    @php
                        // Sisa stok Perangkat Aktif per jenis barang (koreksi lanjutan
                        // ADHOC-54, 2026-09-12) — sama pola installations/report.blade.php:
                        // SERIALIZED gak punya qty, jadi dihitung dari JUMLAH SN yang masih
                        // ISSUED di custody tim, dikelompokkan per nama barang.
                        $serialCountsByItem = $eligibleSerials->groupBy(fn ($serial) => $serial->item->name)
                            ->map->count();
                    @endphp
                    <select name="selected_inventory_serial_id" id="selected_inventory_serial_id" onchange="updateSnStockHint()"
                            class="w-full rounded-md text-sm"
                            style="border:1px solid var(--color-border);background:var(--color-background);color:var(--color-text-main);padding:10px 12px;outline:none">
                        <option value="">Tidak ganti modem</option>
                        @foreach($eligibleSerials as $serial)
                            <option value="{{ $serial->id }}" data-item-name="{{ $serial->item->name }}" data-available="{{ $serialCountsByItem[$serial->item->name] }}" @selected(old('selected_inventory_serial_id') == $serial->id)>
                                {{ $serial->item->name }} — SN {{ $serial->serial_number }}
                            </option>
                        @endforeach
                    </select>
                    <p class="text-[10px] mt-1 leading-relaxed" style="color:var(--color-text-muted)">Perangkat yang diambil lewat Gudang (custody Anda) — SN yang tersimpan otomatis sama persis dengan yang dipilih di sini.</p>
                    {{-- Sisa custody CUMA tampil kalau SN-nya lagi dipilih (revisi user
                         2026-09-12: ringkasan statis makan tempat) — updateSnStockHint() di
                         <script> bawah file ini, duplikasi persis installations/report.blade.php
                         (view terpisah, bukan partial/push bersama — sama pola duplikasi
                         eligibleSerialsForTeam()/eligiblePassiveCustodyForTeam() di controller). --}}
                    <p id="sn-stock-hint" class="text-[10px] mt-1 font-semibold" style="color:var(--color-success,#059669); {{ old('selected_inventory_serial_id') ? '' : 'display:none' }}"></p>
                @else
                    <select disabled class="w-full rounded-md text-sm" style="border:1px solid var(--color-border);background:var(--color-surface-muted);color:var(--color-text-muted);padding:10px 12px">
                        <option>Tidak ada SN di custody Anda</option>
                    </select>
                    <p class="text-[10px] mt-1 leading-relaxed" style="color:var(--color-text-muted)">Anda belum mengambil modem dari Gudang — kalau maintenance ini gak perlu ganti modem, abaikan saja.</p>
                @endif
            </div>

            {{-- ── Section 2c: Roll Kabel (Opsional) ─────────────────────
                 Sama pola Modem/Perangkat Aktif di atas — OPSIONAL, teknisi
                 gak selalu potong kabel dari roll ter-track saat maintenance. --}}
            <div class="px-6 py-4 text-xs" style="border-bottom:1px solid var(--color-border)">
                <p class="mb-1" style="font-size:11px;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;color:var(--color-text-muted)">
                    Roll Kabel <span class="font-normal normal-case" style="color:var(--color-text-muted)">(opsional)</span>
                </p>
                <p class="text-[10px] mb-3 leading-relaxed font-normal" style="color:var(--color-text-muted)">
                    Isi HANYA kalau teknisi potong kabel dari roll yang ke-track per-roll. Kalau tidak, biarkan kosong.
                </p>
                @if($eligibleRolls->isNotEmpty())
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <select name="selected_inventory_roll_id" id="selected_inventory_roll_id"
                                class="w-full rounded-md text-sm"
                                style="border:1px solid var(--color-border);background:var(--color-background);color:var(--color-text-main);padding:10px 12px;outline:none">
                            <option value="">Tidak pakai roll kabel</option>
                            @foreach($eligibleRolls as $roll)
                                <option value="{{ $roll->id }}" @selected(old('selected_inventory_roll_id') == $roll->id)>
                                    {{ $roll->item->name ?? '(barang dihapus)' }} — {{ $roll->roll_code }} (sisa {{ rtrim(rtrim(number_format((float) $roll->length_remaining, 2, ',', '.'), '0'), ',') }} m)
                                </option>
                            @endforeach
                        </select>
                        <input type="number" step="0.01" min="0.01" name="roll_meters_used" id="roll_meters_used" value="{{ old('roll_meters_used') }}" placeholder="Meter terpakai"
                               class="w-full rounded-md text-sm"
                               style="border:1px solid var(--color-border);background:var(--color-background);color:var(--color-text-main);padding:10px 12px;outline:none">
                    </div>
                    <p class="text-[10px] mt-1 leading-relaxed" style="color:var(--color-text-muted)">Pilih roll yang lagi di custody Anda, isi berapa meter dipakai.</p>
                @else
                    <p class="text-[10px] mt-1 leading-relaxed" style="color:var(--color-text-muted)">Tidak ada roll kabel di custody Anda — abaikan kalau gak perlu.</p>
                @endif
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
                    <div class="border-2 border-dashed border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/40 hover:border-sky-500 dark:hover:border-sky-400 rounded-xl p-4 text-center transition-all shadow-xs flex flex-col justify-between relative group">
                        <div id="default-placeholder-opm_photo" class="py-4 space-y-2">
                            <div class="w-10 h-10 mx-auto rounded-full bg-sky-50 dark:bg-sky-900/40 text-sky-600 dark:text-sky-400 flex items-center justify-center text-lg border border-sky-200 dark:border-sky-800">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z"/>
                                </svg>
                            </div>
                            <div>
                                <span class="block text-xs font-bold text-slate-800 dark:text-slate-200">FOTO HASIL OPM <span class="text-rose-500">*</span></span>
                                <span class="block text-[10px] text-slate-400 dark:text-slate-500 mt-0.5">Gunakan tag lokasi · Maks 2MB</span>
                            </div>
                        </div>

                        <div id="preview-container-opm_photo" style="display: none;" class="py-2 flex flex-col items-center justify-center">
                            <div class="relative inline-block w-full">
                                <img id="preview-img-opm_photo" class="max-h-36 max-w-full rounded-lg object-contain border border-slate-200 dark:border-slate-700 shadow-xs mx-auto" src="" alt="Preview Foto OPM">
                                <button type="button" onclick="clearFile('opm_photo')" class="absolute -top-2.5 -right-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-full w-6 h-6 flex items-center justify-center shadow-md hover:scale-110 transition-transform focus:outline-none cursor-pointer" title="Hapus File">
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                            <span class="block text-[10px] font-bold text-emerald-600 dark:text-emerald-400 mt-2">✓ Foto OPM Terpilih</span>
                        </div>

                        <div class="mt-2">
                            <input type="file" name="opm_photo" id="opm_photo" accept="image/jpeg,image/png,image/webp,image/jpg,.jpg,.jpeg,.png,.webp" class="hidden" onchange="onFileChange('opm_photo')">
                            <label for="opm_photo" class="block w-full text-center bg-sky-600 hover:bg-sky-700 text-white text-[11px] font-semibold py-2 px-3 rounded-lg cursor-pointer transition-colors shadow-xs focus:outline-none">
                                Pilih Foto OPM
                            </label>
                            <span id="file-label-opm_photo" class="block text-[10px] text-slate-400 dark:text-slate-500 text-center mt-1.5 font-mono truncate">Belum ada file</span>
                        </div>
                    </div>

                    {{-- Foto Speedtest --}}
                    <div class="border-2 border-dashed border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/40 hover:border-sky-500 dark:hover:border-sky-400 rounded-xl p-4 text-center transition-all shadow-xs flex flex-col justify-between relative group">
                        <div id="default-placeholder-speedtest_photo" class="py-4 space-y-2">
                            <div class="w-10 h-10 mx-auto rounded-full bg-sky-50 dark:bg-sky-900/40 text-sky-600 dark:text-sky-400 flex items-center justify-center text-lg border border-sky-200 dark:border-sky-800">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/>
                                </svg>
                            </div>
                            <div>
                                <span class="block text-xs font-bold text-slate-800 dark:text-slate-200">FOTO HASIL SPEEDTEST <span class="text-rose-500">*</span></span>
                                <span class="block text-[10px] text-slate-400 dark:text-slate-500 mt-0.5">Screenshot Speedtest · Maks 2MB</span>
                            </div>
                        </div>

                        <div id="preview-container-speedtest_photo" style="display: none;" class="py-2 flex flex-col items-center justify-center">
                            <div class="relative inline-block w-full">
                                <img id="preview-img-speedtest_photo" class="max-h-36 max-w-full rounded-lg object-contain border border-slate-200 dark:border-slate-700 shadow-xs mx-auto" src="" alt="Preview Foto Speedtest">
                                <button type="button" onclick="clearFile('speedtest_photo')" class="absolute -top-2.5 -right-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-full w-6 h-6 flex items-center justify-center shadow-md hover:scale-110 transition-transform focus:outline-none cursor-pointer" title="Hapus File">
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                            <span class="block text-[10px] font-bold text-emerald-600 dark:text-emerald-400 mt-2">✓ Foto Speedtest Terpilih</span>
                        </div>

                        <div class="mt-2">
                            <input type="file" name="speedtest_photo" id="speedtest_photo" accept="image/jpeg,image/png,image/webp,image/jpg,.jpg,.jpeg,.png,.webp" class="hidden" onchange="onFileChange('speedtest_photo')">
                            <label for="speedtest_photo" class="block w-full text-center bg-sky-600 hover:bg-sky-700 text-white text-[11px] font-semibold py-2 px-3 rounded-lg cursor-pointer transition-colors shadow-xs focus:outline-none">
                                Pilih Foto Speedtest
                            </label>
                            <span id="file-label-speedtest_photo" class="block text-[10px] text-slate-400 dark:text-slate-500 text-center mt-1.5 font-mono truncate">Belum ada file</span>
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
function onFileChange(fieldId) {
    const input = document.getElementById(fieldId);
    const label = document.getElementById('file-label-' + fieldId);
    const defaultPlaceholder = document.getElementById('default-placeholder-' + fieldId);
    const previewContainer = document.getElementById('preview-container-' + fieldId);
    const previewImg = document.getElementById('preview-img-' + fieldId);

    if (input && input.files && input.files.length > 0) {
        const file = input.files[0];
        if (label) label.textContent = file.name;

        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                if (previewImg) previewImg.src = e.target.result;
                if (defaultPlaceholder) defaultPlaceholder.classList.add('hidden');
                if (previewContainer) previewContainer.style.display = 'flex';
            };
            reader.readAsDataURL(file);
        }
    } else {
        if (label) label.textContent = 'Belum ada file';
        if (defaultPlaceholder) defaultPlaceholder.classList.remove('hidden');
        if (previewContainer) previewContainer.style.display = 'none';
        if (previewImg) previewImg.src = '';
    }

    const errBanner = document.getElementById('client-error-banner');
    if (errBanner) errBanner.classList.add('hidden');
}

function clearFile(fieldId) {
    const input = document.getElementById(fieldId);
    if (input) {
        input.value = '';
        onFileChange(fieldId);
    }
}

function showFormError(message, targetElement) {
    const banner = document.getElementById('client-error-banner');
    const textEl = document.getElementById('client-error-text');
    if (banner && textEl) {
        textEl.textContent = message;
        banner.classList.remove('hidden');
    }
    if (targetElement) {
        targetElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
        if (typeof targetElement.focus === 'function') {
            targetElement.focus();
        }
    } else if (banner) {
        banner.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}

// Client validation and loading state on submit
document.getElementById('maintenance-form')?.addEventListener('submit', function(e) {
    const kendalaInput = this.querySelector('textarea[name="kendala_teknis"]');
    const opmInput = document.getElementById('opm_photo');
    const speedtestInput = document.getElementById('speedtest_photo');

    if (!kendalaInput || !kendalaInput.value.trim()) {
        e.preventDefault();
        showFormError('Detail Kendala & Solusi wajib diisi.', kendalaInput);
        return;
    }

    if (!opmInput || !opmInput.files || opmInput.files.length === 0) {
        e.preventDefault();
        showFormError('Foto Hasil OPM wajib dipilih.', document.getElementById('default-placeholder-opm_photo'));
        return;
    }

    if (!speedtestInput || !speedtestInput.files || speedtestInput.files.length === 0) {
        e.preventDefault();
        showFormError('Foto Hasil Speedtest wajib dipilih.', document.getElementById('default-placeholder-speedtest_photo'));
        return;
    }

    const btn = document.getElementById('submit-btn');
    if (btn) {
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
    }
});

// Sisa custody Perangkat Aktif — CUMA tampil kalau SN-nya lagi dipilih
function updateSnStockHint() {
    const select = document.getElementById('selected_inventory_serial_id');
    const hint = document.getElementById('sn-stock-hint');
    if (! select || ! hint) return;

    const opt = select.options[select.selectedIndex];
    const available = opt?.dataset.available;

    if (! opt || ! opt.value || ! available) {
        hint.style.display = 'none';
        hint.textContent = '';
        return;
    }

    hint.textContent = `Sisa custody tim: ${opt.dataset.itemName} (${available} unit)`;
    hint.style.display = '';
}

document.addEventListener('DOMContentLoaded', updateSnStockHint);
</script>
@endsection
