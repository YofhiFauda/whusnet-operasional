<?php $__env->startSection('title', 'Assign POP — ' . $user->name); ?>
<?php $__env->startSection('page_title', 'Assign POP: ' . $user->name); ?>

<?php $__env->startSection('content'); ?>

<div class="mb-4">
    <a href="<?php echo e(route('users.index')); ?>"
       class="inline-flex items-center gap-1 text-sm font-medium text-primary hover:text-primary-hover transition-colors">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        Kembali ke Daftar User
    </a>
</div>

<div class="max-w-2xl">
    
    <div class="mb-4 rounded-md border border-border bg-surface-muted px-4 py-3 flex items-start gap-3">
        <div class="h-9 w-9 rounded-full bg-primary/10 flex items-center justify-center font-bold text-primary flex-shrink-0 text-sm">
            <?php echo e(strtoupper(substr($user->name, 0, 2))); ?>

        </div>
        <div class="min-w-0">
            <p class="text-sm font-semibold text-text-main"><?php echo e($user->name); ?></p>
            <p class="text-xs text-text-muted"><?php echo e($user->email); ?></p>
            <p class="text-xs text-text-muted mt-0.5">
                Role: <span class="font-medium text-text-secondary"><?php echo e($user->role?->name ?? '—'); ?></span>
            </p>
        </div>
        <?php if($user->hasFullAccess()): ?>
            <div class="ml-auto flex-shrink-0">
                <span class="inline-flex items-center rounded-full bg-warning-bg border border-warning-border px-2.5 py-0.5 text-xs font-semibold text-warning">
                    Akses Penuh
                </span>
            </div>
        <?php endif; ?>
    </div>

    <?php if($user->hasFullAccess()): ?>
        <div class="mb-4 rounded-md bg-info-bg border border-info-border px-4 py-3 text-sm text-text-secondary">
            <p>
                User ini memiliki role <strong><?php echo e($user->role->name); ?></strong> dengan akses penuh ke semua POP.
                Pilihan di bawah disimpan sebagai referensi data saja dan tidak membatasi aksesnya.
            </p>
        </div>
    <?php endif; ?>

    <form action="<?php echo e(route('users.pops.update', $user)); ?>" method="POST">
        <?php echo csrf_field(); ?>
        <?php echo method_field('PUT'); ?>

        <?php if (isset($component)) { $__componentOriginaldae4cd48acb67888a4631e1ba48f2f93 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.card','data' => ['padding' => 'compact']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.card'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['padding' => 'compact']); ?>
             <?php $__env->slot('header', null, []); ?> 
                <div>
                    <h2 class="text-md font-semibold text-text-main">Pilih POP / Cabang</h2>
                    <p class="mt-0.5 text-sm text-text-muted">
                        Pilih cabang yang dapat diakses berdasarkan hierarki.
                        Memilih POP induk akan otomatis mencakup semua sub-POP di bawahnya.
                    </p>
                </div>
             <?php $__env->endSlot(); ?>

            <?php
                $assignedPopIds = $user->pops->pluck('id')->map(fn($id) => (int)$id)->all();
            ?>

            <?php if (isset($component)) { $__componentOriginalbad9adfbc10a9231976d70e8bb986d58 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalbad9adfbc10a9231976d70e8bb986d58 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.pop-tree-picker','data' => ['popTree' => $popTree,'selected' => $assignedPopIds,'name' => 'pop_ids[]','id' => 'pop-tree-edit']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.pop-tree-picker'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['popTree' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($popTree),'selected' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($assignedPopIds),'name' => 'pop_ids[]','id' => 'pop-tree-edit']); ?>
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

            <?php $__errorArgs = ['pop_ids'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                <p class="mt-2 text-xs text-error"><?php echo e($message); ?></p>
            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>

             <?php $__env->slot('footer', null, []); ?> 
                <div class="flex justify-end gap-2">
                    <a href="<?php echo e(route('users.index')); ?>" class="btn-secondary">Batal</a>
                    <button type="submit" class="btn-primary">Simpan Penugasan</button>
                </div>
             <?php $__env->endSlot(); ?>
         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93)): ?>
<?php $attributes = $__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93; ?>
<?php unset($__attributesOriginaldae4cd48acb67888a4631e1ba48f2f93); ?>
<?php endif; ?>
<?php if (isset($__componentOriginaldae4cd48acb67888a4631e1ba48f2f93)): ?>
<?php $component = $__componentOriginaldae4cd48acb67888a4631e1ba48f2f93; ?>
<?php unset($__componentOriginaldae4cd48acb67888a4631e1ba48f2f93); ?>
<?php endif; ?>
    </form>
</div>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/users/edit_pops.blade.php ENDPATH**/ ?>