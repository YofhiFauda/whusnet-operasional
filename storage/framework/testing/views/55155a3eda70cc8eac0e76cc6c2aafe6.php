<?php $__env->startSection('title', 'FOP Dashboard'); ?>
<?php $__env->startSection('page_title', 'FOP Dashboard'); ?>
<?php $__env->startSection('breadcrumb_parent', 'Dashboard'); ?>
<?php $__env->startSection('breadcrumb_parent_url', '/'); ?>

<?php $__env->startSection('content'); ?>
<div x-data="fopDashboardHandler()" class="flex flex-col gap-6 px-6 py-6 max-w-screen-2xl mx-auto font-sans text-text-main">

    
    <div class="page-header flex flex-col gap-1.5 md:flex-row md:items-center md:justify-between mb-2">
        <div class="page-header-left">
            <div class="flex items-center gap-2">
                <svg class="h-5 w-5 text-primary shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                </svg>
                <h1 class="text-xl font-bold text-text-main leading-tight tracking-tight font-sans">FOP Dashboard</h1>
            </div>
            <p class="text-xs text-text-muted mt-0.5 font-sans">Ringkasan pengerjaan harian lapangan dan koordinasi tim teknisi · <?php echo e(now()->translatedFormat('l, d F Y')); ?></p>
        </div>
    </div>

    
    <div id="stat-cards-container" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Card 1: Antrean Survey -->
        <div class="metric-card <?php echo e(($stats['overdue_survey'] ?? 0) > 0 ? 'status-error' : 'status-info'); ?>">
            <div>
                <div class="metric-card-label">
                    <span>Antrean Survey</span>
                    <svg class="h-4 w-4 text-text-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2" />
                    </svg>
                </div>
                <div class="metric-card-value-container">
                    <p class="metric-card-value"><?php echo e($stats['antrian_survey']); ?></p>
                    <?php if(($stats['overdue_survey'] ?? 0) > 0): ?>
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold font-sans uppercase tracking-wider bg-error-bg text-error border border-error-border animate-pulse">
                            <?php echo e($stats['overdue_survey']); ?> Overdue
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <p class="metric-card-footer">Pelanggan baru terdaftar</p>
        </div>

        <!-- Card 2: Perlu Aksi FOP -->
        <div class="metric-card <?php echo e(($stats['overdue_installation'] ?? 0) > 0 ? 'status-error' : (($stats['perlu_aksi_fop'] ?? 0) > 0 ? 'status-warning' : '')); ?>">
            <div>
                <div class="metric-card-label">
                    <span>Perlu Aksi FOP</span>
                    <svg class="h-4 w-4 text-text-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <div class="metric-card-value-container">
                    <p class="metric-card-value"><?php echo e($stats['perlu_aksi_fop']); ?></p>
                    <?php if(($stats['overdue_installation'] ?? 0) > 0): ?>
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold font-sans uppercase tracking-wider bg-error-bg text-error border border-error-border animate-pulse">
                            <?php echo e($stats['overdue_installation']); ?> Overdue
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <p class="metric-card-footer">Survey selesai & verifikasi</p>
        </div>

        <!-- Card 3: Sedang Berjalan -->
        <div class="metric-card status-info">
            <div>
                <div class="metric-card-label">
                    <span>Sedang Berjalan</span>
                    <svg class="h-4 w-4 text-text-muted shrink-0 animate-spin" style="animation-duration: 8s" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.21 8H17" />
                    </svg>
                </div>
                <div class="metric-card-value-container">
                    <p class="metric-card-value"><?php echo e($stats['berjalan']); ?></p>
                </div>
            </div>
            <p class="metric-card-footer">Task aktif lapangan hari ini</p>
        </div>

        <!-- Card 4: Selesai Hari Ini -->
        <div class="metric-card status-success">
            <div>
                <div class="metric-card-label">
                    <span>Selesai Hari Ini</span>
                    <svg class="h-4 w-4 text-text-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="metric-card-value-container">
                    <p class="metric-card-value"><?php echo e($stats['selesai_hari_ini']); ?></p>
                </div>
            </div>
            <p class="metric-card-footer">Target harian yang tercapai</p>
        </div>
    </div>

    
    <div class="flex flex-col gap-3">
        <div class="flex items-center justify-between">
            <p class="text-[10px] font-bold uppercase tracking-widest text-text-muted font-sans">Team FOP Aktif</p>
            <a href="<?php echo e(route('fop-tasks.index')); ?>" class="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:text-primary-hover transition-colors cursor-pointer">
                <span>Kelola Tim & Jadwal</span>
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                </svg>
            </a>
        </div>
        <?php if($activeFopTeams->count() > 0): ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-6">
            <?php $__currentLoopData = $activeFopTeams; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $team): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div @dragover.prevent="onTeamDragOver(<?php echo e($team['id']); ?>)"
                 @dragleave="onTeamDragLeave(<?php echo e($team['id']); ?>)"
                 @drop.prevent="onTeamDrop(<?php echo e($team['id']); ?>)"
                 :class="dragOverTeamId === <?php echo e($team['id']); ?> ? 'border-primary ring-4 ring-primary/10 bg-primary/5 shadow-md scale-[1.01]' : 'border-border bg-surface shadow-sm'"
                 class="flex flex-col border rounded-xl overflow-hidden hover:shadow-md hover:border-primary-border/60 transition-all duration-200">
                
                
                <button type="button" @click="openTeamDetail(<?php echo e($team['id']); ?>)"
                        class="text-left px-4 py-3 border-b border-border bg-surface-muted hover:bg-slate-200/50 dark:hover:bg-slate-700/50 flex items-center justify-between shrink-0 cursor-pointer transition-colors duration-150">
                    <span class="text-xs font-bold text-text-main font-sans"><?php echo e($team['name']); ?></span>
                    <span class="text-[10px] text-text-muted font-medium font-sans bg-surface px-2 py-0.5 rounded border border-border shadow-xs"><?php echo e($team['work_date']); ?></span>
                </button>

                
                <div class="p-4 flex-1 flex flex-col gap-2 max-h-48 overflow-y-auto custom-scrollbar">
                    <?php $__empty_1 = true; $__currentLoopData = $team['tasks']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <div draggable="<?php echo e($t['draggable'] ? 'true' : 'false'); ?>"
                         @dragstart="<?php echo e($t['draggable'] ? 'true' : 'false'); ?> ? startTaskDrag($event, <?php echo e($t['fop_task_id']); ?>, <?php echo e($team['id']); ?>, <?php echo \Illuminate\Support\Js::from($t['tugas'])->toHtml() ?>) : $event.preventDefault()"
                         @dragend="endTaskDrag()"
                         @click="openTeamDetail(<?php echo e($team['id']); ?>)"
                         :class="[
                            'flex items-center justify-between text-[11px] rounded-lg px-2.5 py-2 border transition-all duration-150 shadow-xs',
                            dragging && dragging.fopTaskId === <?php echo e($t['fop_task_id']); ?>

                                ? 'opacity-40 border-dashed bg-slate-100 dark:bg-slate-700/50 border-slate-300 dark:border-slate-600'
                                : (<?php echo e($t['draggable'] ? 'true' : 'false'); ?>

                                    ? 'cursor-grab hover:bg-slate-100 dark:hover:bg-slate-700 dark:hover:bg-slate-700/50 dark:hover:bg-slate-700 dark:hover:bg-slate-700/50 dark:hover:bg-slate-750 border-border hover:border-primary/30'
                                    : 'cursor-not-allowed bg-slate-50 dark:bg-slate-800/50 border-slate-200 dark:border-slate-700 opacity-60')
                         ]">
                        <div class="flex items-center gap-2 truncate flex-1 mr-1">
                            <?php if($t['draggable']): ?>
                            <!-- Drag Handle Icon -->
                            <svg class="h-3.5 w-3.5 text-text-disabled shrink-0 cursor-grab" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M8.5 6a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm5 0a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm5 0a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm-10 6a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm5 0a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm5 0a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm-10 6a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm5 0a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm5 0a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z"/>
                            </svg>
                            <?php endif; ?>
                            <div class="flex flex-col gap-0.5 truncate flex-1">
                                <span class="text-text-main font-semibold truncate"><?php echo e($t['tugas']); ?></span>
                                <span class="text-[9px] text-text-muted truncate font-sans"><?php echo e($t['customer_name']); ?></span>
                            </div>
                        </div>
                        <span class="text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full shrink-0 ml-2 border font-sans"
                            style="<?php echo e($t['status_style']); ?>">
                            <?php echo e($t['status']); ?>

                        </span>
                    </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <div class="flex flex-col items-center justify-center py-6 text-text-muted">
                        <svg class="h-6 w-6 text-text-disabled mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0a2 2 0 01-2 2H6a2 2 0 01-2-2m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5" />
                        </svg>
                        <p class="text-[10px] italic font-sans">Belum ada pengerjaan hari ini</p>
                    </div>
                    <?php endif; ?>
                </div>

                
                <button type="button" @click="openTeamDetail(<?php echo e($team['id']); ?>)"
                        class="text-left px-4 py-3 border-t border-border bg-surface hover:bg-surface-muted flex items-center justify-between shrink-0 cursor-pointer transition-colors duration-150">
                    <div class="flex items-center -space-x-2">
                        <?php $__currentLoopData = $team['members']->take(4); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $member): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <span class="h-6 w-6 rounded-full bg-primary text-white border-2 border-surface flex items-center justify-center text-[9px] font-bold tracking-tight font-sans shadow-sm" title="<?php echo e($member['name']); ?>">
                            <?php echo e($member['initials']); ?>

                        </span>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        <?php if($team['members']->count() > 4): ?>
                        <span class="h-6 w-6 rounded-full bg-slate-200 text-slate-700 dark:text-slate-300 border-2 border-surface flex items-center justify-center text-[9px] font-bold font-sans shadow-sm">
                            +<?php echo e($team['members']->count() - 4); ?>

                        </span>
                        <?php endif; ?>
                    </div>
                    <span class="text-[10px] text-text-muted font-sans font-medium"><?php echo e($team['members']->count()); ?> Teknisi · <?php echo e($team['total_tasks']); ?> Task</span>
                </button>
            </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
        <?php else: ?>
        <div class="bg-surface border border-border rounded-xl flex flex-col items-center justify-center py-12 text-text-muted shadow-sm">
            <svg class="h-10 w-10 text-text-disabled mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
            <p class="text-sm font-medium font-sans">Belum ada tim yang aktif hari ini.</p>
            <p class="text-xs text-text-muted mt-0.5 font-sans">Jadwalkan task di Antrean Survey untuk mengaktifkan tim.</p>
        </div>
        <?php endif; ?>
    </div>

    
    <div x-show="teamDetail.open"
         x-effect="document.body.classList.toggle('overflow-hidden', teamDetail.open)"
         class="fixed inset-0 z-50 overflow-y-auto"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         style="display: none;">

        <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-md" @click="teamDetail.open = false"></div>

        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-surface border border-border w-full max-w-lg rounded-xl shadow-xl relative z-10 overflow-hidden"
                 x-show="teamDetail.open"
                 @click.away="teamDetail.open = false"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95 translate-y-4"
                 x-transition:enter-end="opacity-100 scale-100 translate-y-0">

                <template x-if="teamDetail.data">
                    <div>
                        
                        <div class="px-5 py-4 border-b border-border flex items-center justify-between bg-surface-muted">
                            <div>
                                <h3 class="text-sm font-bold text-text-main font-sans" x-text="teamDetail.data.name"></h3>
                                <p class="text-[10px] text-text-muted font-medium font-sans mt-0.5" x-text="teamDetail.data.work_date"></p>
                            </div>
                            <button type="button" @click="teamDetail.open = false" class="text-text-disabled hover:text-text-main transition-colors cursor-pointer">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        
                        <div class="p-5 max-h-[60vh] overflow-y-auto space-y-4 custom-scrollbar">
                            
                            <div class="bg-surface-muted border border-border rounded-lg p-4">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs font-bold text-text-secondary uppercase tracking-wider font-sans">Progress Team</span>
                                    <span class="text-xs font-bold font-mono text-text-main" x-text="teamDetail.data.completed_tasks + '/' + teamDetail.data.total_tasks + ' Selesai'"></span>
                                </div>
                                <div class="h-2.5 bg-slate-200 rounded-full overflow-hidden shadow-inner">
                                    <div class="h-full rounded-full transition-all duration-300"
                                         :style="`width: ${teamDetail.data.progress_percent}%; background: ${teamDetail.data.progress_percent === 100 ? 'var(--color-success)' : 'var(--color-primary)'}`">
                                    </div>
                                </div>
                            </div>

                            
                            <div>
                                <h4 class="text-[10px] font-bold text-text-muted uppercase tracking-widest mb-2.5 font-sans">Task dalam Team</h4>
                                <div class="border border-border rounded-lg bg-surface divide-y divide-border overflow-hidden shadow-xs">
                                    <template x-for="t in teamDetail.data.tasks" :key="t.fop_task_id">
                                        <a :href="t.task_id ? `<?php echo e(url('/tasks')); ?>/${t.task_id}` : '#'"
                                           class="block p-4 hover:bg-surface-muted transition-colors duration-150 cursor-pointer"
                                           :class="!t.task_id ? 'pointer-events-none opacity-60' : ''">
                                            
                                            <!-- Top Row -->
                                            <div class="flex items-start justify-between gap-2 mb-2.5">
                                                <!-- Left: Badge Kategori -->
                                                <span class="px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider border font-sans"
                                                      :class="t.badge_classes"
                                                      x-text="t.category_label">
                                                </span>
                                                <!-- Right: Task ID & Status -->
                                                <div class="flex items-center gap-1.5 shrink-0 font-sans">
                                                    <span class="text-[10px] font-mono text-text-muted font-semibold" x-text="t.task_number"></span>
                                                    <span class="text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full border font-sans"
                                                          :style="t.status_style"
                                                          x-text="t.status">
                                                    </span>
                                                </div>
                                            </div>

                                            <!-- Body: Customer Name -->
                                            <div class="mb-1">
                                                <h5 class="text-xs font-bold text-text-main font-sans" x-text="t.customer_name"></h5>
                                            </div>

                                            <!-- Body: Customer Address -->
                                            <div class="mb-3">
                                                <p class="text-[11px] text-text-muted font-sans leading-relaxed" x-text="t.customer_address"></p>
                                            </div>

                                            <!-- Footer: Technicians / PIC -->
                                            <div class="pt-3 border-t border-border/60 flex items-center justify-between">
                                                <div class="flex items-center gap-1 text-[10px] text-text-muted font-sans">
                                                    <svg class="h-3.5 w-3.5 text-text-disabled shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                                    </svg>
                                                    <span class="font-semibold text-text-secondary" x-text="t.technicians.join(', ') || 'Belum ada PIC'"></span>
                                                </div>
                                                <span class="text-[10px] font-bold text-primary hover:text-primary-hover font-sans inline-flex items-center gap-0.5">
                                                    <span>Detail Task</span>
                                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                                    </svg>
                                                </span>
                                            </div>
                                        </a>
                                    </template>
                                    <div x-show="teamDetail.data.tasks.length === 0" class="text-xs text-text-muted italic text-center py-6 font-sans">Belum ada task.</div>
                                </div>
                            </div>
                        </div>

                        
                        <div class="px-5 py-3.5 border-t border-border bg-surface-muted flex justify-end">
                            <a href="<?php echo e(route('fop-tasks.index')); ?>" class="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:text-primary-hover transition-colors cursor-pointer">
                                <span>Kelola di Task FOP</span>
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                </svg>
                            </a>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    
    <div x-show="switchTeamModal.open"
         x-effect="document.body.classList.toggle('overflow-hidden', switchTeamModal.open)"
         class="fixed inset-0 z-50 overflow-y-auto"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         style="display: none;">

        <div class="fixed inset-0 bg-slate-950/60 backdrop-blur-md" @click="switchTeamModal.open = false"></div>

        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-surface border border-border w-full max-w-md rounded-xl shadow-xl relative z-10 overflow-hidden"
                 @click.away="switchTeamModal.open = false">

                <div class="px-5 py-4 border-b border-border flex items-center justify-between bg-surface-muted">
                    <h3 class="text-sm font-bold text-text-main font-sans">Pindahkan Task ke Team Lain</h3>
                    <button type="button" @click="switchTeamModal.open = false" class="text-text-disabled hover:text-text-main transition-colors cursor-pointer">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="p-5 space-y-4 font-sans">
                    <p class="text-xs text-text-secondary leading-relaxed bg-primary-soft/50 p-3 rounded-lg border border-primary-border">
                        Pindahkan task <span class="font-bold text-text-main font-mono" x-text="switchTeamModal.tugas"></span> dari 
                        <span class="font-bold text-text-main" x-text="switchTeamModal.fromTeamName"></span> ke 
                        <span class="font-bold text-text-main" x-text="switchTeamModal.toTeamName"></span>.
                    </p>

                    <div class="relative" x-data="{ openTechDropdown: false }">
                        <label class="block text-[10px] font-bold text-text-muted uppercase tracking-widest mb-1.5 font-sans">
                            Teknisi Pengerjaan di Team Tujuan
                        </label>
                        <div @click="openTechDropdown = true" @click.away="openTechDropdown = false"
                             class="min-h-[38px] w-full border border-border rounded-lg bg-surface px-2 py-1.5 focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20 cursor-text flex items-center gap-1.5 flex-wrap transition-all duration-150">
                            <template x-for="techId in switchTeamModal.technicianIds" :key="techId">
                                <span class="inline-flex items-center gap-1 bg-primary-soft text-primary-hover border border-primary-border text-[11px] font-semibold px-2.5 py-0.5 rounded-full shadow-xs">
                                    <span x-text="switchTeamModal.toTeamMembers.find(m => m.id === techId)?.name"></span>
                                    <button type="button" @click.stop="toggleSwitchTeamTech(techId)" class="hover:text-error transition-colors cursor-pointer">
                                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </span>
                            </template>
                            <input type="text" x-model="switchTeamModal.searchTech" @focus="openTechDropdown = true"
                                   placeholder="Cari teknisi..." class="flex-1 min-w-[120px] outline-none text-xs bg-transparent border-none p-0 focus:ring-0 text-text-main placeholder-text-muted/60">
                        </div>

                        <div x-show="openTechDropdown" class="absolute z-50 w-full bg-surface border border-border rounded-lg shadow-lg mt-1 max-h-40 overflow-y-auto divide-y divide-border" style="display: none;">
                            <template x-for="member in switchTeamModal.toTeamMembers" :key="member.id">
                                <label class="flex items-center gap-2.5 px-3 py-2 bg-surface hover:bg-surface-muted cursor-pointer transition-colors duration-100"
                                       x-show="switchTeamModal.searchTech === '' || member.name.toLowerCase().includes(switchTeamModal.searchTech.toLowerCase())">
                                    <input type="checkbox"
                                           :checked="switchTeamModal.technicianIds.includes(member.id)"
                                           @change="toggleSwitchTeamTech(member.id)"
                                           class="w-4 h-4 rounded border-border text-primary focus:ring-primary/20 cursor-pointer">
                                    <span class="text-xs text-text-secondary font-medium" x-text="member.name"></span>
                                </label>
                            </template>
                            <p x-show="switchTeamModal.toTeamMembers.length === 0" class="px-3 py-2.5 text-xs text-text-disabled italic text-center font-sans">Team tujuan belum punya anggota.</p>
                        </div>
                        <p class="text-[11px] text-text-muted mt-1.5 font-sans leading-relaxed">Hanya menampilkan anggota Team tujuan untuk menghindari konflik penugasan. Teknisi lama akan otomatis digantikan.</p>
                    </div>
                </div>

                <div class="px-5 py-3.5 border-t border-border bg-surface-muted flex justify-end gap-2.5">
                    <button type="button" @click="switchTeamModal.open = false"
                            class="text-xs font-semibold px-3 py-2 rounded-lg border border-border text-text-secondary hover:bg-surface-muted hover:text-text-main transition-colors cursor-pointer shadow-xs">
                        Batal
                    </button>
                    <button type="button" @click="submitSwitchTeam()"
                            :disabled="switchTeamModal.technicianIds.length === 0 || switchTeamModal.isSubmitting"
                            class="text-xs font-semibold px-3 py-2 rounded-lg bg-primary text-white hover:bg-primary-hover transition-colors disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer shadow-sm">
                        <span x-show="!switchTeamModal.isSubmitting">Konfirmasi Pindah</span>
                        <span x-show="switchTeamModal.isSubmitting">Memproses...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    
    <div class="flex flex-col gap-3">
        <div class="flex items-center justify-between">
            <p class="text-[10px] font-bold uppercase tracking-widest text-text-muted font-sans">Antrean Survey</p>
            <span class="text-xs text-text-muted font-medium font-sans bg-slate-100 dark:bg-slate-700/50 border border-slate-200 dark:border-slate-700 px-2 py-0.5 rounded shadow-xs">Batas respon 1×24 jam sejak pendaftaran</span>
        </div>
        <div id="antrian-survey-container" class="bg-surface border border-border rounded-xl shadow-sm overflow-hidden">
            <?php if($surveyQueue->count() > 0): ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-border bg-surface-muted">
                            <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-text-muted font-sans w-2/5">Pelanggan</th>
                            <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-text-muted font-sans w-1/5">POP Cabang</th>
                            <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-text-muted font-sans w-1/5">Terdaftar</th>
                            <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-text-muted font-sans w-1/5">Sisa Waktu Survey</th>
                            <th class="px-4 py-3 w-[80px]"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <?php $__currentLoopData = $surveyQueue; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr class="hover:bg-surface-muted/50 transition-colors duration-150">
                            <td class="px-4 py-3">
                                <div class="flex flex-col gap-0.5">
                                    <p class="font-bold text-text-main text-xs font-sans"><?php echo e($item['name']); ?></p>
                                    <p class="text-[10px] font-mono text-text-muted font-semibold tracking-tight"><?php echo e($item['cid']); ?></p>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-text-secondary text-xs font-sans font-medium"><?php echo e($item['pop_name']); ?></td>
                            <td class="px-4 py-3 text-text-muted text-xs font-sans"><?php echo e($item['registered_at']); ?></td>
                            <td class="px-4 py-3">
                                
                                <?php if (isset($component)) { $__componentOriginalb8d3d89751f3d81017aa8a59bd985fb5 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb8d3d89751f3d81017aa8a59bd985fb5 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.countdown-timer','data' => ['deadline' => ''.e($item['deadline_iso']).'','totalSeconds' => $item['total_seconds'],'label' => 'Sisa Waktu']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('countdown-timer'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['deadline' => ''.e($item['deadline_iso']).'','total-seconds' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($item['total_seconds']),'label' => 'Sisa Waktu']); ?>
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
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="<?php echo e(route('customers.show', $item['id'])); ?>"
                                   class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-1.5 border border-border rounded-lg bg-surface hover:bg-surface-muted text-text-secondary hover:text-text-main shadow-xs transition-all duration-150 cursor-pointer">
                                    <span>Detail</span>
                                    <svg class="h-3.5 w-3.5 text-text-disabled" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                    </svg>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="flex flex-col items-center justify-center py-12 text-text-muted">
                <svg class="h-10 w-10 text-text-disabled mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <p class="text-sm font-medium font-sans">Tidak ada pelanggan dalam antrean survey.</p>
                <p class="text-xs text-text-muted mt-0.5 font-sans">Semua pendaftaran survey telah dijadwalkan.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    
    <div class="flex flex-col gap-3">
        <div class="flex items-center justify-between">
            <p class="text-[10px] font-bold uppercase tracking-widest text-text-muted font-sans">Status Teknisi</p>
        </div>
        <div id="status-teknisi-container" class="bg-surface border border-border rounded-xl shadow-sm overflow-hidden">
            <?php if($teknisiList->count() > 0): ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-border bg-surface-muted">
                            <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-text-muted font-sans w-2/5">Teknisi</th>
                            <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-text-muted font-sans w-1/5">Status</th>
                            <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-text-muted font-sans w-1/5">Task Aktif Hari Ini</th>
                            <th class="px-4 py-3 text-[10px] font-bold uppercase tracking-widest text-text-muted font-sans w-1/5">Lokasi Terakhir</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <?php $__currentLoopData = $teknisiList; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tek): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr class="hover:bg-surface-muted/50 transition-colors duration-150">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2.5">
                                    <div class="h-7 w-7 rounded-full bg-primary-soft text-primary border border-primary-border flex items-center justify-center text-[10px] font-bold shrink-0 font-sans shadow-xs">
                                        <?php echo e($tek['initials']); ?>

                                    </div>
                                    <span class="font-bold text-text-main text-xs font-sans"><?php echo e($tek['name']); ?></span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <?php if($tek['status'] === 'aktif'): ?>
                                    <span class="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full border bg-success-bg text-success border-success-border">
                                        <span class="h-1.5 w-1.5 rounded-full bg-current animate-pulse"></span>
                                        <span>Aktif</span>
                                    </span>
                                <?php elseif($tek['status'] === 'terjadwal'): ?>
                                    <span class="inline-flex items-center gap-1 text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full border bg-info-bg text-info border-info-border">
                                        <span>Terjadwal</span>
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 text-[10px] font-semibold tracking-wider px-2 py-0.5 rounded-full border border-border bg-slate-50 dark:bg-slate-800/50 text-text-muted font-sans">
                                        <span>Standby</span>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-xs font-semibold font-mono text-text-secondary">
                                <?php echo e($tek['task_count']); ?> Task
                            </td>
                            <td class="px-4 py-3 text-text-secondary text-xs font-sans">
                                <?php if($tek['location'] && $tek['location'] !== '-'): ?>
                                    <div class="flex items-center gap-1.5">
                                        <svg class="h-3.5 w-3.5 text-text-disabled shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        <span class="truncate max-w-[280px]" title="<?php echo e($tek['location']); ?>"><?php echo e($tek['location']); ?></span>
                                    </div>
                                <?php else: ?>
                                    <span class="text-text-disabled italic font-light font-sans">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="flex flex-col items-center justify-center py-12 text-text-muted border-t border-border">
                <svg class="h-10 w-10 text-text-disabled mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <p class="text-sm font-medium font-sans">Tidak ada teknisi terdaftar di POP wilayah Anda.</p>
                <p class="text-xs text-text-muted mt-0.5 font-sans">Daftarkan teknisi baru atau sesuaikan wilayah POP pada Manajemen User.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php $__env->startPush('scripts'); ?>
<script>
function fopDashboardHandler() {
    return {
        teamsData: <?php echo json_encode($activeFopTeams, 15, 512) ?>,
        teamDetail: { open: false, data: null },
        dragOverTeamId: null,
        dragging: null,
        switchTeamModal: {
            open: false,
            fopTaskId: null,
            fromTeamId: null,
            fromTeamName: '',
            toTeamId: null,
            toTeamName: '',
            toTeamMembers: [],
            tugas: '',
            technicianIds: [],
            searchTech: '',
            isSubmitting: false,
        },

        openTeamDetail(id) {
            this.teamDetail.data = this.teamsData.find(t => t.id === id) || null;
            this.teamDetail.open = true;
        },

        // ── Drag & Drop: Switch Task antar Team ─────────────────────
        startTaskDrag(event, fopTaskId, fromTeamId, tugas) {
            this.dragging = { fopTaskId, fromTeamId, tugas };
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', String(fopTaskId));
        },

        endTaskDrag() {
            this.dragging = null;
            this.dragOverTeamId = null;
        },

        onTeamDragOver(teamId) {
            if (!this.dragging || this.dragging.fromTeamId === teamId) return;
            this.dragOverTeamId = teamId;
        },

        onTeamDragLeave(teamId) {
            if (this.dragOverTeamId === teamId) this.dragOverTeamId = null;
        },

        onTeamDrop(toTeamId) {
            this.dragOverTeamId = null;
            if (!this.dragging || this.dragging.fromTeamId === toTeamId) {
                this.dragging = null;
                return;
            }

            const fromTeam = this.teamsData.find(t => t.id === this.dragging.fromTeamId);
            const toTeam = this.teamsData.find(t => t.id === toTeamId);
            if (!toTeam) {
                this.dragging = null;
                return;
            }

            this.switchTeamModal = {
                open: true,
                fopTaskId: this.dragging.fopTaskId,
                fromTeamId: this.dragging.fromTeamId,
                fromTeamName: fromTeam ? fromTeam.name : '',
                toTeamId: toTeam.id,
                toTeamName: toTeam.name,
                toTeamMembers: toTeam.members,
                tugas: this.dragging.tugas,
                technicianIds: [],
                searchTech: '',
                isSubmitting: false,
            };
            this.dragging = null;
        },

        toggleSwitchTeamTech(id) {
            const index = this.switchTeamModal.technicianIds.indexOf(id);
            if (index > -1) {
                this.switchTeamModal.technicianIds.splice(index, 1);
            } else {
                this.switchTeamModal.technicianIds.push(id);
                this.switchTeamModal.searchTech = '';
            }
        },

        submitSwitchTeam() {
            if (this.switchTeamModal.technicianIds.length === 0) return;
            this.switchTeamModal.isSubmitting = true;
            const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            fetch(`<?php echo e(url('/fop-tasks')); ?>/${this.switchTeamModal.fopTaskId}/switch-team`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token
                },
                body: JSON.stringify({
                    to_team_id: this.switchTeamModal.toTeamId,
                    technician_ids: this.switchTeamModal.technicianIds,
                })
            })
            .then(res => res.json())
            .then(data => {
                this.switchTeamModal.isSubmitting = false;
                if (data.success) {
                    this.showToast('success', data.message);
                    this.switchTeamModal.open = false;
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    this.showToast('error', data.message || 'Gagal memindahkan task.');
                }
            })
            .catch(() => {
                this.switchTeamModal.isSubmitting = false;
                this.showToast('error', 'Terjadi kesalahan jaringan.');
            });
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

        init() {
            this.initEchoListeners();
        },

        initEchoListeners() {
            const popIds = <?php echo json_encode($pops->pluck('id'), 15, 512) ?>;
            let attempts = 0;
            const setup = () => {
                if (typeof window.Echo === 'undefined' || !window.Echo) {
                    attempts++;
                    if (attempts < 20) setTimeout(setup, 100);
                    return;
                }
                popIds.forEach(popId => {
                    window.Echo.private(`fop.${popId}`)
                        .listen('TaskStarted', (e) => {
                            this.refreshTaskStats();
                        })
                        .listen('TaskCompleted', (e) => {
                            this.refreshTaskStats();
                        })
                        .listen('SurveyStarted',          () => this.refreshDashboardContainers())
                        .listen('SurveyCompleted',        () => this.refreshDashboardContainers())
                        .listen('InstallationStarted',    () => this.refreshDashboardContainers())
                        .listen('InstallationCompleted',  () => this.refreshDashboardContainers());
                });
            };
            setup();
        },

        async refreshTaskStats() {
            try {
                const res = await fetch(window.location.href);
                const html = await res.text();
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                
                ['stat-cards-container', 'status-teknisi-container'].forEach(id => {
                    const el = document.getElementById(id);
                    const newEl = doc.getElementById(id);
                    if (el && newEl) el.innerHTML = newEl.innerHTML;
                });
            } catch (e) {
                console.error('Auto-refresh error:', e);
            }
        },

        async refreshDashboardContainers() {
            try {
                const res = await fetch(window.location.href);
                const html = await res.text();
                const doc = new DOMParser().parseFromString(html, 'text/html');

                ['stat-cards-container', 'antrian-survey-container', 'status-teknisi-container'].forEach(id => {
                    const el = document.getElementById(id);
                    const newEl = doc.getElementById(id);
                    if (el && newEl) el.innerHTML = newEl.innerHTML;
                });
            } catch (e) {
                console.error('Auto-refresh error:', e);
            }
        },

    };
}
</script>
<?php $__env->stopPush(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/fop/dashboard.blade.php ENDPATH**/ ?>