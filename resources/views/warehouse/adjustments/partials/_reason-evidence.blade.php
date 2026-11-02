{{--
    Bagian "alasan + catatan + bukti foto" bersama semua form koreksi gudang.
    Satu tempat supaya aturan bukti (kontrol-anti-manipulasi.md §2) gak
    menyimpang antar form — dulu tiap form menyalin markup + teks sendiri.
    Nama field tetap `reason` / `notes` / `evidence` seperti endpoint lama;
    penegak sebenarnya tetap validasi controller + InventoryAdjustmentService.

    Parameter (semua opsional):
      withReason (bool, true)      reasonLabel, reasonPlaceholder, reasonMax (255)
      reasonMultiline (bool)       suggestions (array<string>) → datalist
      reasonValue (?string)
      withNotes (bool, true)       notesPlaceholder
      evidence: 'none' | 'required' | 'when'
      evidenceWhen (?string)       ekspresi Alpine, dipakai kalau evidence = 'when'
      evidenceLabel, evidenceHint, idPrefix (unik per include)
--}}
@php
    $idPrefix = $idPrefix ?? 'adj';
    $withReason = $withReason ?? true;
    $withNotes = $withNotes ?? true;
    $evidence = $evidence ?? 'none';
    $suggestions = $suggestions ?? [];
    $reasonMax = $reasonMax ?? 255;
    $reasonLabel = $reasonLabel ?? 'Alasan';
    $inputClass = 'w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs text-slate-800 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100';
@endphp

@if($withReason)
<div>
    <label for="{{ $idPrefix }}-reason" class="mb-1.5 block text-xs font-bold text-slate-700 dark:text-slate-200">{{ $reasonLabel }} <span class="text-rose-500">*</span></label>
    @if(! empty($reasonMultiline))
        <textarea id="{{ $idPrefix }}-reason" name="reason" rows="2" required maxlength="{{ $reasonMax }}" placeholder="{{ $reasonPlaceholder ?? '' }}" class="{{ $inputClass }}">{{ old('reason', $reasonValue ?? '') }}</textarea>
    @else
        <input id="{{ $idPrefix }}-reason" type="text" name="reason" required maxlength="{{ $reasonMax }}" value="{{ old('reason', $reasonValue ?? '') }}"
               placeholder="{{ $reasonPlaceholder ?? '' }}" @if($suggestions) list="{{ $idPrefix }}-reason-suggestions" @endif class="{{ $inputClass }}">
        @if($suggestions)
        <datalist id="{{ $idPrefix }}-reason-suggestions">
            @foreach($suggestions as $suggestion)
            <option value="{{ $suggestion }}">
            @endforeach
        </datalist>
        @endif
    @endif
    @error('reason') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
</div>
@endif

@if($withNotes)
<div>
    <label for="{{ $idPrefix }}-notes" class="mb-1.5 block text-xs font-bold text-slate-700 dark:text-slate-200">Catatan Tambahan (Opsional)</label>
    <textarea id="{{ $idPrefix }}-notes" name="notes" rows="2" maxlength="500" placeholder="{{ $notesPlaceholder ?? 'Keterangan tambahan…' }}" class="{{ $inputClass }}">{{ old('notes') }}</textarea>
    @error('notes') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
</div>
@endif

@if($evidence !== 'none')
<div @if($evidence === 'when') x-show="{{ $evidenceWhen }}" x-cloak @endif>
    <label for="{{ $idPrefix }}-evidence" class="mb-1.5 block text-xs font-bold text-slate-700 dark:text-slate-200">
        {{ $evidenceLabel ?? 'Bukti Foto' }} <span class="text-rose-500">*</span>
    </label>
    <input id="{{ $idPrefix }}-evidence" type="file" name="evidence" accept="image/*"
           @if($evidence === 'required') required @elseif($evidence === 'when') :required="{{ $evidenceWhen }}" @endif
           class="w-full rounded-lg border border-slate-200 bg-slate-50/50 px-3 py-2 text-xs text-slate-800 file:mr-3 file:rounded-lg file:border-0 file:bg-amber-100 file:px-3 file:py-1 file:text-xs file:font-semibold file:text-amber-700 dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-200 dark:file:bg-amber-900/40 dark:file:text-amber-300">
    @if(! empty($evidenceHint)) <p class="mt-1 text-[11px] text-slate-400">{{ $evidenceHint }}</p> @endif
    @error('evidence') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
</div>
@endif
