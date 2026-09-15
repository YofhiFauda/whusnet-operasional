@php $agent = $agent ?? null; @endphp

<div class="mb-4">
    <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Kode Agent</label>
    <input type="text" name="code" value="{{ old('code', $agent?->code) }}" maxlength="30" required
           class="w-full px-3 py-2 text-sm border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200">
    @error('code')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
</div>

<div class="mb-4">
    <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Nama Agent</label>
    <input type="text" name="name" value="{{ old('name', $agent?->name) }}" maxlength="150" required
           class="w-full px-3 py-2 text-sm border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200">
    @error('name')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
</div>

<div class="mb-4">
    <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1">Telepon</label>
    <input type="text" name="phone" value="{{ old('phone', $agent?->phone) }}" maxlength="20"
           class="w-full px-3 py-2 text-sm border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/50 dark:bg-slate-900/60 text-slate-800 dark:text-slate-200">
    @error('phone')<p class="text-xs text-rose-600 mt-1">{{ $message }}</p>@enderror
</div>

<div class="mb-6">
    <label class="flex items-center gap-2">
        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $agent?->is_active ?? true) ? 'checked' : '' }}
               class="rounded border-slate-300 text-sky-600 focus:ring-sky-500">
        <span class="text-sm text-slate-700 dark:text-slate-200">Aktif</span>
    </label>
</div>
