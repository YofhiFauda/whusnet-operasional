<?php
    $isEdit           = isset($user);
    $currentRoleScope = $isEdit ? collect($user->roleScopes)->where('role_id', $user->role_id)->first() : null;
    $currentScopeType = old('scope_type', $currentRoleScope->scope_type->value ?? 'selected_pop');
    $selectedPopIds   = collect(old('pop_ids', $currentRoleScope ? $currentRoleScope->getTargetPopIds() : []))->map(fn ($id) => (int) $id)->all();
?>

<div class="grid gap-4 md:grid-cols-2">
    <div>
        <label for="name" class="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Nama</label>
        <input id="name" name="name" type="text" value="<?php echo e(old('name', $user->name ?? '')); ?>"
               class="w-full rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
        <?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="mt-1 text-xs text-rose-600"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
    </div>

    <div>
        <label for="email" class="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Email</label>
        <input id="email" name="email" type="email" value="<?php echo e(old('email', $user->email ?? '')); ?>"
               class="w-full rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
        <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="mt-1 text-xs text-rose-600"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
    </div>

    <div>
        <label for="phone" class="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Phone</label>
        <input id="phone" name="phone" type="text" value="<?php echo e(old('phone', $user->phone ?? '')); ?>"
               class="w-full rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
        <?php $__errorArgs = ['phone'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="mt-1 text-xs text-rose-600"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
    </div>

    <div>
        <label for="status" class="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Status</label>
        <select id="status" name="status"
                class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
            <option value="active"   <?php if(old('status', $user->status?->value ?? 'active') === 'active'): echo 'selected'; endif; ?>>Aktif</option>
            <option value="inactive" <?php if(old('status', $user->status?->value ?? 'active') === 'inactive'): echo 'selected'; endif; ?>>Nonaktif</option>
        </select>
        <?php $__errorArgs = ['status'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="mt-1 text-xs text-rose-600"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
    </div>

    <div>
        <label for="role_id" class="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Role</label>
        <select id="role_id" name="role_id"
                class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
            <option value="">Pilih Role</option>
            <?php $__currentLoopData = $roles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $role): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($role->id); ?>"
                        data-code="<?php echo e($role->code); ?>"
                        <?php if((string) old('role_id', $user->role_id ?? '') === (string) $role->id): echo 'selected'; endif; ?>>
                    <?php echo e($role->name); ?>

                </option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>
        <?php $__errorArgs = ['role_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="mt-1 text-xs text-rose-600"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
    </div>

    <div>
        <label for="scope_type" class="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
            Wilayah Kerja (Scope)
        </label>
        <select id="scope_type" name="scope_type"
                class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
            <option value="all_pop"      <?php if($currentScopeType === 'all_pop'): echo 'selected'; endif; ?>>Seluruh POP</option>
            <option value="selected_pop" <?php if($currentScopeType === 'selected_pop' || $currentScopeType === 'pop_tree'): echo 'selected'; endif; ?>>Cabang POP</option>
        </select>
        <p id="scope_description" class="mt-1 text-xs text-slate-400 dark:text-slate-500"></p>
        <?php $__errorArgs = ['scope_type'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="mt-1 text-xs text-rose-600"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
    </div>

    <div>
        <label for="password" class="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
            <?php echo e($isEdit ? 'Password Baru' : 'Password'); ?>

        </label>
        <input id="password" name="password" type="password"
               class="w-full rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
        <?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="mt-1 text-xs text-rose-600"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
    </div>

    <div>
        <label for="password_confirmation" class="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
            <?php echo e($isEdit ? 'Konfirmasi Password Baru' : 'Konfirmasi Password'); ?>

        </label>
        <input id="password_confirmation" name="password_confirmation" type="password"
               class="w-full rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
    </div>
</div>




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
        <?php $__errorArgs = ['pop_ids'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
            <p class="text-xs text-rose-600 flex-shrink-0"><?php echo e($message); ?></p>
        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
    </div>

    <?php if (isset($component)) { $__componentOriginalbad9adfbc10a9231976d70e8bb986d58 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalbad9adfbc10a9231976d70e8bb986d58 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.pop-tree-picker','data' => ['popTree' => $popTree,'selected' => $selectedPopIds,'name' => 'pop_ids[]','id' => 'pop-tree-user']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.pop-tree-picker'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['popTree' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($popTree),'selected' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($selectedPopIds),'name' => 'pop_ids[]','id' => 'pop-tree-user']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalbad9adfbc10a9231976d70e8bb986d58)): ?>
<?php $attributes = $__attributesOriginalbad9adfbc10a9231976d70e8bb986d58; ?>
<?php unset($__attributesOriginalbad9adfbc10a9231976d70e8bb986d58); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalbad9adfbc10a9231976d70e8bb986d58)): ?>
<?php $component = $__componentOriginalbad9adfbc10a9231976d70e8bb986d58; ?>
<?php unset($__componentOriginalbad9adfbc10a9231976d70e8bb986d58); ?>
<?php endif; ?>
</div>




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

    fetch('<?php echo e(route("users.preview-access")); ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>' },
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
<?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/users/_form.blade.php ENDPATH**/ ?>