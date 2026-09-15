@php
    $isEdit           = isset($user);
    $currentRoleScope = $isEdit ? collect($user->roleScopes)->where('role_id', $user->role_id)->first() : null;
    $currentScopeType = old('scope_type', $currentRoleScope->scope_type->value ?? 'selected_pop');
    $selectedPopIds   = collect(old('pop_ids', $currentRoleScope ? $currentRoleScope->getTargetPopIds() : []))->map(fn ($id) => (int) $id)->all();
@endphp

<div class="grid gap-4 md:grid-cols-2">
    <div>
        <label for="name" class="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Nama</label>
        <input id="name" name="name" type="text" value="{{ old('name', $user->name ?? '') }}"
               class="w-full rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
        @error('name')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="email" class="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email', $user->email ?? '') }}"
               class="w-full rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
        @error('email')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="phone" class="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Phone</label>
        <input id="phone" name="phone" type="text" value="{{ old('phone', $user->phone ?? '') }}"
               class="w-full rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
        @error('phone')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="status" class="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Status</label>
        <select id="status" name="status"
                class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
            <option value="active"   @selected(old('status', $user->status?->value ?? 'active') === 'active')>Aktif</option>
            <option value="inactive" @selected(old('status', $user->status?->value ?? 'active') === 'inactive')>Nonaktif</option>
        </select>
        @error('status')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="role_id" class="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Role</label>
        <select id="role_id" name="role_id"
                class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
            <option value="">Pilih Role</option>
            @foreach($roles as $role)
                <option value="{{ $role->id }}"
                        data-code="{{ $role->code }}"
                        @selected((string) old('role_id', $user->role_id ?? '') === (string) $role->id)>
                    {{ $role->name }}
                </option>
            @endforeach
        </select>
        @error('role_id')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="scope_type" class="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
            Wilayah Kerja (Scope)
        </label>
        <select id="scope_type" name="scope_type"
                class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
            <option value="all_pop"      @selected($currentScopeType === 'all_pop')>Seluruh POP</option>
            <option value="selected_pop" @selected($currentScopeType === 'selected_pop' || $currentScopeType === 'pop_tree')>Cabang POP</option>
        </select>
        <p id="scope_description" class="mt-1 text-xs text-slate-400 dark:text-slate-500"></p>
        @error('scope_type')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
    </div>

    <div class="col-span-1 md:col-span-2 grid gap-4 md:grid-cols-2 pt-2 border-t border-slate-100 dark:border-slate-700/60"
         x-data="{
             showPassword: false,
             showConfirm: false,
             password: '',
             confirmPassword: '',
             get hasMinLength() { return this.password.length >= 8; },
             get hasMixedCase() { return /[A-Z]/.test(this.password) && /[a-z]/.test(this.password); },
             get hasNumber() { return /[0-9]/.test(this.password); },
             get hasSymbol() { return /[^A-Za-z0-9]/.test(this.password); },
             get strengthScore() {
                 let s = 0;
                 if (this.password.length >= 8) s++;
                 if (this.password.length >= 12) s++;
                 if (/[A-Z]/.test(this.password) && /[a-z]/.test(this.password)) s++;
                 if (/[0-9]/.test(this.password)) s++;
                 if (/[^A-Za-z0-9]/.test(this.password)) s++;
                 return Math.min(4, s);
             },
             get strengthLabel() {
                 if (!this.password) return '';
                 const labels = ['Sangat Lemah', 'Lemah', 'Sedang', 'Kuat', 'Sangat Kuat'];
                 return labels[this.strengthScore] || '';
             },
             get strengthColor() {
                 const colors = ['bg-rose-500', 'bg-amber-500', 'bg-yellow-500', 'bg-sky-500', 'bg-emerald-500'];
                 return colors[this.strengthScore] || 'bg-slate-200 dark:bg-slate-700';
             },
             get isMatched() {
                 return this.confirmPassword.length > 0 && this.password === this.confirmPassword;
             }
         }">
        <div>
            <label for="password" class="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                {{ $isEdit ? 'Password Baru' : 'Password' }} @if(!$isEdit)<span class="text-rose-500">*</span>@endif
            </label>
            <div class="relative">
                <input id="password"
                       name="password"
                       :type="showPassword ? 'text' : 'password'"
                       x-model="password"
                       placeholder="Min. 8 karakter, huruf besar/kecil, angka & simbol"
                       autocomplete="new-password"
                       class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900/50 pl-3 pr-10 py-2 text-sm text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500 @error('password') border-rose-500 ring-1 ring-rose-500 @enderror">

                <button type="button"
                        @click="showPassword = !showPassword"
                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 cursor-pointer focus:outline-none"
                        tabindex="-1"
                        title="Tampilkan / sembunyikan password">
                    <svg x-show="!showPassword" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <svg x-show="showPassword" x-cloak class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                    </svg>
                </button>
            </div>
            @error('password')
                <p class="mt-1 text-xs text-rose-600 flex items-center gap-1">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ $message }}
                </p>
            @enderror

            {{-- Strength Meter Bar --}}
            <div x-show="password.length > 0" x-transition.opacity class="mt-2 space-y-1">
                <div class="flex items-center justify-between text-[11px]">
                    <span class="text-slate-400 dark:text-slate-500">Kekuatan Sandi:</span>
                    <span class="font-bold" :class="{
                        'text-rose-500': strengthScore <= 1,
                        'text-amber-500': strengthScore === 2,
                        'text-sky-500': strengthScore === 3,
                        'text-emerald-500': strengthScore === 4
                    }" x-text="strengthLabel"></span>
                </div>
                <div class="h-1.5 w-full bg-slate-100 dark:bg-slate-700 rounded-full overflow-hidden flex gap-1">
                    <div class="h-full rounded-full transition-all duration-300"
                         :class="strengthColor"
                         :style="`width: ${Math.max(15, (strengthScore / 4) * 100)}%`"></div>
                </div>
            </div>

            {{-- Password Criteria Checklist --}}
            <div class="mt-2.5 space-y-1 text-[11px] text-slate-500 dark:text-slate-400" @if($isEdit) x-show="password.length > 0" x-transition.opacity @endif>
                <div class="flex items-center gap-1.5" :class="hasMinLength ? 'text-emerald-600 dark:text-emerald-400 font-medium' : ''">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    <span>Minimal 8 karakter</span>
                </div>
                <div class="flex items-center gap-1.5" :class="hasMixedCase ? 'text-emerald-600 dark:text-emerald-400 font-medium' : ''">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    <span>Huruf besar dan huruf kecil</span>
                </div>
                <div class="flex items-center gap-1.5" :class="hasNumber ? 'text-emerald-600 dark:text-emerald-400 font-medium' : ''">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    <span>Minimal 1 angka</span>
                </div>
                <div class="flex items-center gap-1.5" :class="hasSymbol ? 'text-emerald-600 dark:text-emerald-400 font-medium' : ''">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                    <span>Minimal 1 simbol (!@#$%dst)</span>
                </div>
            </div>
        </div>

        <div>
            <label for="password_confirmation" class="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                {{ $isEdit ? 'Konfirmasi Password Baru' : 'Konfirmasi Password' }} @if(!$isEdit)<span class="text-rose-500">*</span>@endif
            </label>
            <div class="relative">
                <input id="password_confirmation"
                       name="password_confirmation"
                       :type="showConfirm ? 'text' : 'password'"
                       x-model="confirmPassword"
                       placeholder="Ulangi password"
                       autocomplete="new-password"
                       class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900/50 pl-3 pr-10 py-2 text-sm text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">

                <button type="button"
                        @click="showConfirm = !showConfirm"
                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 cursor-pointer focus:outline-none"
                        tabindex="-1"
                        title="Tampilkan / sembunyikan password">
                    <svg x-show="!showConfirm" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <svg x-show="showConfirm" x-cloak class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                    </svg>
                </button>
            </div>

            {{-- Match Status Feedback --}}
            <template x-if="confirmPassword.length > 0">
                <p class="mt-1.5 text-xs flex items-center gap-1 font-medium"
                   :class="isMatched ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="isMatched ? 'M5 13l4 4L19 7' : 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'"/>
                    </svg>
                    <span x-text="isMatched ? 'Konfirmasi password cocok.' : 'Konfirmasi password belum sama.'"></span>
                </p>
            </template>
        </div>
    </div>
</div>

{{-- ============================================================ --}}
{{-- POP TREE PICKER — Tampil hanya jika scope butuh pilih POP    --}}
{{-- ============================================================ --}}
<div id="pop_selection_container" class="border-t border-slate-200 dark:border-slate-700 pt-4">
    <div class="mb-2 flex items-start justify-between gap-3">
        <div>
            <label class="block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                Pilih Cabang / POP Target
            </label>
            <p class="mt-1 text-xs text-slate-400 dark:text-slate-500" id="pop_selection_hint">
                Pilih cabang yang dapat diakses user ini.
            </p>
        </div>
        @error('pop_ids')
            <p class="text-xs text-rose-600 flex-shrink-0">{{ $message }}</p>
        @enderror
    </div>

    <x-ui.pop-tree-picker
        :popTree="$popTree"
        :selected="$selectedPopIds"
        name="pop_ids[]"
        id="pop-tree-user"
    />
</div>

{{-- ============================================================ --}}
{{-- MODAL PREVIEW ACCESS                                         --}}
{{-- ============================================================ --}}
<div id="previewAccessModal"
     class="fixed inset-0 z-50 hidden overflow-y-auto"
     aria-labelledby="preview-modal-title"
     role="dialog"
     aria-modal="true">
    <!-- Backdrop overlay -->
    <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" aria-hidden="true" onclick="closePreviewModal()"></div>

    <div class="flex min-h-screen items-end justify-center px-4 pb-20 pt-4 text-center sm:block sm:p-0">
        <span class="hidden sm:inline-block sm:h-screen sm:align-middle" aria-hidden="true">&#8203;</span>
        <div class="inline-block transform overflow-hidden rounded-lg bg-white dark:bg-slate-800 text-left align-bottom shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-2xl sm:align-middle relative z-10">
            <div class="bg-white dark:bg-slate-800 px-6 pb-4 pt-5">
                <h3 class="text-lg font-semibold text-slate-900 dark:text-slate-100" id="preview-modal-title">Review Konfigurasi Akses</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Periksa kembali tingkat akses dan wilayah data sebelum menyimpan.</p>

                <div class="mt-4 rounded-md bg-slate-50 dark:bg-slate-800/50 p-4 border border-slate-200 dark:border-slate-700">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <span class="block text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Role</span>
                            <span class="block text-sm font-medium text-slate-900 dark:text-slate-100" id="previewRoleName">—</span>
                        </div>
                        <div>
                            <span class="block text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Scope Data</span>
                            <span class="block text-sm font-medium text-slate-900 dark:text-slate-100" id="previewScopeLabel">—</span>
                        </div>
                        <div class="col-span-2" id="previewPopContainer" style="display:none;">
                            <span class="block text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Wilayah Cabang (POP)</span>
                            <span class="block text-sm font-medium text-slate-900 dark:text-slate-100" id="previewPopNames">—</span>
                        </div>
                    </div>
                </div>

                <div id="previewWarningAllPop" class="mt-3 rounded-md bg-rose-50 p-3 border border-rose-200" style="display:none;">
                    <p class="text-sm font-medium text-rose-800">Peringatan: Akses Seluruh POP</p>
                    <p class="mt-0.5 text-xs text-rose-700">User ini akan memiliki akses ke seluruh data POP/Cabang tanpa batasan wilayah.</p>
                </div>

                <div class="mt-4">
                    <h4 class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2 border-b pb-1">Akses Modul</h4>
                    <ul class="list-disc pl-5 text-sm text-slate-600 dark:text-slate-400 space-y-1 max-h-40 overflow-y-auto" id="previewFeaturesList"></ul>
                </div>

                <div id="previewSensitiveContainer" class="mt-3" style="display:none;">
                    <h4 class="text-sm font-semibold text-amber-600 dark:text-amber-400 mb-2 border-b border-amber-200 dark:border-amber-800/50 pb-1">Akses Sensitif</h4>
                    <ul class="list-disc pl-5 text-sm text-amber-700 dark:text-amber-400 space-y-1" id="previewSensitiveList"></ul>
                </div>
            </div>

            <div class="bg-slate-50 dark:bg-slate-800/50 px-6 py-3 flex justify-end gap-2 border-t border-slate-200 dark:border-slate-700">
                <button type="button" onclick="closePreviewModal()"
                        class="rounded-md border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 dark:bg-slate-800/50">
                    Batal
                </button>
                <button type="button" onclick="submitForm()"
                        class="rounded-md bg-sky-600 dark:bg-sky-500 px-4 py-2 text-sm font-medium text-white hover:bg-sky-700 dark:hover:bg-sky-600">
                    Konfirmasi & Simpan
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const roleSelect   = document.getElementById('role_id');
    const scopeSelect  = document.getElementById('scope_type');
    const popContainer = document.getElementById('pop_selection_container');
    const scopeDesc    = document.getElementById('scope_description');
    const popHint      = document.getElementById('pop_selection_hint');

    // Deskripsi tiap scope
    const scopeDescriptions = {
        'all_pop':      'Akses ke seluruh POP tanpa batasan wilayah.',
        'selected_pop': 'Akses ke Cabang POP yang dipilih beserta Mini POP dan semua distribusi di bawahnya secara otomatis.',
    };

    // Hint teks pada pilihan POP per scope
    const popHints = {
        'selected_pop': 'Pilih Cabang POP. Mini POP dan semua distribusi di bawah cabang yang dipilih akan otomatis tercakup.',
    };

    // Scope yang diizinkan per role code
    const validScopes = {
        'owner':     ['all_pop'],
        'atasan':    ['all_pop'],
        'admin':     ['all_pop', 'selected_pop'],
        'noc':       ['all_pop', 'selected_pop'],
        'helpdesk':  ['selected_pop'],
        'fop':       ['selected_pop'],
        'teknisi':   ['selected_pop'],
        'sales':     ['selected_pop'],
        'pop_admin': ['selected_pop'],
    };

    function updateScopeOptions() {
        const roleCode      = roleSelect.options[roleSelect.selectedIndex]?.getAttribute('data-code') ?? '';
        const allowedScopes = validScopes[roleCode] ?? ['all_pop', 'selected_pop'];

        for (const opt of scopeSelect.options) {
            const allowed = allowedScopes.includes(opt.value);
            opt.style.display = allowed ? '' : 'none';
            opt.disabled = !allowed;
        }

        // Jika nilai saat ini tidak lagi valid, pilih yang pertama valid
        if (scopeSelect.options[scopeSelect.selectedIndex]?.disabled) {
            for (const opt of scopeSelect.options) {
                if (!opt.disabled) { opt.selected = true; break; }
            }
        }

        updatePopContainer();
    }

    function updatePopContainer() {
        const scope    = scopeSelect.value;
        const needsPop = scope === 'selected_pop';

        // Tampilkan/sembunyikan POP picker
        popContainer.style.display = needsPop ? 'block' : 'none';

        // Update deskripsi scope
        if (scopeDesc) {
            scopeDesc.textContent = scopeDescriptions[scope] ?? '';
        }

        // Update hint pada POP picker
        if (popHint && needsPop) {
            popHint.textContent = popHints[scope] ?? '';
        }
    }

    roleSelect.addEventListener('change', updateScopeOptions);
    scopeSelect.addEventListener('change', updatePopContainer);

    // Init
    updateScopeOptions();
});

// ---- Preview Access Modal ----
window.openPreviewModal = function () {
    const roleId    = document.getElementById('role_id').value;
    const scopeType = document.getElementById('scope_type').value;
    const popIds    = [...document.querySelectorAll('input[name="pop_ids[]"]:checked')].map(el => el.value);

    if (!roleId) {
        Toast.warning('Pilih Role', 'Silakan pilih Role terlebih dahulu.');
        return;
    }

    const btn = document.getElementById('btnReviewAccess');
    if (btn) { btn.disabled = true; btn.textContent = 'Memuat...'; }

    fetch('{{ route("users.preview-access") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify({ role_id: roleId, scope_type: scopeType, pop_ids: popIds }),
    })
    .then(r => r.json())
    .then(data => {
        if (btn) { btn.disabled = false; btn.textContent = 'Review Access'; }
        if (data.error) { Toast.error('Error', data.error); return; }

        document.getElementById('previewRoleName').textContent  = data.role_name;
        document.getElementById('previewScopeLabel').textContent = data.scope_label;

        const popBox = document.getElementById('previewPopContainer');
        if (data.pops?.length) {
            popBox.style.display = 'block';
            document.getElementById('previewPopNames').textContent = data.pops.join(', ');
        } else {
            popBox.style.display = 'none';
        }

        document.getElementById('previewWarningAllPop').style.display = data.scope_type === 'all_pop' ? 'block' : 'none';

        const fl = document.getElementById('previewFeaturesList');
        fl.innerHTML = '';
        (data.features ?? []).forEach(f => { const li = document.createElement('li'); li.textContent = f; fl.appendChild(li); });

        const sc = document.getElementById('previewSensitiveContainer');
        const sl = document.getElementById('previewSensitiveList');
        if (data.sensitive_actions?.length) {
            sc.style.display = 'block';
            sl.innerHTML = '';
            data.sensitive_actions.forEach(sa => { const li = document.createElement('li'); li.textContent = sa; sl.appendChild(li); });
        } else {
            sc.style.display = 'none';
        }

        document.getElementById('previewAccessModal').classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    })
    .catch(() => {
        if (btn) { btn.disabled = false; btn.textContent = 'Review Access'; }
        Toast.error('Error', 'Gagal memuat preview akses.');
    });
};

window.closePreviewModal = function () {
    document.getElementById('previewAccessModal').classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
};

window.submitForm = function () {
    document.getElementById('userForm').submit();
};

// Close modal on Escape key press
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('previewAccessModal');
        if (modal && !modal.classList.contains('hidden')) {
            closePreviewModal();
        }
    }
});
</script>
