<?php $__env->startSection('title', 'Manajemen Role & Permission'); ?>
<?php $__env->startSection('page_title', 'Manajemen Role & Permission'); ?>

<?php $__env->startSection('content'); ?>

<?php
    $userRoleCode     = auth()->user()->role?->code;
    $managementScope  = config('rbac.role_management_scope', []);
    $canManageRoles   = $managementScope[$userRoleCode] ?? [];
    $isOwner          = $userRoleCode === 'owner';
    $canCreateRole    = \App\Models\Role::canBeCreatedBy(auth()->user());
?>


<div class="mb-4 flex items-center justify-between gap-4">
    <div>
        <p class="text-sm text-text-muted">
            Kelola master data jabatan (Role) dan hak akses (Permission) pengguna sistem.
        </p>
    </div>
    <?php if(auth()->user()->hasPermission('roles.create') && $canCreateRole): ?>
        <?php if (isset($component)) { $__componentOriginala8bb031a483a05f647cb99ed3a469847 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala8bb031a483a05f647cb99ed3a469847 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.button','data' => ['type' => 'button','variant' => 'primary','onclick' => 'openCreateModal()','ariaLabel' => 'Tambah role baru']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'button','variant' => 'primary','onclick' => 'openCreateModal()','aria-label' => 'Tambah role baru']); ?>
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Tambah Role
         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginala8bb031a483a05f647cb99ed3a469847)): ?>
<?php $attributes = $__attributesOriginala8bb031a483a05f647cb99ed3a469847; ?>
<?php unset($__attributesOriginala8bb031a483a05f647cb99ed3a469847); ?>
<?php endif; ?>
<?php if (isset($__componentOriginala8bb031a483a05f647cb99ed3a469847)): ?>
<?php $component = $__componentOriginala8bb031a483a05f647cb99ed3a469847; ?>
<?php unset($__componentOriginala8bb031a483a05f647cb99ed3a469847); ?>
<?php endif; ?>
    <?php endif; ?>
</div>


<div class="table-wrapper">
    <table class="table">
        <thead>
            <tr>
                <th>Nama Role</th>
                <th>Kode</th>
                <th>Deskripsi</th>
                <th class="text-center">Users</th>
                <th class="text-center">Permissions</th>
                <th class="text-center">Tipe</th>
                <th class="text-right pr-4">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php $__empty_1 = true; $__currentLoopData = $roles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $role): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <?php $canManage = $role->canBeManagedBy(auth()->user()); ?>
            <tr class="<?php echo e(!$canManage && !$isOwner ? 'opacity-60' : ''); ?>">

                
                <td class="font-medium text-text-main">
                    <div class="flex items-center gap-2">
                        <span><?php echo e($role->name); ?></span>
                        <!-- <?php if($role->isOwner()): ?>
                            <?php if (isset($component)) { $__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.badge','data' => ['variant' => 'warning']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'warning']); ?>
                                <svg class="h-3 w-3 mr-0.5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                </svg>
                                Owner
                             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4)): ?>
<?php $attributes = $__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4; ?>
<?php unset($__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4)): ?>
<?php $component = $__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4; ?>
<?php unset($__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4); ?>
<?php endif; ?>
                        <?php elseif($role->is_system): ?>
                            <?php if (isset($component)) { $__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.badge','data' => ['variant' => 'neutral']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'neutral']); ?>Sistem <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4)): ?>
<?php $attributes = $__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4; ?>
<?php unset($__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4)): ?>
<?php $component = $__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4; ?>
<?php unset($__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4); ?>
<?php endif; ?>
                        <?php endif; ?> -->
                    </div>
                </td>

                
                <td>
                    <span class="data-text text-xs text-text-secondary"><?php echo e($role->code); ?></span>
                </td>

                
                <td class="text-sm text-text-secondary max-w-xs truncate">
                    <?php echo e($role->description ?: '—'); ?>

                </td>

                
                <td class="text-center">
                    <span class="data-text text-sm font-medium text-text-main"><?php echo e($role->users_count); ?></span>
                </td>

                
                <td class="text-center">
                    <?php if($role->isOwner()): ?>
                        <span class="text-xs text-warning font-medium">Semua</span>
                    <?php else: ?>
                        <span class="data-text text-sm font-medium text-text-main"><?php echo e($role->permissions_count); ?></span>
                    <?php endif; ?>
                </td>

                
                <td class="text-center">
                    <?php if($role->isProtected()): ?>
                        <?php if (isset($component)) { $__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.badge','data' => ['variant' => 'error']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'error']); ?>
                            <svg class="h-3 w-3 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                            Dilindungi
                         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4)): ?>
<?php $attributes = $__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4; ?>
<?php unset($__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4)): ?>
<?php $component = $__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4; ?>
<?php unset($__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4); ?>
<?php endif; ?>
                    <?php elseif($role->is_system): ?>
                        <?php if (isset($component)) { $__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.badge','data' => ['variant' => 'info']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'info']); ?>Sistem <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4)): ?>
<?php $attributes = $__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4; ?>
<?php unset($__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4)): ?>
<?php $component = $__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4; ?>
<?php unset($__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4); ?>
<?php endif; ?>
                    <?php else: ?>
                        <?php if (isset($component)) { $__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.badge','data' => ['variant' => 'neutral']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'neutral']); ?>Custom <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4)): ?>
<?php $attributes = $__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4; ?>
<?php unset($__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4)): ?>
<?php $component = $__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4; ?>
<?php unset($__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4); ?>
<?php endif; ?>
                    <?php endif; ?>
                </td>

                
                <td class="text-right pr-4">
                    <div class="flex items-center justify-end gap-3">

                        
                        <?php if($role->isOwner()): ?>
                            <span class="text-xs text-text-muted italic">Full Access</span>
                        <?php elseif($canManage && auth()->user()->hasPermission('roles.update')): ?>
                            <a href="<?php echo e(route('roles.matrix', $role)); ?>"
                               class="text-sm font-medium text-primary hover:text-primary-hover transition-colors"
                               title="Atur permission role <?php echo e($role->name); ?>">
                                Matrix
                            </a>
                        <?php endif; ?>

                        
                        <?php if($canManage && auth()->user()->hasPermission('roles.update')): ?>
                            <button
                                type="button"
                                onclick="openEditModal(<?php echo e($role->id); ?>, '<?php echo e(addslashes($role->name)); ?>', '<?php echo e(addslashes($role->code)); ?>', '<?php echo e(addslashes($role->description ?? '')); ?>', <?php echo e($role->is_system ? 'true' : 'false'); ?>)"
                                class="text-sm font-medium text-success hover:opacity-80 transition-opacity"
                                title="Edit role <?php echo e($role->name); ?>"
                            >
                                Edit
                            </button>
                        <?php endif; ?>

                        
                        <?php if($canManage && auth()->user()->hasPermission('roles.delete') && !$role->isProtected()): ?>
                            <form
                                action="<?php echo e(route('roles.destroy', $role)); ?>"
                                method="POST"
                                class="inline"
                                onsubmit="return confirmRoleDestroy(event, this, '<?php echo e(addslashes($role->name)); ?>', <?php echo e($role->users_count); ?>)"
                            >
                                <?php echo csrf_field(); ?>
                                <?php echo method_field('DELETE'); ?>
                                <button type="submit"
                                        class="text-sm font-medium text-error hover:opacity-80 transition-opacity"
                                        title="Hapus role <?php echo e($role->name); ?>">
                                    Hapus
                                </button>
                            </form>
                        <?php endif; ?>

                    </div>
                </td>
            </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <tr>
                <td colspan="7" class="py-16 text-center">
                    <div class="flex flex-col items-center gap-3 text-text-muted">
                        <svg class="h-10 w-10 text-text-disabled" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        <p class="text-sm font-medium">Belum ada role terdaftar</p>
                        <?php if($canCreateRole): ?>
                            <?php if (isset($component)) { $__componentOriginala8bb031a483a05f647cb99ed3a469847 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala8bb031a483a05f647cb99ed3a469847 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.button','data' => ['type' => 'button','variant' => 'secondary','onclick' => 'openCreateModal()']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'button','variant' => 'secondary','onclick' => 'openCreateModal()']); ?>
                                Tambah Role Pertama
                             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginala8bb031a483a05f647cb99ed3a469847)): ?>
<?php $attributes = $__attributesOriginala8bb031a483a05f647cb99ed3a469847; ?>
<?php unset($__attributesOriginala8bb031a483a05f647cb99ed3a469847); ?>
<?php endif; ?>
<?php if (isset($__componentOriginala8bb031a483a05f647cb99ed3a469847)): ?>
<?php $component = $__componentOriginala8bb031a483a05f647cb99ed3a469847; ?>
<?php unset($__componentOriginala8bb031a483a05f647cb99ed3a469847); ?>
<?php endif; ?>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>




<?php if (isset($component)) { $__componentOriginal7762953202be6518eecd1cfbd075bf2f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal7762953202be6518eecd1cfbd075bf2f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.modal','data' => ['name' => 'role-modal','title' => 'Form Role','maxWidth' => 'md']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.modal'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'role-modal','title' => 'Form Role','maxWidth' => 'md']); ?>
    <form id="roleForm" method="POST" action="<?php echo e(route('roles.store')); ?>" novalidate>
        <?php echo csrf_field(); ?>
        <input type="hidden" name="_method" id="formMethod" value="POST">

        <div class="space-y-4">

            
            <div class="flex flex-col gap-1.5">
                <label for="roleName" class="text-sm font-medium text-text-main">
                    Nama Role
                    <span class="text-error ml-0.5" aria-hidden="true">*</span>
                </label>
                <?php if (isset($component)) { $__componentOriginal65bd7e7dbd93cec773ad6501ce127e46 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal65bd7e7dbd93cec773ad6501ce127e46 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.input','data' => ['type' => 'text','name' => 'name','id' => 'roleName','required' => true,'placeholder' => 'Contoh: Supervisor','autocomplete' => 'off','error' => $errors->has('name')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'text','name' => 'name','id' => 'roleName','required' => true,'placeholder' => 'Contoh: Supervisor','autocomplete' => 'off','error' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($errors->has('name'))]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal65bd7e7dbd93cec773ad6501ce127e46)): ?>
<?php $attributes = $__attributesOriginal65bd7e7dbd93cec773ad6501ce127e46; ?>
<?php unset($__attributesOriginal65bd7e7dbd93cec773ad6501ce127e46); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal65bd7e7dbd93cec773ad6501ce127e46)): ?>
<?php $component = $__componentOriginal65bd7e7dbd93cec773ad6501ce127e46; ?>
<?php unset($__componentOriginal65bd7e7dbd93cec773ad6501ce127e46); ?>
<?php endif; ?>
                <?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                    <p class="text-xs font-medium text-error" role="alert"><?php echo e($message); ?></p>
                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                <p class="text-xs text-text-muted">Nama yang tampil untuk user dan dalam laporan.</p>
            </div>

            
            <div class="flex flex-col gap-1.5">
                <label for="roleCode" class="text-sm font-medium text-text-main">
                    Kode Role
                    <span class="text-error ml-0.5" aria-hidden="true">*</span>
                </label>
                <?php if (isset($component)) { $__componentOriginal65bd7e7dbd93cec773ad6501ce127e46 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal65bd7e7dbd93cec773ad6501ce127e46 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.input','data' => ['type' => 'text','name' => 'code','id' => 'roleCode','required' => true,'placeholder' => 'Contoh: supervisor','pattern' => '[a-z0-9_]+','autocomplete' => 'off','error' => $errors->has('code')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'text','name' => 'code','id' => 'roleCode','required' => true,'placeholder' => 'Contoh: supervisor','pattern' => '[a-z0-9_]+','autocomplete' => 'off','error' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($errors->has('code'))]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal65bd7e7dbd93cec773ad6501ce127e46)): ?>
<?php $attributes = $__attributesOriginal65bd7e7dbd93cec773ad6501ce127e46; ?>
<?php unset($__attributesOriginal65bd7e7dbd93cec773ad6501ce127e46); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal65bd7e7dbd93cec773ad6501ce127e46)): ?>
<?php $component = $__componentOriginal65bd7e7dbd93cec773ad6501ce127e46; ?>
<?php unset($__componentOriginal65bd7e7dbd93cec773ad6501ce127e46); ?>
<?php endif; ?>
                <?php $__errorArgs = ['code'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                    <p class="text-xs font-medium text-error" role="alert"><?php echo e($message); ?></p>
                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                <p class="text-xs text-text-muted" id="codeHint">
                    Gunakan huruf kecil, angka, dan underscore saja. Contoh: <span class="data-text">pop_admin</span>
                </p>
            </div>

            
            <div class="flex flex-col gap-1.5">
                <label for="roleDescription" class="text-sm font-medium text-text-main">
                    Deskripsi
                    <span class="text-xs font-normal text-text-muted ml-1">(opsional)</span>
                </label>
                <?php if (isset($component)) { $__componentOriginal62d1193389a71cd99ff302a00abbf991 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal62d1193389a71cd99ff302a00abbf991 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.textarea','data' => ['name' => 'description','id' => 'roleDescription','rows' => '3','placeholder' => 'Jelaskan fungsi dan tanggung jawab role ini...','error' => $errors->has('description')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.textarea'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'description','id' => 'roleDescription','rows' => '3','placeholder' => 'Jelaskan fungsi dan tanggung jawab role ini...','error' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($errors->has('description'))]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal62d1193389a71cd99ff302a00abbf991)): ?>
<?php $attributes = $__attributesOriginal62d1193389a71cd99ff302a00abbf991; ?>
<?php unset($__attributesOriginal62d1193389a71cd99ff302a00abbf991); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal62d1193389a71cd99ff302a00abbf991)): ?>
<?php $component = $__componentOriginal62d1193389a71cd99ff302a00abbf991; ?>
<?php unset($__componentOriginal62d1193389a71cd99ff302a00abbf991); ?>
<?php endif; ?>
                <?php $__errorArgs = ['description'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                    <p class="text-xs font-medium text-error" role="alert"><?php echo e($message); ?></p>
                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>

        </div>

         <?php $__env->slot('footer', null, []); ?> 
            <?php if (isset($component)) { $__componentOriginala8bb031a483a05f647cb99ed3a469847 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala8bb031a483a05f647cb99ed3a469847 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.button','data' => ['type' => 'button','variant' => 'secondary','@click' => 'show = false']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'button','variant' => 'secondary','@click' => 'show = false']); ?>
                Batal
             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginala8bb031a483a05f647cb99ed3a469847)): ?>
<?php $attributes = $__attributesOriginala8bb031a483a05f647cb99ed3a469847; ?>
<?php unset($__attributesOriginala8bb031a483a05f647cb99ed3a469847); ?>
<?php endif; ?>
<?php if (isset($__componentOriginala8bb031a483a05f647cb99ed3a469847)): ?>
<?php $component = $__componentOriginala8bb031a483a05f647cb99ed3a469847; ?>
<?php unset($__componentOriginala8bb031a483a05f647cb99ed3a469847); ?>
<?php endif; ?>
            <?php if (isset($component)) { $__componentOriginala8bb031a483a05f647cb99ed3a469847 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala8bb031a483a05f647cb99ed3a469847 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.button','data' => ['type' => 'submit','variant' => 'primary','id' => 'modalSubmitBtn']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'submit','variant' => 'primary','id' => 'modalSubmitBtn']); ?>
                Simpan Role
             <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginala8bb031a483a05f647cb99ed3a469847)): ?>
<?php $attributes = $__attributesOriginala8bb031a483a05f647cb99ed3a469847; ?>
<?php unset($__attributesOriginala8bb031a483a05f647cb99ed3a469847); ?>
<?php endif; ?>
<?php if (isset($__componentOriginala8bb031a483a05f647cb99ed3a469847)): ?>
<?php $component = $__componentOriginala8bb031a483a05f647cb99ed3a469847; ?>
<?php unset($__componentOriginala8bb031a483a05f647cb99ed3a469847); ?>
<?php endif; ?>
         <?php $__env->endSlot(); ?>
    </form>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal7762953202be6518eecd1cfbd075bf2f)): ?>
<?php $attributes = $__attributesOriginal7762953202be6518eecd1cfbd075bf2f; ?>
<?php unset($__attributesOriginal7762953202be6518eecd1cfbd075bf2f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal7762953202be6518eecd1cfbd075bf2f)): ?>
<?php $component = $__componentOriginal7762953202be6518eecd1cfbd075bf2f; ?>
<?php unset($__componentOriginal7762953202be6518eecd1cfbd075bf2f); ?>
<?php endif; ?>

<?php $__env->startSection('scripts'); ?>
<script>
    // ---- Toast untuk info scope akses (jika role non-Owner dengan scope terbatas) ----
    <?php if(!$isOwner && !empty($canManageRoles)): ?>
    <?php
        $scopeRoleLabels = array_values(array_map(
            function($r) { return ucfirst(str_replace('_', ' ', $r)); },
            $canManageRoles
        ));
    ?>
    document.addEventListener('DOMContentLoaded', () => {
        const roles = <?php echo json_encode($scopeRoleLabels, 15, 512) ?>;
        Toast.info(
            'Akses Terbatas',
            'Anda hanya dapat mengelola role: ' + roles.join(', ') + '.',
            6000
        );
    });
    <?php endif; ?>
    function openCreateModal() {
        const form = document.getElementById('roleForm');
        form.action = '<?php echo e(route("roles.store")); ?>';
        document.getElementById('formMethod').value = 'POST';

        // Reset semua field
        document.getElementById('roleName').value = '';
        document.getElementById('roleCode').value = '';
        document.getElementById('roleCode').disabled = false;
        document.getElementById('roleDescription').value = '';

        // Reset hint & error states
        document.getElementById('codeHint').textContent =
            'Gunakan huruf kecil, angka, dan underscore saja. Contoh: pop_admin';
        resetFieldErrors();

        // Update judul modal
        document.querySelector('[id="modal-title"]').textContent = 'Tambah Role';
        document.getElementById('modalSubmitBtn').textContent = 'Simpan Role';

        window.dispatchEvent(new CustomEvent('open-modal', { detail: 'role-modal' }));
    }

    // ---- Buka modal untuk EDIT role ----
    function openEditModal(id, name, code, description, isSystem) {
        const form = document.getElementById('roleForm');
        form.action = '<?php echo e(url("roles")); ?>/' + id;
        document.getElementById('formMethod').value = 'PUT';

        document.getElementById('roleName').value = name;
        document.getElementById('roleCode').value = code;
        document.getElementById('roleDescription').value = description;

        // Kunci field code untuk role sistem
        const codeInput = document.getElementById('roleCode');
        const codeHint  = document.getElementById('codeHint');
        if (isSystem) {
            codeInput.disabled = true;
            codeHint.textContent = 'Kode role sistem tidak dapat diubah.';
        } else {
            codeInput.disabled = false;
            codeHint.textContent = 'Gunakan huruf kecil, angka, dan underscore saja. Contoh: pop_admin';
        }

        resetFieldErrors();

        document.querySelector('[id="modal-title"]').textContent = 'Edit Role';
        document.getElementById('modalSubmitBtn').textContent = 'Perbarui Role';

        window.dispatchEvent(new CustomEvent('open-modal', { detail: 'role-modal' }));
    }

    // ---- Konfirmasi hapus role via Komponen Kustom Dialog Alert / Confirm ----
    function confirmRoleDestroy(event, form, roleName, usersCount) {
        event.preventDefault();
        if (usersCount > 0) {
            window.Alert(
                'Tidak Dapat Menghapus Role',
                `Role "${roleName}" tidak dapat dihapus.\nMasih ada ${usersCount} user yang menggunakan role ini.\nPindahkan user ke role lain terlebih dahulu.`,
                'error'
            );
            return false;
        }
        window.confirmDelete(
            `Apakah Anda yakin ingin menghapus role "${roleName}"?\n\nTindakan ini tidak dapat dibatalkan.`,
            form
        );
        return false;
    }

    // ---- Reset state error field ----
    function resetFieldErrors() {
        document.querySelectorAll('#roleForm [role="alert"]').forEach(el => el.remove());
        document.querySelectorAll('#roleForm .border-error').forEach(el => {
            el.classList.remove('border-error');
        });
    }

    // ---- Auto-generate kode dari nama (hanya saat tambah baru) ----
    document.getElementById('roleName')?.addEventListener('input', function () {
        const codeInput  = document.getElementById('roleCode');
        const formMethod = document.getElementById('formMethod').value;

        // Hanya auto-generate saat tambah baru dan field belum pernah diedit
        if (formMethod === 'POST' && !codeInput.dataset.manualEdit) {
            const generated = this.value
                .toLowerCase()
                .trim()
                .replace(/\s+/g, '_')
                .replace(/[^a-z0-9_]/g, '')
                .substring(0, 50);
            codeInput.value = generated;
        }
    });

    document.getElementById('roleCode')?.addEventListener('input', function () {
        // Tandai sebagai sudah diedit manual
        if (document.getElementById('formMethod').value === 'POST') {
            this.dataset.manualEdit = '1';
        }
    });
</script>
<?php $__env->stopSection(); ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/roles/index.blade.php ENDPATH**/ ?>