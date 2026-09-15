<?php $__env->startSection('title', 'Task FOP'); ?>

<?php $__env->startSection('content'); ?>
<div x-data="fopTaskPageHandler()" x-init="initTeamConflicts(); initFopTaskEchoListeners()" x-effect="document.body.classList.toggle('overflow-hidden', modal.open || teamConflictModal.open || teamSelectionModal.open || switchTechModal.open || cancelModal.open || filterDrawerOpen)" class="px-3 sm:px-4 py-4 sm:py-6 max-w-12xl mx-auto space-y-4 sm:space-y-5 pb-20 md:pb-6">

    
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4 py-1">
        <div>
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-sky-500/10 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0">
                    <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                    </svg>
                </div>
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-slate-100 tracking-tight font-ui">Task FOP</h1>
            </div>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-1 font-ui">Kelola penugasan, status, dan prioritas task FOP yang sedang berjalan.</p>
        </div>
        
        
        <div class="hidden sm:flex items-center gap-2">
            <button x-show="teamConflictModal.conflicts.length > 0" @click="teamConflictModal.open = true"
                    class="inline-flex items-center gap-2 bg-amber-50 dark:bg-amber-900/20 hover:bg-amber-100 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-800/50 text-sm font-medium px-4 py-2 rounded-lg transition-colors shadow-2xs font-ui cursor-pointer"
                    style="display: none;">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                </svg>
                <span>Konflik Team (<span x-text="teamConflictModal.conflicts.length"></span>)</span>
            </button>
            <button @click="openCreateModal()"
                    class="inline-flex items-center gap-2 bg-sky-600 hover:bg-sky-700 text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors shadow-2xs font-ui cursor-pointer">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                </svg>
                Tambah Task FOP
            </button>
        </div>
    </div>

    <?php
        $activeFiltersCount = count(array_filter(request()->only(['category', 'priority', 'village_id', 'team_id'])));
        $currentStatus = request('status', '');
    ?>

    
    <div class="flex items-center gap-2 overflow-x-auto pb-1 custom-scrollbar -mx-1 px-1">
        <a href="<?php echo e(route('fop-tasks.index', array_merge(request()->except(['status', 'page']), ['status' => '']))); ?>"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition-all shadow-2xs <?php echo e(empty($currentStatus) ? 'bg-sky-600 text-white shadow-sky-600/20' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 hover:bg-slate-50'); ?>">
            <span>Semua Aktif</span>
            <span class="text-[10px] px-1.5 py-0.2 rounded-full <?php echo e(empty($currentStatus) ? 'bg-white/20 text-white' : 'bg-slate-100 dark:bg-slate-700 text-slate-500'); ?> font-mono"><?php echo e($fopTasks->total()); ?></span>
        </a>

        <a href="<?php echo e(route('fop-tasks.index', array_merge(request()->except(['status', 'page']), ['status' => 'terjadwal']))); ?>"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition-all shadow-2xs <?php echo e($currentStatus === 'terjadwal' ? 'bg-blue-600 text-white shadow-blue-600/20' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 hover:bg-slate-50'); ?>">
            <span class="w-2 h-2 rounded-full bg-blue-400"></span>
            <span>Terjadwal</span>
        </a>

        <a href="<?php echo e(route('fop-tasks.index', array_merge(request()->except(['status', 'page']), ['status' => 'in_progress']))); ?>"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition-all shadow-2xs <?php echo e($currentStatus === 'in_progress' ? 'bg-amber-600 text-white shadow-amber-600/20' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 hover:bg-slate-50'); ?>">
            <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
            <span>Sedang Dikerjakan</span>
        </a>

        <a href="<?php echo e(route('fop-tasks.index', array_merge(request()->except(['status', 'page']), ['status' => 'pending']))); ?>"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap transition-all shadow-2xs <?php echo e($currentStatus === 'pending' ? 'bg-yellow-600 text-white shadow-yellow-600/20' : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 hover:bg-slate-50'); ?>">
            <span class="w-2 h-2 rounded-full bg-yellow-400"></span>
            <span>Pending</span>
        </a>

        <button x-show="teamConflictModal.conflicts.length > 0"
                @click="teamConflictModal.open = true"
                type="button"
                class="inline-flex sm:hidden items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold whitespace-nowrap bg-red-50 text-red-700 border border-red-200 dark:bg-red-950/40 dark:text-red-400 dark:border-red-900/50 animate-pulse cursor-pointer">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
            <span>Konflik (<span x-text="teamConflictModal.conflicts.length"></span>)</span>
        </button>
    </div>

    
    <div class="block md:hidden">
        <form method="GET" action="<?php echo e(route('fop-tasks.index')); ?>" class="flex items-center gap-2">
            <?php if(request('status')): ?>
                <input type="hidden" name="status" value="<?php echo e(request('status')); ?>">
            <?php endif; ?>
            <?php if(request('category')): ?>
                <input type="hidden" name="category" value="<?php echo e(request('category')); ?>">
            <?php endif; ?>
            <?php if(request('priority')): ?>
                <input type="hidden" name="priority" value="<?php echo e(request('priority')); ?>">
            <?php endif; ?>
            <?php if(request('village_id')): ?>
                <input type="hidden" name="village_id" value="<?php echo e(request('village_id')); ?>">
            <?php endif; ?>
            <?php if(request('team_id')): ?>
                <input type="hidden" name="team_id" value="<?php echo e(request('team_id')); ?>">
            <?php endif; ?>

            <div class="relative flex-1">
                <input type="text" name="search" value="<?php echo e(request('search')); ?>" placeholder="Cari task, nama, atau issue..."
                       class="w-full text-xs pl-8 pr-3 py-2 border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 placeholder:text-slate-400 focus:border-sky-500 focus:ring-1 focus:ring-sky-500 outline-none shadow-2xs font-ui">
                <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-1/2 -translate-y-1/2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>

            <button type="button" @click="filterDrawerOpen = true"
                    class="inline-flex items-center gap-1.5 px-3 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 transition-colors shadow-2xs shrink-0 cursor-pointer">
                <svg class="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                </svg>
                <span>Filter</span>
                <?php if($activeFiltersCount > 0): ?>
                    <span class="w-4 h-4 rounded-full bg-sky-600 text-white text-[10px] font-bold flex items-center justify-center"><?php echo e($activeFiltersCount); ?></span>
                <?php endif; ?>
            </button>
        </form>
    </div>

    
    <form method="GET" action="<?php echo e(route('fop-tasks.index')); ?>" class="hidden md:flex flex-col gap-3 pb-2">
        <div class="grid grid-cols-2 md:grid-cols-6 gap-3">
            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1.5 font-ui">Pencarian</label>
                <input type="text" name="search" value="<?php echo e(request('search')); ?>" placeholder="Cari Task..." class="w-full text-xs border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none placeholder:text-slate-400 text-slate-800 dark:text-slate-100 font-ui bg-white dark:bg-slate-800 shadow-2xs">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1.5 font-ui">Kategori</label>
                <select name="category" class="w-full text-xs border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 font-ui shadow-2xs">
                    <option value="">Semua</option>
                    <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $val): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($key); ?>" <?php echo e(request('category') === $key ? 'selected' : ''); ?>><?php echo e($key); ?> - <?php echo e($val); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1.5 font-ui">Status</label>
                <select name="status" class="w-full text-xs border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 font-ui shadow-2xs">
                    <option value="">Semua (Aktif)</option>
                    <option value="draft" <?php echo e(request('status') === 'draft' ? 'selected' : ''); ?>>Draft</option>
                    <option value="terjadwal" <?php echo e(request('status') === 'terjadwal' ? 'selected' : ''); ?>>Terjadwal</option>
                    <option value="in_progress" <?php echo e(request('status') === 'in_progress' ? 'selected' : ''); ?>>Sedang Dikerjakan</option>
                    <option value="pending" <?php echo e(request('status') === 'pending' ? 'selected' : ''); ?>>Pending</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1.5 font-ui">Prioritas</label>
                <select name="priority" class="w-full text-xs border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 font-ui shadow-2xs">
                    <option value="">Semua</option>
                    <option value="low" <?php echo e(request('priority') === 'low' ? 'selected' : ''); ?>>Low</option>
                    <option value="Medium" <?php echo e(request('priority') === 'Medium' ? 'selected' : ''); ?>>Medium</option>
                    <option value="High" <?php echo e(request('priority') === 'High' ? 'selected' : ''); ?>>High</option>
                    <option value="Urgent" <?php echo e(request('priority') === 'Urgent' ? 'selected' : ''); ?>>Urgent</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1.5 font-ui">Area</label>
                <select name="village_id" class="w-full text-xs border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 font-ui shadow-2xs">
                    <option value="">Semua</option>
                    <?php $__currentLoopData = $villages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($v->id); ?>" <?php echo e(request('village_id') == $v->id ? 'selected' : ''); ?>><?php echo e($v->name); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1.5 font-ui">Team</label>
                <select name="team_id" class="w-full text-xs border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 font-ui shadow-2xs">
                    <option value="">Semua</option>
                    <?php $__currentLoopData = $teams; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($t['id']); ?>" <?php echo e(request('team_id') == $t['id'] ? 'selected' : ''); ?>><?php echo e($t['name']); ?> (<?php echo e($t['work_date']); ?>)</option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>
        </div>
        <div class="flex items-center justify-between mt-1">
            <span class="text-xs text-slate-500 dark:text-slate-400 font-ui">Menampilkan <span class="font-semibold text-slate-700 dark:text-slate-300 font-data"><?php echo e($fopTasks->count()); ?></span> dari <span class="font-semibold text-slate-700 dark:text-slate-300 font-data"><?php echo e($fopTasks->total()); ?></span> data</span>
            <div class="flex items-center gap-3">
                <?php if(request()->anyFilled(['search', 'category', 'status', 'priority', 'village_id', 'team_id'])): ?>
                    <a href="<?php echo e(route('fop-tasks.index')); ?>" class="text-xs font-semibold text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 transition-colors font-ui">Reset</a>
                <?php endif; ?>
                <button type="submit" class="bg-slate-800 hover:bg-slate-900 text-white dark:bg-slate-700 dark:hover:bg-slate-600 text-xs font-semibold px-4 py-2 rounded-lg transition-colors font-ui shadow-2xs cursor-pointer">Filter</button>
            </div>
        </div>
    </form>

    
    <div x-show="filterDrawerOpen"
         class="fixed inset-0 z-50 overflow-y-auto flex items-end md:hidden"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         style="display: none;">
        
        <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-xs" @click="filterDrawerOpen = false"></div>

        <div class="bg-surface border-t border-border w-full rounded-t-2xl shadow-2xl relative z-10 max-h-[85vh] flex flex-col overflow-hidden"
             @click.away="filterDrawerOpen = false"
             x-transition:enter="transition ease-out duration-250"
             x-transition:enter-start="translate-y-full"
             x-transition:enter-end="translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="translate-y-0"
             x-transition:leave-end="translate-y-full">
            
            
            <div class="w-12 h-1.5 bg-slate-300 dark:bg-slate-600 rounded-full mx-auto my-2 shrink-0"></div>

            <div class="px-5 py-3 border-b border-border flex items-center justify-between bg-surface-muted shrink-0">
                <h3 class="text-sm font-bold text-text-main font-ui">Filter Task FOP</h3>
                <button type="button" @click="filterDrawerOpen = false" class="text-text-muted hover:text-text-main p-1">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form method="GET" action="<?php echo e(route('fop-tasks.index')); ?>" class="flex flex-col flex-1 overflow-hidden">
                <div class="p-5 overflow-y-auto space-y-4 flex-1 custom-scrollbar font-ui">
                    <?php if(request('status')): ?>
                        <input type="hidden" name="status" value="<?php echo e(request('status')); ?>">
                    <?php endif; ?>

                    <div>
                        <label class="block text-xs font-semibold text-text-secondary uppercase tracking-wider mb-1.5">Pencarian</label>
                        <input type="text" name="search" value="<?php echo e(request('search')); ?>" placeholder="Cari task / pelanggan..." class="w-full text-sm border border-border rounded-lg px-3 py-2 bg-surface text-text-main focus:ring-1 focus:ring-primary outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-text-secondary uppercase tracking-wider mb-1.5">Kategori</label>
                        <select name="category" class="w-full text-sm border border-border rounded-lg px-3 py-2 bg-surface text-text-main focus:ring-1 focus:ring-primary outline-none">
                            <option value="">Semua Kategori</option>
                            <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $val): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($key); ?>" <?php echo e(request('category') === $key ? 'selected' : ''); ?>><?php echo e($key); ?> - <?php echo e($val); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-text-secondary uppercase tracking-wider mb-1.5">Prioritas</label>
                        <select name="priority" class="w-full text-sm border border-border rounded-lg px-3 py-2 bg-surface text-text-main focus:ring-1 focus:ring-primary outline-none">
                            <option value="">Semua Prioritas</option>
                            <option value="low" <?php echo e(request('priority') === 'low' ? 'selected' : ''); ?>>Low</option>
                            <option value="Medium" <?php echo e(request('priority') === 'Medium' ? 'selected' : ''); ?>>Medium</option>
                            <option value="High" <?php echo e(request('priority') === 'High' ? 'selected' : ''); ?>>High</option>
                            <option value="Urgent" <?php echo e(request('priority') === 'Urgent' ? 'selected' : ''); ?>>Urgent</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-text-secondary uppercase tracking-wider mb-1.5">Area (Desa)</label>
                        <select name="village_id" class="w-full text-sm border border-border rounded-lg px-3 py-2 bg-surface text-text-main focus:ring-1 focus:ring-primary outline-none">
                            <option value="">Semua Area</option>
                            <?php $__currentLoopData = $villages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($v->id); ?>" <?php echo e(request('village_id') == $v->id ? 'selected' : ''); ?>><?php echo e($v->name); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-text-secondary uppercase tracking-wider mb-1.5">Team</label>
                        <select name="team_id" class="w-full text-sm border border-border rounded-lg px-3 py-2 bg-surface text-text-main focus:ring-1 focus:ring-primary outline-none">
                            <option value="">Semua Team</option>
                            <?php $__currentLoopData = $teams; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($t['id']); ?>" <?php echo e(request('team_id') == $t['id'] ? 'selected' : ''); ?>><?php echo e($t['name']); ?> (<?php echo e($t['work_date']); ?>)</option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>
                </div>

                <div class="px-5 py-3.5 border-t border-border bg-surface-muted flex items-center justify-between gap-3 shrink-0">
                    <a href="<?php echo e(route('fop-tasks.index')); ?>" class="btn-secondary text-xs">Reset</a>
                    <button type="submit" class="btn-primary text-xs">Terapkan Filter</button>
                </div>
            </form>
        </div>
    </div>

    
    <div class="block md:hidden space-y-3.5" id="fop-tasks-mobile-feed">
        <?php $__empty_1 = true; $__currentLoopData = $fopTasks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $task): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <?php echo $__env->make('fop_tasks.partials.mobile-card', ['task' => $task, 'isHistory' => false], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-8 text-center text-slate-500 dark:text-slate-400">
                <svg class="w-10 h-10 mx-auto mb-3 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                <p class="text-xs font-bold text-slate-700 dark:text-slate-300 font-ui">Tidak ada task FOP aktif.</p>
                <p class="text-[11px] mt-1 text-slate-400 dark:text-slate-500 font-ui">Silakan buat task baru atau sesuaikan filter pencarian.</p>
            </div>
        <?php endif; ?>
    </div>

    
    <div class="hidden md:block bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-2xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-700 text-[11px] font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider">
                        <th class="px-3 py-2.5">Kategori</th>
                        <th class="px-3 py-2.5">Tanggal</th>
                        <th class="px-3 py-2.5">Tugas</th>
                        <th class="px-3 py-2.5">Area</th>
                        <th class="px-3 py-2.5">Issue</th>
                        <th class="px-3 py-2.5">Teknisi</th>
                        <th class="px-3 py-2.5">Team</th>
                        <th class="px-3 py-2.5">Status</th>
                        <th class="px-3 py-2.5">Prioritas</th>
                        <th class="px-3 py-2.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50 text-[11px] text-slate-700 dark:text-slate-300 font-ui">
                    <?php $__empty_1 = true; $__currentLoopData = $fopTasks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $task): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors align-top" id="fop-task-row-<?php echo e($task->id); ?>" data-pop-id="<?php echo e($task->pop_id); ?>">
                            <td class="px-3 py-2 whitespace-nowrap">
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-medium border <?php echo e($task->category instanceof \App\Enums\TaskType ? $task->category->badgeClasses() : 'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-400'); ?>">
                                    <?php echo e($task->category instanceof \App\Enums\TaskType ? $task->category->value : $task->category); ?>

                                </span>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-slate-600 dark:text-slate-400">
                                <?php echo e($task->task_date ? $task->task_date->format('d/m/Y H:i') : '—'); ?>

                                <?php if($task->client_request_date): ?>
                                    <br>
                                    <?php if($task->client_request_date->lte(\Illuminate\Support\Carbon::today())): ?>
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-semibold bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 border border-red-100 dark:border-red-800/30 mt-0.5 animate-pulse">
                                            JADWAL HARI INI
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-medium bg-slate-50 dark:bg-slate-800/50 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700 mt-0.5">
                                            Terjadwal — <?php echo e($task->client_request_date->format('d/m/Y')); ?>

                                        </span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2 min-w-[200px] whitespace-normal leading-tight">
                                <span class="font-medium text-slate-800 dark:text-slate-200"><?php echo e($task->tugas); ?></span>
                            </td>
                            <td class="px-3 py-2 whitespace-normal leading-tight text-slate-600 dark:text-slate-400 min-w-[120px]">
                                <?php echo e($task->village?->name ?? '—'); ?>

                            </td>
                            <td class="px-3 py-2 min-w-[150px] whitespace-normal leading-tight text-red-600 dark:text-red-400">
                                <?php echo e($task->issue ?? '—'); ?>

                            </td>
                            <?php echo $__env->make('fop_tasks.partials.row-cells', ['task' => $task], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                            <?php
                                $canDeleteTask = !in_array($task->category->value, ['SURVEY', 'PSB'], true) && !$task->ticket;
                            ?>
                            <td class="px-3 py-2 whitespace-nowrap">
                                <?php if($task->isScheduledForFutureClientDate()): ?>
                                    <span class="inline-flex items-center gap-1 text-[10px] font-semibold px-1.5 py-0.5 rounded-full border border-sky-200 dark:border-sky-800 bg-sky-50 dark:bg-sky-950/40 text-sky-700 dark:text-sky-300 mb-1">
                                        <svg class="h-3 w-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                        </svg>
                                        Dijadwalkan <?php echo e(\App\Support\IndonesianDate::date($task->client_request_date)); ?>

                                    </span>
                                <?php endif; ?>

                                <?php if($canEditFopTaskType): ?>
                                    <select @change="updatePriority(<?php echo e($task->id); ?>, $event.target.value)"
                                            x-data="{ currentPriority: '<?php echo e($task->priority->value); ?>' }"
                                            x-model="currentPriority"
                                            class="text-[11px] font-medium rounded border px-2 py-1 outline-none focus:ring-1 focus:ring-blue-500 w-24 transition-colors duration-200"
                                            :class="{
                                                'border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 bg-slate-50 dark:bg-slate-800/50': currentPriority === 'low',
                                                'border-yellow-300 text-yellow-800 bg-yellow-50': currentPriority === 'Medium',
                                                'border-orange-300 text-orange-800 bg-orange-50': currentPriority === 'High',
                                                'border-red-300 text-red-700 dark:text-red-400 bg-red-50 dark:bg-red-900/20 font-bold': currentPriority === 'Urgent'
                                            }">
                                        <option value="low">Low</option>
                                        <option value="Medium">Medium</option>
                                        <option value="High">High</option>
                                        <option value="Urgent">Urgent</option>
                                    </select>
                                <?php elseif(! $task->isScheduledForFutureClientDate()): ?>
                                    <?php if (isset($component)) { $__componentOriginalb8d3d89751f3d81017aa8a59bd985fb5 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb8d3d89751f3d81017aa8a59bd985fb5 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.countdown-timer','data' => ['deadline' => ''.e($task->slaDeadline()->toIso8601String()).'','totalSeconds' => $task->slaTotalSeconds(),'label' => 'SLA '.e($task->category->label()).'','compact' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('countdown-timer'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['deadline' => ''.e($task->slaDeadline()->toIso8601String()).'','total-seconds' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($task->slaTotalSeconds()),'label' => 'SLA '.e($task->category->label()).'','compact' => true]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb8d3d89751f3d81017aa8a59bd985fb5)): ?>
<?php $attributes = $__attributesOriginalb8d3d89751f3d81017aa8a59bd985fb5; ?>
<?php unset($__attributesOriginalb8d3d89751f3d81017aa8a59bd985fb5); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb8d3d89751f3d81017aa8a59bd985fb5)): ?>
<?php $component = $__componentOriginalb8d3d89751f3d81017aa8a59bd985fb5; ?>
<?php unset($__componentOriginalb8d3d89751f3d81017aa8a59bd985fb5); ?>
<?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a href="<?php echo e(route('fop-tasks.history.show', $task->id)); ?>"
                                       class="text-slate-400 dark:text-slate-500 hover:text-slate-700 dark:hover:text-slate-200 transition-colors bg-slate-100 dark:bg-slate-700/50 hover:bg-slate-200 p-1.5 rounded"
                                       title="Detail Task">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </a>
                                    <button @click="openEditModal(<?php echo e(json_encode($task)); ?>, <?php echo e(json_encode($task->technicians->pluck('id'))); ?>, '<?php echo e(route('fop-tasks.update', $task->id)); ?>')"
                                            class="text-slate-400 dark:text-slate-500 hover:text-blue-600 transition-colors bg-slate-100 dark:bg-slate-700/50 hover:bg-blue-50 p-1.5 rounded cursor-pointer"
                                            title="Edit">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </button>
                                    <?php if($canDeleteTask): ?>
                                    <form action="<?php echo e(route('fop-tasks.destroy', $task->id)); ?>" method="POST" data-confirm="Apakah Anda yakin ingin menghapus Task FOP ini?" class="inline-block">
                                        <?php echo csrf_field(); ?>
                                        <?php echo method_field('DELETE'); ?>
                                        <button type="submit" class="text-slate-400 dark:text-slate-500 hover:text-red-600 dark:hover:text-red-400 transition-colors bg-slate-100 dark:bg-slate-700/50 hover:bg-red-50 dark:hover:bg-red-900/20 p-1.5 rounded cursor-pointer" title="Hapus">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </form>
                                    <?php elseif($task->ticket): ?>
                                    <button type="button" disabled
                                            class="text-slate-300 bg-slate-50 dark:bg-slate-800/50 p-1.5 rounded cursor-not-allowed"
                                            title="Task dari Ticketing gak bisa dihapus — batalkan lewat Cancel kalau salah input.">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="10" class="px-3 py-10 text-center text-slate-500 dark:text-slate-400">
                                <svg class="w-8 h-8 mx-auto mb-3 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                                <p class="text-[11px] font-medium">Tidak ada data task FOP.</p>
                                <p class="text-[10px] mt-1 text-slate-400 dark:text-slate-500">Silakan buat task baru atau ubah filter pencarian.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    
    <?php if($fopTasks->hasPages()): ?>
        <div class="mt-4">
            <?php echo e($fopTasks->links()); ?>

        </div>
    <?php endif; ?>

    
    <div class="fixed bottom-5 right-4 z-40 md:hidden flex flex-col items-end gap-2.5">
        <button x-show="teamConflictModal.conflicts.length > 0"
                @click="teamConflictModal.open = true"
                type="button"
                class="inline-flex items-center gap-2 bg-amber-500 text-white font-bold text-xs px-3.5 py-2.5 rounded-full shadow-lg shadow-amber-500/30 animate-bounce cursor-pointer"
                style="display: none;">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
            </svg>
            <span><span x-text="teamConflictModal.conflicts.length"></span> Konflik</span>
        </button>

        <button @click="openCreateModal()"
                type="button"
                class="inline-flex items-center gap-2 bg-sky-600 hover:bg-sky-700 text-white font-bold text-sm px-4 py-3 rounded-full shadow-xl shadow-sky-600/30 cursor-pointer active:scale-95 transition-transform">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            <span>Tambah Task</span>
        </button>
    </div>

    
    <div x-show="modal.open" 
         class="fixed inset-0 z-50 overflow-y-auto flex items-end md:items-center justify-center" 
         x-transition:enter="transition ease-out duration-200" 
         x-transition:enter-start="opacity-0" 
         x-transition:enter-end="opacity-100" 
         x-transition:leave="transition ease-in duration-150" 
         x-transition:leave-start="opacity-100" 
         x-transition:leave-end="opacity-0"
         style="display: none;">
        
        <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-xs" @click="modal.open = false"></div>

        <div class="bg-surface border-t md:border border-border w-full max-w-2xl rounded-t-2xl md:rounded-xl shadow-2xl relative z-10 max-h-[90vh] md:max-h-[85vh] flex flex-col overflow-hidden" 
             @click.away="modal.open = false"
             x-transition:enter="transition ease-out duration-250"
             x-transition:enter-start="translate-y-full md:translate-y-4 md:scale-95 md:opacity-0"
             x-transition:enter-end="translate-y-0 md:translate-y-0 md:scale-100 md:opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="translate-y-0 md:translate-y-0 md:scale-100 md:opacity-100"
             x-transition:leave-end="translate-y-full md:translate-y-4 md:scale-95 md:opacity-0">
            
            
            <div class="w-12 h-1.5 bg-slate-300 dark:bg-slate-600 rounded-full mx-auto my-2 shrink-0 md:hidden"></div>

            <div class="px-5 py-3.5 border-b border-border flex items-center justify-between bg-surface-muted rounded-t-xl shrink-0">
                <h3 class="text-sm font-bold text-text-main font-ui" x-text="modal.isEdit ? 'Edit Task FOP' : 'Tambah Task FOP'"></h3>
                <button type="button" @click="modal.open = false" class="text-text-muted hover:text-text-main transition-colors p-1 cursor-pointer">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form :action="formAction" method="POST" enctype="multipart/form-data"
                  class="flex flex-col flex-1 overflow-hidden"
                  @submit="
                      if (!$el.getAttribute('action')) {
                          $event.preventDefault();
                          if (window.Toast) {
                              window.Toast.error('Aksi Gagal', 'Target penyimpanan task tidak dikenal. Tutup modal dan muat ulang halaman.');
                          }
                          return;
                      }
                      isSubmitting = true;
                  ">
                <div class="p-5 overflow-y-auto space-y-4 flex-1 custom-scrollbar font-ui">
                    <?php echo csrf_field(); ?>
                    <template x-if="modal.isEdit">
                        <input type="hidden" name="_method" value="PUT">
                    </template>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-text-secondary mb-1">Tipe Task <span class="text-error">*</span></label>
                            <select name="category" x-model="modal.data.category"
                                    @change="onCategoryChange()"
                                    :disabled="isEditingLockedSurveyPsb || isEditingLinkedTicket || (modal.isEdit && !canEditCategory)"
                                    required
                                    class="w-full text-sm bg-surface text-text-main border border-border rounded-lg px-3 py-2 outline-none focus:border-primary focus:ring-1 focus:ring-primary disabled:bg-surface-muted disabled:text-text-muted">
                                <option value="">Pilih Tipe</option>
                                <template x-for="[key, val] in Object.entries(availableCategories)" :key="key">
                                    <option :value="key" x-text="key + ' - ' + val"></option>
                                </template>
                            </select>
                            <template x-if="isEditingLockedSurveyPsb || isEditingLinkedTicket || (modal.isEdit && !canEditCategory)">
                                <input type="hidden" name="category" :value="modal.data.category">
                            </template>
                            <p class="mt-1 text-[10px] text-text-muted" x-show="isEditingLockedSurveyPsb && modal.data.category !== 'DEAC'">Task Survey/Pemasangan tidak bisa diedit dari sini — hanya lewat alur Registrasi Pelanggan.</p>
                            <p class="mt-1 text-[10px] text-text-muted" x-show="isEditingLockedSurveyPsb && modal.data.category === 'DEAC'">Task Ambil Modem tidak bisa diedit dari sini — asalnya dari tombol "Ambil Alat" di List Putus Langganan.</p>
                            <p class="mt-1 text-[10px] text-text-muted" x-show="isEditingLinkedTicket">Tipe gak bisa diubah — task ini nyambung ke Ticket, tipe-nya ngikut tipe ticket-nya.</p>
                            <p class="mt-1 text-[10px] text-text-muted" x-show="!isEditingLockedSurveyPsb && !isEditingLinkedTicket && modal.isEdit && !canEditCategory">Anda tidak punya izin ubah tipe task.</p>
                            <p class="mt-1 text-[10px] text-text-muted" x-show="!modal.isEdit && !isTicketMode">Survey &amp; Pemasangan Baru otomatis dibuat saat Registrasi Pelanggan. Ambil Modem otomatis dibuat lewat tombol "Ambil Alat" di List Putus Langganan.</p>
                            <p class="mt-1 text-[10px] text-primary font-medium" x-show="isTicketMode && !modal.isEdit">Tipe ini ikut alur Ticketing — cari pelanggan lewat CID di bawah, data pelanggan terisi otomatis.</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-text-secondary mb-1">Tanggal & Waktu <span class="text-error">*</span></label>
                            <input type="datetime-local" name="task_date" x-model="modal.data.task_date" required class="w-full text-sm bg-surface text-text-main border border-border rounded-lg px-3 py-2 outline-none focus:border-primary focus:ring-1 focus:ring-primary disabled:bg-surface-muted disabled:text-text-muted font-mono">
                        </div>
                    </div>

                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4" x-show="!isTicketMode">
                        <div>
                            <label class="block text-xs font-semibold text-text-secondary mb-1">POP / Cabang <span class="text-error">*</span></label>
                            <select name="pop_id" x-model="modal.data.pop_id" :disabled="isEditingLockedSurveyPsb" :required="!isTicketMode" class="w-full text-sm bg-surface text-text-main border border-border rounded-lg px-3 py-2 outline-none focus:border-primary focus:ring-1 focus:ring-primary disabled:bg-surface-muted disabled:text-text-muted">
                                <option value="">Pilih Cabang</option>
                                <?php $__currentLoopData = $pops; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($p->id); ?>"><?php echo e($p->name); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-text-secondary mb-1">Area (Desa) <span class="text-error">*</span></label>
                            <select name="village_id" x-model="modal.data.village_id" :disabled="isEditingLockedSurveyPsb" :required="!isTicketMode" class="w-full text-sm bg-surface text-text-main border border-border rounded-lg px-3 py-2 outline-none focus:border-primary focus:ring-1 focus:ring-primary disabled:bg-surface-muted disabled:text-text-muted">
                                <option value="">Pilih Desa</option>
                                <?php $__currentLoopData = $villages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($v->id); ?>"><?php echo e($v->name); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        </div>
                    </div>

                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4" x-show="!isTicketMode">
                        <div class="relative" @click.away="customerSearchResults = []">
                            <label class="block text-xs font-semibold text-text-secondary mb-1">Penugasan / Pelanggan <span class="text-error">*</span></label>
                            <input type="text" name="tugas" x-model="modal.data.tugas"
                                   @input.debounce.300ms="searchCustomer()"
                                   @keydown.escape="customerSearchResults = []"
                                   autocomplete="off"
                                   :disabled="isEditingLockedSurveyPsb"
                                   :required="!isTicketMode"
                                   placeholder="Ketik tugas / nama..." class="w-full text-sm bg-surface text-text-main border border-border rounded-lg px-3 py-2 outline-none focus:border-primary focus:ring-1 focus:ring-primary disabled:bg-surface-muted disabled:text-text-muted">

                            <div x-show="!isEditingLockedSurveyPsb && customerSearchResults.length > 0" class="absolute z-50 w-full bg-surface border border-border rounded-lg mt-1 max-h-48 overflow-y-auto shadow-lg" style="display: none;">
                                <template x-for="c in customerSearchResults" :key="c.id">
                                    <button type="button" @click="selectCustomer(c)" class="w-full text-left px-3 py-2 text-sm bg-surface hover:bg-surface-muted text-text-main border-b border-border last:border-0 outline-none">
                                        <span class="font-medium text-text-secondary" x-text="c.label"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-text-secondary mb-1">Issue / Masalah <span class="text-error">*</span></label>
                            <input type="text" name="issue" x-model="modal.data.issue" placeholder="Contoh: FO CUT..." :required="!isTicketMode" class="w-full text-sm bg-surface text-text-main border border-border rounded-lg px-3 py-2 outline-none focus:border-primary focus:ring-1 focus:ring-primary disabled:bg-surface-muted disabled:text-text-muted">
                        </div>
                    </div>

                    <input type="hidden" name="customer_id" :value="modal.data.customer_id">
                    <input type="hidden" name="origin" value="fop_tasks">

                    
                    <div x-show="isTicketMode" class="space-y-4">
                        <div class="relative" @click.away="ticketCustomerResults = []" x-show="!isEditingLinkedTicket">
                            <label class="block text-xs font-semibold text-text-secondary mb-1">CID / Pelanggan <span class="text-error">*</span></label>
                            <input type="text" x-model="ticketCidQuery" @input.debounce.300ms="searchTicketCustomer()"
                                   :disabled="ticketSelectedCustomer !== null"
                                   placeholder="Ketik CID atau nama pelanggan..."
                                   class="w-full text-sm bg-surface text-text-main border border-border rounded-lg px-3 py-2 outline-none focus:border-primary focus:ring-1 focus:ring-primary disabled:bg-surface-muted disabled:text-text-muted">

                            <div x-show="ticketCustomerResults.length > 0 && !ticketSelectedCustomer" class="absolute z-50 w-full bg-surface border border-border rounded-lg mt-1 max-h-48 overflow-y-auto shadow-lg" style="display: none;">
                                <template x-for="c in ticketCustomerResults" :key="c.id">
                                    <button type="button" @click="pickTicketCustomer(c)" class="w-full text-left px-3 py-2 text-sm bg-surface hover:bg-surface-muted text-text-main border-b border-border last:border-0 outline-none">
                                        <span x-text="c.label"></span>
                                    </button>
                                </template>
                            </div>
                            <p x-show="ticketSearching" class="text-[10px] text-text-muted mt-1">Mencari...</p>
                        </div>

                        <p class="text-xs font-medium text-text-secondary" x-show="isEditingLinkedTicket">
                            CID / Pelanggan <span class="text-[10px] font-normal text-text-muted">(terkunci ke Ticket <span class="font-mono" x-text="modal.data.ticket?.ticket_number"></span> — ganti pelanggan lewat Ticketing)</span>
                        </p>

                        <div x-show="ticketSelectedCustomer" class="border border-border rounded-lg bg-surface-muted overflow-hidden">
                            <div class="flex items-center justify-between px-3 py-2 bg-surface border-b border-border">
                                <span class="text-xs font-semibold text-text-secondary">Data Pelanggan</span>
                                <button type="button" @click="clearTicketCustomer()" class="text-xs text-primary hover:underline font-semibold" x-show="!isEditingLinkedTicket">Ganti</button>
                            </div>
                            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2 p-3 text-xs">
                                <div><dt class="text-text-muted">CID</dt><dd class="font-medium text-text-main" x-text="ticketSelectedCustomer?.cid || '—'"></dd></div>
                                <div><dt class="text-text-muted">Nama</dt><dd class="font-medium text-text-main" x-text="ticketSelectedCustomer?.nama || '—'"></dd></div>
                                <div class="sm:col-span-2"><dt class="text-text-muted">Alamat</dt><dd class="font-medium text-text-main" x-text="ticketSelectedCustomer?.alamat || '—'"></dd></div>
                                <div><dt class="text-text-muted">No. HP</dt><dd class="font-medium text-text-main" x-text="ticketSelectedCustomer?.no_hp || '—'"></dd></div>
                                <div><dt class="text-text-muted">POP / Cabang</dt><dd class="font-medium text-text-main" x-text="ticketSelectedCustomer?.pop || '—'"></dd></div>
                                <div><dt class="text-text-muted">ODP</dt><dd class="font-medium text-text-main" x-text="ticketSelectedCustomer?.odp || '—'"></dd></div>
                                <div><dt class="text-text-muted">Paket</dt><dd class="font-medium text-text-main" x-text="ticketSelectedCustomer?.paket || '—'"></dd></div>
                                <div><dt class="text-text-muted">Perangkat Pelanggan</dt><dd class="font-medium text-text-main" x-text="ticketSelectedCustomer?.perangkat || '—'"></dd></div>
                                <div>
                                    <dt class="text-text-muted">Koordinat</dt>
                                    <dd class="font-medium text-text-main">
                                        <template x-if="ticketSelectedCustomer?.maps_url">
                                            <a :href="ticketSelectedCustomer.maps_url" target="_blank" rel="noopener" class="text-primary hover:underline font-medium" x-text="ticketSelectedCustomer.koordinat"></a>
                                        </template>
                                        <template x-if="!ticketSelectedCustomer?.maps_url"><span>—</span></template>
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        <div x-show="!modal.isEdit">
                            <label class="block text-xs font-semibold text-text-secondary mb-1">Detail Keluhan <span class="text-error">*</span></label>
                            <textarea name="detail_keluhan" x-model="modal.data.detail_keluhan" rows="3" :required="isTicketMode && !modal.isEdit" maxlength="2000" placeholder="Jelaskan keluhan pelanggan..." class="w-full text-sm bg-surface text-text-main border border-border rounded-lg px-3 py-2 outline-none focus:border-primary focus:ring-1 focus:ring-primary"></textarea>
                        </div>
                        <div x-show="!modal.isEdit">
                            <label class="block text-xs font-semibold text-text-secondary mb-1">Catatan Teknis</label>
                            <textarea name="catatan_teknis" x-model="modal.data.catatan_teknis" rows="2" maxlength="2000" placeholder="Redaman, indikasi penyebab, dll (opsional)" class="w-full text-sm bg-surface text-text-main border border-border rounded-lg px-3 py-2 outline-none focus:border-primary focus:ring-1 focus:ring-primary"></textarea>
                        </div>
                        <div x-show="modal.isEdit">
                            <label class="block text-xs font-semibold text-text-secondary mb-1">Detail Keluhan</label>
                            <div class="w-full text-sm text-text-main bg-surface-muted border border-border rounded-lg px-3 py-2 whitespace-pre-line" x-text="modal.data.detail_keluhan || '—'"></div>
                        </div>
                        <div x-show="modal.isEdit">
                            <label class="block text-xs font-semibold text-text-secondary mb-1">Catatan Teknis</label>
                            <div class="w-full text-sm text-text-main bg-surface-muted border border-border rounded-lg px-3 py-2 whitespace-pre-line" x-text="modal.data.catatan_teknis || '—'"></div>
                        </div>
                        <div x-show="!modal.isEdit">
                            <label class="block text-xs font-semibold text-text-secondary mb-1">Lampiran</label>
                            <input type="file" name="attachments[]" multiple accept="image/jpeg,image/png,image/webp,application/pdf"
                                   class="w-full text-xs text-text-secondary file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-surface-muted file:text-text-main hover:file:bg-border file:cursor-pointer">
                            <p class="text-[10px] text-text-muted mt-1">Maks. 5 file, tiap file maks. 5 MB. Format: JPG, PNG, WEBP, PDF.</p>
                        </div>
                    </div>

                    <div class="relative" x-data="{ openTechDropdown: false }">
                        <label class="block text-xs font-semibold text-text-secondary mb-1">Pilih Teknisi <span class="text-error">*</span></label>
                        <div @click="openTechDropdown = true" @click.away="openTechDropdown = false" class="min-h-[38px] w-full border border-border rounded-lg bg-surface px-2 py-1.5 focus-within:border-primary focus-within:ring-1 focus-within:ring-primary cursor-text flex items-center gap-2 flex-wrap">
                            <template x-for="techId in modal.techs" :key="techId">
                                <span class="inline-flex items-center gap-1 bg-surface-muted border border-border text-text-secondary text-xs font-medium px-2 py-0.5 rounded-md">
                                    <span x-text="getTechName(techId)"></span>
                                    <button type="button" @click.stop="toggleTech(techId)" class="hover:text-error transition-colors p-0.5">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                    </button>
                                </span>
                            </template>
                            <input type="text" x-model="searchTech" @focus="openTechDropdown = true" placeholder="Cari teknisi..." class="flex-1 min-w-[100px] outline-none text-sm text-text-main bg-transparent border-none p-0 focus:ring-0">
                        </div>

                        <div x-show="openTechDropdown" class="absolute z-50 w-full bg-surface border border-border rounded-lg shadow-lg mt-1 max-h-48 overflow-y-auto" style="display: none;">
                            <?php $__currentLoopData = $technicians; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tech): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <label class="flex items-center gap-2 px-3 py-2 bg-surface hover:bg-surface-muted cursor-pointer border-b border-border last:border-0"
                                       x-show="searchTech === '' || '<?php echo e(strtolower($tech->name)); ?>'.includes(searchTech.toLowerCase())">
                                    <input type="checkbox" name="technicians[]" value="<?php echo e($tech->id); ?>"
                                           :checked="modal.techs.includes(<?php echo e($tech->id); ?>)"
                                           @change="toggleTech(<?php echo e($tech->id); ?>)"
                                           class="w-4 h-4 rounded border-border bg-surface text-primary focus:ring-primary">
                                    <span class="text-sm text-text-secondary"><?php echo e($tech->name); ?></span>
                                </label>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </div>
                        <input type="hidden" :required="!isTicketMode && modal.techs.length === 0" class="absolute w-0 h-0 opacity-0" name="technicians_required">
                        <p class="mt-1 text-[10px] text-text-muted" x-show="isTicketMode">Teknisi opsional di sini — kosongkan buat masuk sebagai Ticket Masuk dulu, di-assign belakangan.</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div x-show="!isTicketMode">
                            <label class="block text-xs font-semibold text-text-secondary mb-1">Status <span x-show="!modal.isEdit" class="text-error">*</span></label>
                            <template x-if="!modal.isEdit">
                                <select name="status" x-model="modal.data.status" :required="!isTicketMode" class="w-full text-sm bg-surface text-text-main border border-border rounded-lg px-3 py-2 outline-none focus:border-primary focus:ring-1 focus:ring-primary disabled:bg-surface-muted disabled:text-text-muted">
                                    <option value="terjadwal">Terjadwal</option>
                                    <option value="pending">Pending</option>
                                </select>
                            </template>
                            <template x-if="modal.isEdit">
                                <div>
                                    <span class="inline-flex items-center px-2.5 py-1.5 rounded-lg text-xs font-medium border w-fit"
                                          :class="{
                                              'border-blue-200 text-blue-700 bg-blue-50': modal.data.status === 'terjadwal',
                                              'border-amber-200 dark:border-amber-800/50 text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20': modal.data.status === 'in_progress',
                                              'border-yellow-200 text-yellow-700 bg-yellow-50': modal.data.status === 'pending',
                                              'border-green-200 text-green-700 bg-green-50': modal.data.status === 'selesai',
                                              'border-red-200 text-red-700 dark:text-red-400 bg-red-50 dark:bg-red-900/20': modal.data.status === 'dibatalkan',
                                              'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 bg-slate-50 dark:bg-slate-800/50': modal.data.status === 'draft',
                                          }"
                                          x-text="modal.data.status"></span>
                                    <p class="text-[10px] text-text-muted mt-1">Status realtime — otomatis mengikuti status Task teknisi.</p>
                                    <input type="hidden" name="status" :value="modal.data.status">
                                </div>
                            </template>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-text-secondary mb-1">Prioritas <span class="text-error">*</span></label>
                            <select name="priority" x-model="modal.data.priority"
                                    :disabled="modal.isEdit && !canEditCategory"
                                    required class="w-full text-sm bg-surface text-text-main border border-border rounded-lg px-3 py-2 outline-none focus:border-primary focus:ring-1 focus:ring-primary disabled:bg-surface-muted disabled:text-text-muted">
                                <option value="low">Low</option>
                                <option value="Medium">Medium</option>
                                <option value="High">High</option>
                                <option value="Urgent">Urgent</option>
                            </select>
                            <template x-if="modal.isEdit && !canEditCategory">
                                <input type="hidden" name="priority" :value="modal.data.priority">
                            </template>
                        </div>
                    </div>

                    <div x-show="!isTicketMode && modal.data.status === 'pending'" class="space-y-3 bg-surface-muted border border-border rounded-lg p-3" style="display: none;">
                        <div>
                            <label class="block text-xs font-semibold text-text-secondary mb-1">Alasan Pending <span class="text-error">*</span></label>
                            <input type="text" name="pending_reason" x-model="modal.data.pending_reason" :required="!isTicketMode && modal.data.status === 'pending'" class="w-full text-sm bg-surface text-text-main border border-border rounded-lg px-3 py-2 outline-none focus:border-primary focus:ring-1 focus:ring-primary disabled:bg-surface-muted disabled:text-text-muted">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-text-secondary mb-1">Tgl Request Client <span class="text-error">*</span></label>
                            <input type="date" name="client_request_date" x-model="modal.data.client_request_date" :required="!isTicketMode && modal.data.status === 'pending'" class="w-full text-sm bg-surface text-text-main border border-border rounded-lg px-3 py-2 outline-none focus:border-primary focus:ring-1 focus:ring-primary disabled:bg-surface-muted disabled:text-text-muted font-mono">
                        </div>
                    </div>

                    <div x-show="!isTicketMode">
                        <label class="block text-xs font-semibold text-text-secondary mb-1">Catatan</label>
                        <textarea name="notes" x-model="modal.data.notes" rows="2" class="w-full text-sm bg-surface text-text-main border border-border rounded-lg px-3 py-2 outline-none focus:border-primary focus:ring-1 focus:ring-primary disabled:bg-surface-muted disabled:text-text-muted"></textarea>
                    </div>
                </div>

                <div class="px-5 py-3.5 border-t border-border bg-surface-muted flex items-center justify-end gap-3 rounded-b-xl shrink-0">
                    <button type="button" @click="modal.open = false" class="btn-secondary text-xs cursor-pointer">Batal</button>
                    <button type="submit" :disabled="isSubmitting" class="btn-primary text-xs disabled:opacity-50 cursor-pointer">
                        <span x-show="!isSubmitting">Simpan</span>
                        <span x-show="isSubmitting">Menyimpan...</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    
    <div x-show="cancelModal.open"
         class="fixed inset-0 z-50 flex items-end md:items-center justify-center"
         x-transition:enter="transition ease-out duration-200" 
         x-transition:enter-start="opacity-0" 
         x-transition:enter-end="opacity-100" 
         x-transition:leave="transition ease-in duration-150" 
         x-transition:leave-start="opacity-100" 
         x-transition:leave-end="opacity-0"
         style="display: none;">
        
        <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-xs" @click="cancelModal.open = false"></div>

        <div class="bg-surface border-t md:border border-border w-full max-w-md rounded-t-2xl md:rounded-xl shadow-2xl relative z-10 p-5 font-ui" 
             @click.away="cancelModal.open = false"
             x-transition:enter="transition ease-out duration-250"
             x-transition:enter-start="translate-y-full md:translate-y-4 md:scale-95"
             x-transition:enter-end="translate-y-0 md:translate-y-0 md:scale-100">
            
            <div class="w-12 h-1.5 bg-slate-300 dark:bg-slate-600 rounded-full mx-auto mb-3 md:hidden"></div>

            <h4 class="text-sm font-bold text-text-main mb-1">Batalkan Task <span class="font-mono" x-text="cancelModal.taskNumber"></span></h4>
            <p class="text-xs text-text-muted mb-4">Task akan dibatalkan. Tindakan ini tidak dapat dibatalkan.</p>
            <label class="block text-xs font-semibold text-text-secondary mb-1">Alasan Pembatalan <span class="text-error">*</span></label>
            <textarea x-model="cancelModal.reason" rows="3" class="w-full text-xs border border-border rounded-lg px-3 py-2 mb-4 bg-surface text-text-main outline-none focus:ring-1 focus:ring-primary" placeholder="Contoh: Data ganda, pelanggan batal, salah input POP, dll."></textarea>
            
            <div class="flex justify-end gap-2">
                <button type="button" @click="cancelModal.open = false" class="btn-secondary text-xs px-3 py-2 cursor-pointer">Batal</button>
                <button type="button" :disabled="cancelModal.isSubmitting || !cancelModal.reason.trim()"
                        @click="submitCancelModal()"
                        class="text-xs px-4 py-2 rounded-lg font-semibold text-white disabled:opacity-50 cursor-pointer" style="background:var(--color-error);">
                    <span x-show="!cancelModal.isSubmitting">Ya, Batalkan Task</span>
                    <span x-show="cancelModal.isSubmitting">Membatalkan...</span>
                </button>
            </div>
        </div>
    </div>

    
    <div x-show="teamConflictModal.open"
         class="fixed inset-0 z-50 overflow-y-auto flex items-end md:items-center justify-center"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         style="display: none;">

        <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-xs" @click="teamConflictModal.open = false"></div>

        <div class="bg-surface border-t md:border border-border w-full max-w-lg rounded-t-2xl md:rounded-xl shadow-2xl relative z-10 max-h-[85vh] flex flex-col overflow-hidden font-ui"
             @click.away="teamConflictModal.open = false"
             x-transition:enter="transition ease-out duration-250"
             x-transition:enter-start="translate-y-full md:translate-y-4 md:scale-95"
             x-transition:enter-end="translate-y-0 md:translate-y-0 md:scale-100">

            <div class="w-12 h-1.5 bg-slate-300 dark:bg-slate-600 rounded-full mx-auto my-2 shrink-0 md:hidden"></div>

            <div class="px-5 py-3.5 border-b border-border flex items-center justify-between bg-surface-muted rounded-t-xl shrink-0">
                <h3 class="text-sm font-bold text-text-main">Konflik Tim Terdeteksi</h3>
                <button type="button" @click="teamConflictModal.open = false" class="text-text-muted hover:text-text-main p-1 cursor-pointer">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="p-5 overflow-y-auto space-y-4 flex-1 custom-scrollbar">
                <template x-for="c in teamConflictModal.conflicts" :key="c.task_id">
                    <div class="border border-border rounded-lg p-3 bg-surface-muted/40">
                        <p class="text-xs text-text-secondary mb-2.5">
                            Task <span class="font-bold text-text-main font-mono" x-text="c.task_number"></span> menugaskan teknisi yang masing-masing sudah ada di tim berbeda. Taruh di tim mana?
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <template x-for="cand in c.candidates" :key="cand.team_id">
                                <button type="button" @click="resolveTeamConflict(c.task_id, cand.team_id)" class="btn-primary text-xs py-1.5 px-3 cursor-pointer" x-text="cand.team_name"></button>
                            </template>
                            <button type="button" @click="resolveTeamConflict(c.task_id, null)" class="btn-secondary text-xs py-1.5 px-3 cursor-pointer">Buat Tim Baru</button>
                        </div>
                    </div>
                </template>
                <p x-show="teamConflictModal.conflicts.length === 0" class="text-xs text-text-muted text-center py-3">Tidak ada konflik.</p>
            </div>
        </div>
    </div>

    
    <div x-show="teamSelectionModal.open"
         class="fixed inset-0 z-50 overflow-y-auto flex items-end md:items-center justify-center"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         style="display: none;">

        <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-xs" @click="teamSelectionModal.open = false"></div>

        <div class="bg-surface border-t md:border border-border w-full max-w-lg rounded-t-2xl md:rounded-xl shadow-2xl relative z-10 max-h-[85vh] flex flex-col overflow-hidden font-ui"
             @click.away="teamSelectionModal.open = false"
             x-transition:enter="transition ease-out duration-250"
             x-transition:enter-start="translate-y-full md:translate-y-4 md:scale-95"
             x-transition:enter-end="translate-y-0 md:translate-y-0 md:scale-100">

            <div class="w-12 h-1.5 bg-slate-300 dark:bg-slate-600 rounded-full mx-auto my-2 shrink-0 md:hidden"></div>

            <div class="px-5 py-3.5 border-b border-border flex items-center justify-between bg-surface-muted rounded-t-xl shrink-0">
                <h3 class="text-sm font-bold text-text-main">Pilih Tim untuk Task</h3>
                <button type="button" @click="teamSelectionModal.open = false" class="text-text-muted hover:text-text-main p-1 cursor-pointer">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="p-5 overflow-y-auto space-y-4 flex-1 custom-scrollbar">
                <div class="border border-border rounded-lg p-3 bg-surface-muted/50">
                    <p class="text-xs text-text-secondary leading-relaxed">
                        Pilih tim kerja pada tanggal <span class="font-semibold text-text-main" x-text="teamSelectionModal.taskDate"></span> untuk memasukkan task <span class="font-semibold text-text-main font-mono" x-text="teamSelectionModal.taskNumber"></span> (<span x-text="teamSelectionModal.taskTugas"></span>):
                    </p>
                </div>

                <div class="space-y-2">
                    <label class="block text-xs font-semibold text-text-secondary uppercase tracking-wider mb-2">Tim Tersedia</label>
                    <div class="flex flex-col gap-2">
                        <template x-for="t in teamSelectionModal.teams" :key="t.id">
                            <button type="button" 
                                    @click="assignToTeam(teamSelectionModal.taskId, t.id); teamSelectionModal.open = false" 
                                    class="w-full text-left px-4 py-3 border border-border rounded-lg hover:bg-surface-muted hover:border-primary/50 transition-colors flex items-center justify-between group cursor-pointer">
                                <div>
                                    <span class="text-xs font-bold text-text-main group-hover:text-primary transition-colors" x-text="t.name"></span>
                                    <div class="flex items-center gap-1.5 mt-1">
                                        <template x-for="m in t.members" :key="m.id">
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-sky-50 dark:bg-sky-900/20 text-sky-700 dark:text-sky-400 border border-sky-100 dark:border-sky-800/30" x-text="m.name"></span>
                                        </template>
                                    </div>
                                </div>
                                <svg class="w-4 h-4 text-text-muted group-hover:text-primary transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                        </template>
                        <p x-show="teamSelectionModal.teams.length === 0" class="text-xs text-text-muted text-center py-4 bg-surface-muted/30 border border-dashed border-border rounded-lg">
                            Tidak ada tim kerja pada tanggal ini.
                        </p>
                    </div>
                </div>

                <div class="pt-2 border-t border-border">
                    <button type="button" @click="assignToTeam(teamSelectionModal.taskId, null); teamSelectionModal.open = false" class="btn-primary text-xs w-full py-2.5 flex justify-center items-center gap-2 cursor-pointer">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Buat Tim Baru
                    </button>
                </div>
            </div>
        </div>
    </div>

    
    <div x-show="switchTechModal.open"
         class="fixed inset-0 z-50 overflow-y-auto flex items-end md:items-center justify-center"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         style="display: none;">

        <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-xs" @click="switchTechModal.open = false"></div>

        <div class="bg-surface border-t md:border border-border w-full max-w-md rounded-t-2xl md:rounded-xl shadow-2xl relative z-10 max-h-[85vh] flex flex-col overflow-hidden font-ui"
             @click.away="switchTechModal.open = false"
             x-transition:enter="transition ease-out duration-250"
             x-transition:enter-start="translate-y-full md:translate-y-4 md:scale-95"
             x-transition:enter-end="translate-y-0 md:translate-y-0 md:scale-100">

            <div class="w-12 h-1.5 bg-slate-300 dark:bg-slate-600 rounded-full mx-auto my-2 shrink-0 md:hidden"></div>

            <div class="px-5 py-3.5 border-b border-border flex items-center justify-between bg-surface-muted rounded-t-xl shrink-0">
                <h3 class="text-sm font-bold text-text-main">Switch Teknisi antar Team</h3>
                <button type="button" @click="switchTechModal.open = false" class="text-text-muted hover:text-text-main p-1 cursor-pointer">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="p-5 space-y-4 overflow-y-auto flex-1 custom-scrollbar">
                <p class="text-xs text-text-secondary leading-relaxed bg-surface-muted p-3 rounded-lg border border-border">
                    Pindahkan <span class="font-bold text-text-main" x-text="switchTechModal.technicianName"></span>
                    dari task <span class="font-bold text-text-main font-mono" x-text="switchTechModal.fromTaskNumber"></span>
                    (<span x-text="switchTechModal.fromTaskTugas"></span>) ke task lain — wajib pilih pengganti supaya task asal gak kosong teknisi.
                </p>

                <div>
                    <label class="block text-xs font-semibold text-text-secondary mb-1">Task Tujuan <span class="text-error">*</span></label>
                    <select x-model="switchTechModal.toTaskId" class="w-full text-sm bg-surface text-text-main border border-border rounded-lg px-3 py-2 outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                        <option value="">— Pilih Task Tujuan —</option>
                        <template x-for="t in switchTargetTasks" :key="t.id">
                            <option :value="t.id" x-text="t.task_number + ' — ' + t.tugas"></option>
                        </template>
                    </select>
                    <p class="mt-1 text-[10px] text-text-muted" x-show="switchTargetTasks.length === 0">Gak ada task lain di tanggal yang sama (<span x-text="switchTechModal.fromTaskDate"></span>) buat dijadikan tujuan.</p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-text-secondary mb-1">Pengganti di Task Asal <span class="text-error">*</span></label>
                    <select x-model="switchTechModal.replacementId" class="w-full text-sm bg-surface text-text-main border border-border rounded-lg px-3 py-2 outline-none focus:border-primary focus:ring-1 focus:ring-primary">
                        <option value="">— Pilih Pengganti —</option>
                        <template x-for="t in switchReplacementCandidates" :key="t.id">
                            <option :value="t.id" x-text="t.name"></option>
                        </template>
                    </select>
                </div>
            </div>

            <div class="px-5 py-3.5 border-t border-border bg-surface-muted flex items-center justify-end gap-3 rounded-b-xl shrink-0">
                <button type="button" @click="switchTechModal.open = false" class="btn-secondary text-xs cursor-pointer">Batal</button>
                <button type="button" :disabled="switchTechModal.isSubmitting || !switchTechModal.toTaskId || !switchTechModal.replacementId"
                        @click="submitSwitchTechnician()" class="btn-primary text-xs disabled:opacity-50 cursor-pointer">
                    <span x-show="!switchTechModal.isSubmitting">Switch Sekarang</span>
                    <span x-show="switchTechModal.isSubmitting">Memproses...</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    function fopTaskPageHandler() {
        return {
            isSubmitting: false,
            filterDrawerOpen: false,
            searchTech: '',
            customerSearchResults: [],
            isSearchingCustomer: false,
            ticketValues: <?php echo json_encode(\App\Enums\TaskType::ticketValues(), 15, 512) ?>,
            ticketCidQuery: '',
            ticketCustomerResults: [],
            ticketSelectedCustomer: null,
            ticketSearching: false,
            modal: {
                open: false,
                isEdit: false,
                updateUrl: '',
                data: {
                    id: '', task_number: '', task_date: '', category: '', tugas: '',
                    customer_id: '', village_id: '', pop_id: '', issue: '', notes: '',
                    detail_keluhan: '', catatan_teknis: '',
                    ticket: null,
                    status: 'terjadwal', priority: 'low', pending_reason: '', client_request_date: ''
                },
                techs: []
            },

            get isEditingLinkedTicket() {
                return this.modal.isEdit && this.modal.data.ticket !== null;
            },

            cancelModal: {
                open: false,
                taskId: null,
                taskNumber: '',
                reason: '',
                isSubmitting: false,
            },

            openCancelModal(taskId, taskNumber) {
                this.cancelModal = {
                    open: true,
                    taskId: taskId,
                    taskNumber: taskNumber,
                    reason: '',
                    isSubmitting: false,
                };
            },

            submitCancelModal() {
                if (!this.cancelModal.reason.trim()) return;
                this.cancelModal.isSubmitting = true;
                const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                fetch(`<?php echo e(url('/fop-tasks')); ?>/${this.cancelModal.taskId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token
                    },
                    body: JSON.stringify({
                        _method: 'PUT',
                        status: 'dibatalkan',
                        cancel_reason: this.cancelModal.reason.trim()
                    })
                })
                .then(res => res.json())
                .then(data => {
                    this.cancelModal.isSubmitting = false;
                    if (data.success) {
                        this.showToast('success', data.message);
                        this.cancelModal.open = false;
                        this.refreshFopTaskRow(this.cancelModal.taskId);
                    } else {
                        this.showToast('error', data.message || 'Gagal membatalkan task.');
                    }
                })
                .catch(() => {
                    this.cancelModal.isSubmitting = false;
                    this.showToast('error', 'Terjadi kesalahan jaringan.');
                });
            },

            allTasksData: <?php echo json_encode($switchTargetTasks, 15, 512) ?>,
            techniciansData: <?php echo json_encode($technicians, 15, 512) ?>,
            teamConflictModal: {
                open: false,
                conflicts: <?php echo json_encode($teamConflicts, 15, 512) ?>
            },

            teamSelectionModal: {
                open: false,
                taskId: null,
                taskNumber: '',
                taskTugas: '',
                taskDate: '',
                teams: []
            },

            openTeamSelectionModal(taskId, taskNumber, taskTugas, taskDate) {
                const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                fetch(`<?php echo e(url('/fop-tasks')); ?>/${taskId}/available-teams?date=${taskDate}`, {
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token }
                })
                .then(res => res.json())
                .then(data => {
                    this.teamSelectionModal = {
                        open: true,
                        taskId: taskId,
                        taskNumber: taskNumber,
                        taskTugas: taskTugas,
                        taskDate: taskDate,
                        teams: data.teams || []
                    };
                })
                .catch(() => this.showToast('error', 'Gagal memuat tim yang tersedia.'));
            },

            switchTechModal: {
                open: false,
                technicianId: '',
                technicianName: '',
                fromTaskId: '',
                fromTaskNumber: '',
                fromTaskTugas: '',
                fromTaskDate: '',
                toTaskId: '',
                replacementId: '',
                isSubmitting: false,
            },

            openSwitchModal(taskId, taskNumber, taskTugas, taskDate, techId, techName) {
                this.switchTechModal = {
                    open: true,
                    technicianId: techId,
                    technicianName: techName,
                    fromTaskId: taskId,
                    fromTaskNumber: taskNumber,
                    fromTaskTugas: taskTugas,
                    fromTaskDate: taskDate,
                    toTaskId: '',
                    replacementId: '',
                    isSubmitting: false,
                };
            },

            get switchTargetTasks() {
                return this.allTasksData.filter(t =>
                    t.task_date === this.switchTechModal.fromTaskDate &&
                    t.id !== this.switchTechModal.fromTaskId
                );
            },

            get switchReplacementCandidates() {
                return this.techniciansData.filter(t => t.id !== this.switchTechModal.technicianId);
            },

            submitSwitchTechnician() {
                if (!this.switchTechModal.toTaskId || !this.switchTechModal.replacementId) return;
                this.switchTechModal.isSubmitting = true;
                const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                fetch('<?php echo e(route('fop-tasks.switch-technician')); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token
                    },
                    body: JSON.stringify({
                        technician_id: this.switchTechModal.technicianId,
                        from_task_id: this.switchTechModal.fromTaskId,
                        to_task_id: this.switchTechModal.toTaskId,
                        replacement_technician_id: this.switchTechModal.replacementId,
                    })
                })
                .then(res => res.json())
                .then(data => {
                    this.switchTechModal.isSubmitting = false;
                    if (data.success) {
                        this.showToast('success', data.message);
                        this.refreshFopTaskRow(this.switchTechModal.fromTaskId);
                        this.refreshFopTaskRow(this.switchTechModal.toTaskId);
                        this.switchTechModal.open = false;
                    } else {
                        this.showToast('error', data.message || 'Gagal switch teknisi.');
                    }
                })
                .catch(() => {
                    this.switchTechModal.isSubmitting = false;
                    this.showToast('error', 'Terjadi kesalahan jaringan.');
                });
            },

            allCategoriesData: <?php echo json_encode($categories, 15, 512) ?>,
            manualCategoriesData: <?php echo json_encode($manualCategories, 15, 512) ?>,
            autoOnlyCategoryValues: <?php echo json_encode(\App\Enums\TaskType::autoOnlyValues(), 15, 512) ?>,
            canEditCategory: <?php echo json_encode($canEditFopTaskType, 15, 512) ?>,

            get isEditingLockedSurveyPsb() {
                return this.modal.isEdit && this.autoOnlyCategoryValues.includes(this.modal.data.category);
            },

            get availableCategories() {
                return this.modal.isEdit ? this.allCategoriesData : this.manualCategoriesData;
            },

            triggerConflictModal(taskId, taskNumber, candidates) {
                this.teamConflictModal.conflicts = [{
                    task_id: taskId,
                    task_number: taskNumber,
                    candidates: candidates
                }];
                this.teamConflictModal.open = true;
            },

            initTeamConflicts() {
                if (this.teamConflictModal.conflicts.length > 0) {
                    this.teamConflictModal.open = true;
                }
            },

            resolveTeamConflict(taskId, teamId) {
                const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                fetch(`<?php echo e(url('/fop-tasks')); ?>/${taskId}/assign-to-team`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token
                    },
                    body: JSON.stringify({ team_id: teamId })
                })
                .then(res => res.json())
                .then(data => {
                    this.teamConflictModal.conflicts = this.teamConflictModal.conflicts.filter(c => c.task_id !== taskId);
                    if (this.teamConflictModal.conflicts.length === 0) this.teamConflictModal.open = false;
                    this.showToast('success', data.message);
                    this.refreshFopTaskRow(taskId);
                })
                .catch(() => this.showToast('error', 'Terjadi kesalahan jaringan.'));
            },

            assignToTeam(taskId, teamId) {
                if (teamId === undefined) return;
                const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                fetch(`<?php echo e(url('/fop-tasks')); ?>/${taskId}/assign-to-team`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token
                    },
                    body: JSON.stringify({ team_id: teamId })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        this.showToast('success', data.message);
                        this.refreshFopTaskRow(taskId);
                    } else {
                        if (data.team_conflicts && data.team_conflicts.length > 0) {
                            this.teamConflictModal.conflicts = data.team_conflicts;
                            this.teamConflictModal.open = true;
                            this.showToast('warning', 'Konflik team terdeteksi.');
                        } else {
                            this.showToast('error', data.message || 'Gagal memasukkan ke Team.');
                        }
                    }
                })
                .catch(() => this.showToast('error', 'Terjadi kesalahan jaringan.'));
            },

            getTechName(id) {
                const tech = this.techniciansData.find(t => t.id == id);
                return tech ? tech.name : '';
            },

            async searchCustomer() {
                this.modal.data.customer_id = '';
                if (this.modal.data.tugas.length < 2) { 
                    this.customerSearchResults = []; 
                    return; 
                }
                try {
                    const res = await fetch(`/api/tasks/search-customers?q=${encodeURIComponent(this.modal.data.tugas)}`, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    this.customerSearchResults = await res.json();
                } catch (e) { 
                    this.customerSearchResults = []; 
                }
            },
            
            selectCustomer(c) {
                this.modal.data.tugas = c.label;
                this.modal.data.customer_id = c.id;
                if (c.pop_id) { this.modal.data.pop_id = c.pop_id; }
                if (c.village_id) { this.modal.data.village_id = c.village_id; }
                this.customerSearchResults = [];
            },

            async searchTicketCustomer() {
                const q = this.ticketCidQuery.trim();
                if (q.length < 2) {
                    this.ticketCustomerResults = [];
                    return;
                }
                this.ticketSearching = true;
                try {
                    const res = await fetch(`<?php echo e(route('tickets.lookup-customer')); ?>?q=${encodeURIComponent(q)}`, {
                        headers: { 'Accept': 'application/json' },
                    });
                    this.ticketCustomerResults = res.ok ? await res.json() : [];
                } catch (e) {
                    this.ticketCustomerResults = [];
                } finally {
                    this.ticketSearching = false;
                }
            },

            pickTicketCustomer(c) {
                this.ticketSelectedCustomer = c;
                this.ticketCidQuery = c.label;
                this.ticketCustomerResults = [];
                this.modal.data.customer_id = c.id;
            },

            clearTicketCustomer() {
                this.ticketSelectedCustomer = null;
                this.ticketCidQuery = '';
                this.ticketCustomerResults = [];
                this.modal.data.customer_id = '';
            },

            get isTicketMode() {
                return this.ticketValues.includes(this.modal.data.category);
            },

            onCategoryChange() {
                if (this.isTicketMode) {
                    this.modal.data.tugas = '';
                    this.modal.data.issue = '';
                    this.modal.data.pop_id = '';
                    this.modal.data.village_id = '';
                } else {
                    this.clearTicketCustomer();
                    this.modal.data.detail_keluhan = '';
                    this.modal.data.catatan_teknis = '';
                }
            },

            toggleTech(id) {
                const idx = this.modal.techs.indexOf(id);
                if (idx > -1) {
                    this.modal.techs.splice(idx, 1);
                } else {
                    this.modal.techs.push(id);
                }
            },

            openCreateModal() {
                this.modal.isEdit = false;
                this.modal.updateUrl = '';
                this.clearTicketCustomer();
                this.modal.data = {
                    id: '',
                    task_number: '',
                    task_date: new Date().toISOString().slice(0, 16),
                    category: '',
                    tugas: '',
                    customer_id: '',
                    village_id: '',
                    pop_id: '',
                    issue: '',
                    notes: '',
                    detail_keluhan: '',
                    catatan_teknis: '',
                    ticket: null,
                    status: 'terjadwal',
                    priority: 'low',
                    pending_reason: '',
                    client_request_date: ''
                };
                this.modal.techs = [];
                this.customerSearchResults = [];
                this.searchTech = '';
                this.modal.open = true;
            },

            openEditModal(task, techIds, updateUrl) {
                this.modal.isEdit = true;
                this.modal.updateUrl = updateUrl || '';
                this.customerSearchResults = [];
                this.searchTech = '';

                let dateStr = '';
                if (task.task_date) {
                    const d = new Date(task.task_date);
                    if (!isNaN(d.getTime())) {
                        const pad = n => String(n).padStart(2, '0');
                        dateStr = `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
                    }
                }

                let reqDateStr = '';
                if (task.client_request_date) {
                    const rd = new Date(task.client_request_date);
                    if (!isNaN(rd.getTime())) {
                        const pad = n => String(n).padStart(2, '0');
                        reqDateStr = `${rd.getFullYear()}-${pad(rd.getMonth()+1)}-${pad(rd.getDate())}`;
                    }
                }

                const ticket = task.ticket || null;
                if (ticket) {
                    const ticketCust = ticket.customer || null;
                    const popObj = ticketCust?.pop || null;
                    const popName = popObj?.name || '';
                    const coords = (ticket.customer_latitude && ticket.customer_longitude)
                        ? `${ticket.customer_latitude}, ${ticket.customer_longitude}`
                        : '';
                    const mapsUrl = coords ? `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(coords)}` : '';

                    this.ticketSelectedCustomer = {
                        id: ticketCust?.id ?? ticket.customer_id,
                        cid: ticketCust?.cid || '—',
                        nama: ticket.customer_name || '—',
                        alamat: ticket.customer_address || '—',
                        no_hp: ticket.customer_phone || '—',
                        pop: popName || '—',
                        odp: ticket.customer_odp || '—',
                        paket: ticket.customer_package || '—',
                        perangkat: ticket.customer_device || '—',
                        koordinat: coords || '—',
                        maps_url: mapsUrl,
                        label: `${ticketCust?.cid || ''} - ${ticket.customer_name || ''}`.trim(),
                    };
                    this.ticketCidQuery = this.ticketSelectedCustomer.label;
                } else {
                    this.clearTicketCustomer();
                }

                this.modal.data = {
                    id: task.id,
                    task_number: task.task_number || '',
                    task_date: dateStr,
                    category: task.category?.value ?? task.category,
                    tugas: task.tugas || '',
                    customer_id: task.customer_id || '',
                    village_id: task.village_id || '',
                    pop_id: task.pop_id || '',
                    issue: task.issue || '',
                    notes: task.notes || '',
                    detail_keluhan: ticket ? (ticket.detail_keluhan || '') : '',
                    catatan_teknis: ticket ? (ticket.catatan_teknis || '') : '',
                    ticket: ticket,
                    status: task.status?.value ?? task.status,
                    priority: task.priority?.value ?? task.priority,
                    pending_reason: task.pending_reason || '',
                    client_request_date: reqDateStr
                };
                this.modal.techs = techIds ? [...techIds] : [];
                this.modal.open = true;
            },

            get formAction() {
                if (this.modal.isEdit) {
                    return this.modal.updateUrl;
                }
                if (this.isTicketMode) {
                    return '<?php echo e(route('tickets.store')); ?>';
                }
                return '<?php echo e(route('fop-tasks.store')); ?>';
            },

            updatePriority(taskId, newPriority) {
                const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                fetch(`<?php echo e(url('/fop-tasks')); ?>/${taskId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token
                    },
                    body: JSON.stringify({
                        _method: 'PUT',
                        priority: newPriority
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        this.showToast('success', 'Prioritas berhasil diubah.');
                    } else {
                        this.showToast('error', data.message || 'Gagal mengubah prioritas.');
                    }
                })
                .catch(() => this.showToast('error', 'Terjadi kesalahan jaringan.'));
            },

            showToast(type, message) {
                if (window.Toast) {
                    if (type === 'success') window.Toast.success('Berhasil', message);
                    else if (type === 'error') window.Toast.error('Gagal', message);
                    else if (type === 'warning') window.Toast.warning('Peringatan', message);
                    else window.Toast.info('Informasi', message);
                } else {
                    alert(message);
                }
            },

            refreshFopTaskRow(taskId) {
                const row = document.getElementById('fop-task-row-' + taskId);
                const card = document.getElementById('fop-task-card-' + taskId);
                if (!row && !card) return;

                fetch(`<?php echo e(url('/fop-tasks')); ?>/${taskId}/row`, {
                    headers: {
                        'Accept': 'text/html',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                }).then(res => {
                    if (res.status === 204) {
                        if (row) row.remove();
                        if (card) card.remove();
                        return null;
                    }
                    return res.text();
                }).then(html => {
                    if (!html) return;

                    const wrapper = document.createElement('table');
                    wrapper.innerHTML = '<tbody><tr>' + html + '</tr></tbody>';

                    if (row) {
                        ['tech', 'team', 'status'].forEach(part => {
                            const fresh = wrapper.querySelector('#' + part + '-cell-' + taskId);
                            const current = row.querySelector('#' + part + '-cell-' + taskId);
                            if (fresh && current) {
                                current.replaceWith(fresh);
                            }
                        });
                        if (window.Alpine) {
                            window.Alpine.initTree(row);
                        }
                    }

                    if (card && window.Alpine) {
                        window.Alpine.initTree(card);
                    }
                }).catch(() => {
                    // Diam-diam gagal — gak ganggu kerjaan FOP.
                });
            },

            initFopTaskEchoListeners() {
                if (typeof window.Echo === 'undefined' || !window.Echo) return;

                const popIds = [...new Set(
                    Array.from(document.querySelectorAll('[data-pop-id]')).map(el => el.getAttribute('data-pop-id'))
                )];

                popIds.forEach(popId => {
                    window.Echo.private('fop-tasks.' + popId)
                        .listen('.FopTaskUpdated', (e) => this.refreshFopTaskRow(e.fop_task_id))
                        .listen('TaskStarted', (e) => { if (e.fop_task_id) this.refreshFopTaskRow(e.fop_task_id); })
                        .listen('TaskCompleted', (e) => { if (e.fop_task_id) this.refreshFopTaskRow(e.fop_task_id); });
                });
            }
        };
    }
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/fop_tasks/index.blade.php ENDPATH**/ ?>