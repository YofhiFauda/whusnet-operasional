<?php $__env->startSection('title', 'Manajemen User & POP - Whusnet Operasional'); ?>
<?php $__env->startSection('page_title', 'Manajemen User & POP'); ?>

<?php $__env->startSection('content'); ?>


<div class="mb-6 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-5 shadow-sm">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h2 class="text-sm font-bold text-slate-800 dark:text-slate-200">Daftar User</h2>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Kelola user internal, role, dan penugasan POP/cabang dari satu halaman.</p>
        </div>
        <?php if(auth()->user()->hasPermission('users.create')): ?>
        <a href="<?php echo e(route('users.create')); ?>" class="inline-flex items-center justify-center rounded-md bg-sky-600 dark:bg-sky-500 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700 dark:hover:bg-sky-600">
            Tambah User
        </a>
        <?php endif; ?>
    </div>

    <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <div class="rounded-md border border-slate-200 dark:border-slate-700 px-3 py-2 text-center">
            <div class="text-[10px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500">Total User</div>
            <div class="text-lg font-bold text-slate-800 dark:text-slate-200"><?php echo e($totalUsers); ?></div>
        </div>
        <div class="rounded-md border border-slate-200 dark:border-slate-700 px-3 py-2 text-center">
            <div class="text-[10px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500">Owner/Admin</div>
            <div class="text-lg font-bold text-slate-800 dark:text-slate-200"><?php echo e($fullAccessUsers); ?></div>
        </div>
        <div class="rounded-md border border-slate-200 dark:border-slate-700 px-3 py-2 text-center">
            <div class="text-[10px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500">Dengan POP</div>
            <div class="text-lg font-bold text-slate-800 dark:text-slate-200"><?php echo e($withPopUsers); ?></div>
        </div>
        <div class="rounded-md border border-slate-200 dark:border-slate-700 px-3 py-2 text-center">
            <div class="text-[10px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500">Tanpa POP</div>
            <div class="text-lg font-bold text-slate-800 dark:text-slate-200"><?php echo e($withoutPopUsers); ?></div>
        </div>
    </div>
</div>

<div class="mb-6 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-5 shadow-sm">
    <form action="<?php echo e(route('users.index')); ?>" method="GET" class="grid grid-cols-1 gap-4 lg:grid-cols-5 lg:items-end">
        <div class="lg:col-span-2">
            <label for="search" class="mb-2 block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Cari User</label>
            <input
                id="search"
                name="search"
                type="text"
                value="<?php echo e($search); ?>"
                placeholder="Cari nama, email, atau phone..."
                class="w-full rounded-lg border border-slate-300 dark:border-slate-600 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500"
            >
        </div>

        <div>
            <label for="role_id" class="mb-2 block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Role</label>
            <select id="role_id" name="role_id" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                <option value="">Semua Role</option>
                <?php $__currentLoopData = $roles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $role): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($role->id); ?>" <?php if((string) $roleId === (string) $role->id): echo 'selected'; endif; ?>><?php echo e($role->name); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
        </div>

        <div>
            <label for="status" class="mb-2 block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Status</label>
            <select id="status" name="status" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                <option value="">Semua Status</option>
                <option value="active" <?php if($status === 'active'): echo 'selected'; endif; ?>>Aktif</option>
                <option value="inactive" <?php if($status === 'inactive'): echo 'selected'; endif; ?>>Nonaktif</option>
            </select>
        </div>

        <div>
            <label for="pop_id" class="mb-2 block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">POP</label>
            <select id="pop_id" name="pop_id" class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-3 py-2 text-sm focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500">
                <option value="">Semua POP</option>
                <?php $__currentLoopData = $pops; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pop): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($pop->id); ?>" <?php if((string) $popId === (string) $pop->id): echo 'selected'; endif; ?>><?php echo e($pop->name); ?></option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </select>
        </div>

        <div class="flex gap-2">
            <button type="submit" class="inline-flex flex-1 items-center justify-center rounded-md bg-sky-600 dark:bg-sky-500 px-4 py-2 text-sm font-semibold text-white hover:bg-sky-700 dark:hover:bg-sky-600">
                Filter
            </button>
            <a href="<?php echo e(route('users.index')); ?>" class="inline-flex items-center justify-center rounded-md border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 px-4 py-2 text-sm font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700/50 dark:bg-slate-700/50 dark:hover:bg-slate-700 dark:bg-slate-700/50">
                Reset
            </a>
        </div>
    </form>
</div>

<div class="overflow-hidden rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
            <thead class="bg-slate-50 dark:bg-slate-800/50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Nama</th>
                    <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Email</th>
                    <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Role</th>
                    <th class="px-6 py-3 text-left text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">POP</th>
                    <th class="px-6 py-3 text-center text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Status</th>
                    <th class="px-6 py-3 text-center text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50 bg-white dark:bg-slate-800">
                <?php $__empty_1 = true; $__currentLoopData = $users; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-700/60">
                    <td class="px-6 py-4">
                        <div class="text-sm font-semibold text-slate-800 dark:text-slate-200"><?php echo e($user->name); ?></div>
                        <div class="text-xs text-slate-400 dark:text-slate-500 font-mono"><?php echo e($user->phone ?? '-'); ?></div>
                    </td>
                    <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400"><?php echo e($user->email); ?></td>
                    <td class="px-6 py-4">
                        <span class="inline-flex rounded-full border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 px-2 py-0.5 text-xs font-semibold text-slate-700 dark:text-slate-300">
                            <?php echo e(optional($user->role)->name ?? '-'); ?>

                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400">
                        <?php if($user->pops->isEmpty()): ?>
                            <span class="text-slate-400 dark:text-slate-500">Belum ditugaskan</span>
                        <?php else: ?>
                            <div class="space-y-1">
                                <?php $__currentLoopData = $user->pops->take(3); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pop): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <div class="text-xs text-slate-700 dark:text-slate-300">
                                        <span class="font-semibold"><?php echo e($pop->name); ?></span>
                                        <span class="text-slate-400 dark:text-slate-500">(<?php echo e($pop->code); ?>)</span>
                                    </div>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                <?php if($user->pops->count() > 3): ?>
                                    <div class="text-xs text-slate-400 dark:text-slate-500">+<?php echo e($user->pops->count() - 3); ?> POP lainnya</div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold <?php echo e($user->status?->value === 'active' ? 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/50' : 'bg-rose-50 text-rose-700 border border-rose-200'); ?>">
                            <?php echo e($user->status?->label() ?? 'Nonaktif'); ?>

                        </span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <?php if(auth()->user()->hasPermission('users.update')): ?>
                        <div class="flex items-center justify-center gap-2">
                            <a href="<?php echo e(route('users.edit', $user)); ?>" class="inline-flex items-center rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-1.5 text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 dark:bg-slate-800/50">
                                Edit
                            </a>
                            <a href="<?php echo e(route('users.pops.edit', $user)); ?>" class="inline-flex items-center rounded-md border border-sky-200 dark:border-sky-800/50 bg-sky-50 dark:bg-sky-900/20 px-3 py-1.5 text-xs font-semibold text-sky-700 dark:text-sky-400 hover:bg-sky-100 dark:hover:bg-sky-900/40 dark:bg-sky-900/40">
                                Atur Cabang
                            </a>
                        </div>
                        <?php else: ?>
                        <span class="text-xs text-slate-400 dark:text-slate-500">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr>
                    <td colspan="6" class="px-6 py-10 text-center text-sm text-slate-500 dark:text-slate-400">
                        Tidak ada user yang cocok dengan filter.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if($users->hasPages()): ?>
    <div class="border-t border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 px-6 py-4">
        <?php echo e($users->links()); ?>

    </div>
    <?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/users/index.blade.php ENDPATH**/ ?>