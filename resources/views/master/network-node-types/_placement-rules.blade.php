<section class="space-y-3 border-t border-slate-100 pt-5 dark:border-slate-700">
    <input type="hidden" name="rules_configured" value="1">
    <div>
        <h4 class="text-xs font-bold uppercase text-slate-700 dark:text-slate-300">Induk yang Diizinkan</h4>
        <p class="text-[11px] text-slate-500 dark:text-slate-400">Pilih lokasi sah untuk node tipe ini. Tanpa pilihan, penempatan tetap bebas untuk kompatibilitas data lama.</p>
    </div>
    <div class="grid gap-3 sm:grid-cols-2">
        <fieldset class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
            <legend class="px-1 text-xs font-semibold text-slate-700 dark:text-slate-300">Tipe POP langsung</legend>
            @foreach(['pusat' => 'Pusat', 'cabang' => 'Cabang', 'mini_pop' => 'Mini POP / OLT'] as $value => $label)
            <label class="mt-2 flex items-center gap-2 text-xs text-slate-700 dark:text-slate-300">
                <input type="checkbox" name="allowed_anchor_pop_types[]" value="{{ $value }}"
                    @checked(in_array($value, old('allowed_anchor_pop_types', $allowedAnchorPopTypes ?? []), true))>
                {{ $label }}
            </label>
            @endforeach
        </fieldset>
        <fieldset class="rounded-lg border border-slate-200 p-3 dark:border-slate-700">
            <legend class="px-1 text-xs font-semibold text-slate-700 dark:text-slate-300">Tipe node induk</legend>
            @foreach($parentTypes as $parentType)
            <label class="mt-2 flex items-center gap-2 text-xs text-slate-700 dark:text-slate-300">
                <input type="checkbox" name="allowed_parent_type_ids[]" value="{{ $parentType->id }}"
                    @checked(in_array($parentType->id, array_map('intval', old('allowed_parent_type_ids', $allowedParentTypeIds ?? [])), true))>
                {{ $parentType->name }}
            </label>
            @endforeach
        </fieldset>
    </div>
    @error('allowed_parent_type_ids')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
    @error('allowed_anchor_pop_types')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
</section>