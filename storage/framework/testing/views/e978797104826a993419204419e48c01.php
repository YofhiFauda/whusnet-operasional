<?php $__env->startSection('title', $task->task_number . ' — Task Detail'); ?>

<?php $__env->startSection('content'); ?>
<div class="max-w-[1180px] mx-auto space-y-4 select-none sm:select-text">
<!-- <div class="max-w-[1180px] mx-auto px-4 sm:px-6 py-4 space-y-4 select-none sm:select-text"> -->

    
    <nav class="flex items-center gap-1.5 text-xs text-text-muted">
        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewAll', \App\Models\Task::class)): ?>
        <a href="<?php echo e(auth()->user()->hasPermission('task.view.own') ? route('tasks.own') : route('fop.dashboard')); ?>" class="hover:text-primary transition-colors font-ui">Task</a>
        <?php else: ?>
        <a href="<?php echo e(route('tasks.own')); ?>" class="hover:text-primary transition-colors font-ui">Task Saya</a>
        <?php endif; ?>
        <svg class="h-3 w-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
        </svg>
        <span class="font-mono text-text-main"><?php echo e($task->task_number); ?></span>
    </nav>

    
    <div class="flex flex-col gap-3.5 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap mb-1.5">
                <a href="<?php echo e(route('tasks.own')); ?>"
                   class="h-10 w-10 flex items-center justify-center rounded-md border border-border bg-surface hover:bg-surface-muted text-text-secondary hover:text-text-main transition-all active:scale-95 shadow-xs cursor-pointer"
                   title="Kembali ke Task Saya">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                    </svg>
                </a>
                
                <?php
                    $statusStyle = match($task->status->value) {
                        'terjadwal'  => 'background:var(--color-info-bg); color:var(--color-info); border-color:var(--color-info-border)',
                        'in_progress'=> 'background:var(--color-warning-bg); color:var(--color-warning); border-color:var(--color-warning-border)',
                        'selesai'    => 'background:var(--color-success-bg); color:var(--color-success); border-color:var(--color-success-border)',
                        'dibatalkan' => 'background:var(--color-error-bg); color:var(--color-error); border-color:var(--color-error-border)',
                        default      => 'background:var(--color-surface-muted); color:var(--color-text-muted); border-color:var(--color-border)',
                    };
                ?>
                <span class="text-[10px] font-bold uppercase tracking-wider px-2.5 py-0.5 rounded-md border font-ui" style="<?php echo e($statusStyle); ?>">
                    <?php echo e($task->status->label()); ?>

                </span>
                <span class="text-[10px] font-bold uppercase tracking-wider px-2.5 py-0.5 rounded-md border font-ui <?php echo e($task->task_type->cardClasses()); ?>">
                    <?php echo e($task->task_type->label()); ?>

                </span>
                <span class="font-mono text-xs text-text-muted font-semibold shrink-0"><?php echo e($task->task_number); ?></span>
                <?php if($task->isOverSla()): ?>
                <span class="text-[10px] font-bold uppercase tracking-wider px-2.5 py-0.5 rounded-md border font-ui"
                      style="background:var(--color-error-bg); color:var(--color-error); border-color:var(--color-error-border)">
                    Melewati SLA
                </span>
                <?php endif; ?>
            </div>
            <h1 class="text-base sm:text-xl font-bold text-text-main font-ui tracking-tight leading-snug"><?php echo e($task->title); ?></h1>
        </div>

        <?php
            $lat = $task->customer?->customerAddress?->latitude ?? $task->pop?->latitude;
            $lng = $task->customer?->customerAddress?->longitude ?? $task->pop?->longitude;
        ?>
        
        <div class="grid grid-cols-2 sm:flex sm:items-center gap-2 shrink-0 w-full sm:w-auto">
            <?php if($lat && $lng): ?>
            <a href="https://www.google.com/maps/search/?api=1&query=<?php echo e($lat); ?>,<?php echo e($lng); ?>" target="_blank"
               class="inline-flex items-center justify-center gap-1.5 text-xs font-bold px-3 py-2.5 border border-border rounded-xl bg-surface hover:bg-surface-muted text-primary hover:text-primary-hover transition-all shadow-sm font-ui cursor-pointer active:scale-[0.98]">
                <svg class="h-4.5 w-4.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <span>Lokasi Maps</span>
            </a>
            <?php endif; ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('edit', $task)): ?>
            <a href="<?php echo e(route('tasks.edit', $task)); ?>"
               class="inline-flex items-center justify-center gap-1.5 text-xs font-bold px-3 py-2.5 border border-border rounded-xl bg-surface hover:bg-surface-muted text-text-secondary hover:text-text-main transition-all shadow-sm font-ui cursor-pointer active:scale-[0.98]">
                <svg class="h-4.5 w-4.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                <span>Edit</span>
            </a>
            <?php endif; ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('cancel', $task)): ?>
            <button x-data @click="$dispatch('open-modal', 'cancel-task')"
                    class="inline-flex items-center justify-center gap-1.5 text-xs font-bold px-3 py-2.5 border rounded-xl transition-all shadow-sm font-ui cursor-pointer active:scale-[0.98] col-span-2 sm:col-span-1"
                    style="border-color:var(--color-error-border); color:var(--color-error); background:var(--color-error-bg)">
                <span>Batalkan Task</span>
            </button>
            <?php endif; ?>
        </div>
    </div>

    
    <?php
        $topActualMin = $task->actualDurationMinutes();
        $topDuration = $topActualMin !== null
            ? (intdiv($topActualMin, 60) > 0
                ? intdiv($topActualMin, 60).'j '.($topActualMin % 60).'m'
                : $topActualMin.'m')
            : null;
    ?>
    <div class="bg-surface border border-border rounded-2xl overflow-hidden shadow-xs">
        
        
        <div class="grid grid-cols-2 sm:grid-cols-5 divide-y sm:divide-y-0 sm:divide-x divide-border border-b border-border bg-slate-50/50 dark:bg-slate-800/10">
            
            <div class="p-4 flex flex-col justify-between min-w-0">
                <span class="text-[9px] font-bold uppercase tracking-wider text-text-muted font-ui select-none">Tipe Task</span>
                <span class="text-xs font-bold text-text-main mt-1.5 font-ui truncate"><?php echo e($task->task_type->label()); ?></span>
            </div>
            
            <div class="p-4 flex flex-col justify-between min-w-0">
                <span class="text-[9px] font-bold uppercase tracking-wider text-text-muted font-ui select-none">Jadwal</span>
                <div class="mt-1.5">
                    <span class="text-xs font-bold font-mono text-text-main block leading-tight"><?php echo e($task->scheduled_at?->format('H:i') ?? '—'); ?></span>
                    <span class="text-[9px] text-text-muted font-ui block mt-0.5 leading-none"><?php echo e($task->scheduled_at?->translatedFormat('d M Y') ?? 'Belum dijadwalkan'); ?></span>
                </div>
            </div>
            
            <div class="p-4 flex flex-col justify-between min-w-0">
                <span class="text-[9px] font-bold uppercase tracking-wider text-text-muted font-ui select-none">Target SLA</span>
                <div class="mt-1.5">
                    <span class="text-xs font-bold font-mono block leading-tight <?php echo e($task->isOverSla() ? 'text-rose-500' : 'text-text-main'); ?>">
                        <?php echo e($task->sla_minutes); ?> Menit
                    </span>
                    <?php if($topActualMin !== null): ?>
                    <span class="text-[9px] text-text-muted font-mono block mt-0.5 leading-none">Aktual: <?php echo e($topActualMin); ?> Mnt</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="p-4 flex flex-col justify-between min-w-0">
                <span class="text-[9px] font-bold uppercase tracking-wider text-text-muted font-ui select-none">POP / Cabang</span>
                <span class="text-xs font-bold text-text-main mt-1.5 font-ui truncate" title="<?php echo e($task->pop?->name ?? '—'); ?>"><?php echo e($task->pop?->name ?? '—'); ?></span>
            </div>
            
            <div class="p-4 flex flex-col justify-between col-span-2 sm:col-span-1 min-w-0">
                <span class="text-[9px] font-bold uppercase tracking-wider text-text-muted font-ui select-none">Durasi Aktual</span>
                <div class="mt-1.5">
                    <span class="text-xs font-bold font-mono block leading-tight <?php echo e($task->isOverSla() ? 'text-rose-500' : 'text-text-main'); ?>">
                        <?php echo e($topDuration ?? '—'); ?>

                    </span>
                    <span class="text-[9px] text-text-muted font-ui block mt-0.5 leading-none"><?php echo e($task->started_at ? 'Berjalan' : 'Belum dimulai'); ?></span>
                </div>
            </div>
        </div>

        
        <div class="grid grid-cols-1 md:grid-cols-12 divide-y md:divide-y-0 md:divide-x divide-border">
            
            
            <div class="md:col-span-7 p-4 sm:p-5 space-y-4">
                <div class="flex items-center gap-2 mb-1 select-none">
                    <svg class="h-4.5 w-4.5 text-sky-600 dark:text-sky-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-text-muted font-ui">Informasi Pekerjaan</h3>
                </div>
                
                <div class="space-y-0.5 text-xs">
                    
                    <div class="flex flex-col sm:flex-row sm:items-start py-2.5 border-b border-border gap-1 sm:gap-4">
                        <span class="text-text-muted sm:w-36 shrink-0 font-ui font-medium flex items-center gap-1.5 select-none">
                            <svg class="h-3.5 w-3.5 text-text-disabled shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            FOP Koordinator
                        </span>
                        <span class="text-text-main font-semibold flex-1 font-ui"><?php echo e($task->fop?->name ?? '—'); ?></span>
                    </div>

                    
                    <div class="flex flex-col sm:flex-row sm:items-start py-2.5 border-b border-border gap-1 sm:gap-4">
                        <span class="text-text-muted sm:w-36 shrink-0 font-ui font-medium flex items-center gap-1.5 select-none">
                            <svg class="h-3.5 w-3.5 text-text-disabled shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            Pelanggan
                        </span>
                        <div class="text-text-main flex-1 font-ui">
                            <?php if($task->customer): ?>
                            <div>
                                <a href="<?php echo e(route('customers.show', $task->customer)); ?>" class="hover:underline font-bold text-sky-600 dark:text-sky-400">
                                    <?php echo e($task->customer->full_name); ?>

                                </a>
                                <span class="font-mono text-xs text-text-muted ml-1 bg-surface-muted dark:bg-slate-800 px-1.5 py-0.5 rounded border border-border font-semibold"><?php echo e($task->customer->display_id); ?></span>
                            </div>
                            <?php if($task->customer->primary_phone): ?>
                            <div class="flex items-center gap-1.5 mt-1 text-[11px] select-text">
                                <span class="text-text-muted font-mono font-medium"><?php echo e($task->customer->primary_phone); ?></span>
                                <a href="https://wa.me/<?php echo e(preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $task->customer->primary_phone))); ?>" target="_blank"
                                   class="text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 dark:hover:text-emerald-300 inline-flex items-center gap-1 font-bold cursor-pointer bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/50 px-2 py-0.5 rounded-lg transition-colors select-none">
                                    <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.768-.001 1.298.409 2.522 1.189 3.518l-.756 2.766 2.831-.744a5.748 5.748 0 002.504.588h.002c3.18 0 5.767-2.586 5.768-5.766 0-1.541-.6-2.99-1.691-4.08-1.091-1.09-2.539-1.69-4.079-1.648zm0 10.153a4.398 4.398 0 01-2.241-.614l-.16-.095-1.666.438.444-1.624-.105-.167a4.394 4.394 0 01-.67-2.326c.001-2.426 1.975-4.4 4.402-4.4 1.177 0 2.283.458 3.115 1.29a4.382 4.382 0 011.29 3.117c-.001 2.426-1.975-4.4-4.409 4.4z"/></svg>
                                    WhatsApp
                                </a>
                            </div>
                            <?php endif; ?>
                            <?php else: ?>
                            <span class="text-text-muted">—</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    
                    <?php if($task->description): ?>
                    <div class="flex flex-col sm:flex-row sm:items-start py-3 border-b border-border gap-1.5 sm:gap-4 select-text">
                        <span class="text-amber-700 dark:text-amber-400 sm:w-36 shrink-0 font-bold font-ui flex items-center gap-1.5 select-none">
                            <svg class="h-3.5 w-3.5 text-amber-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            Issue / Keluhan
                        </span>
                        <span class="text-text-main font-semibold leading-relaxed bg-amber-50/70 dark:bg-amber-900/10 border border-amber-200/80 dark:border-amber-800/40 rounded-xl p-3 flex-1 font-ui shadow-xs"><?php echo e($task->description); ?></span>
                    </div>
                    <?php endif; ?>

                    
                    <?php if($task->fopTask?->ticket?->catatan_teknis): ?>
                    <div class="flex flex-col sm:flex-row sm:items-start py-3 border-b border-border gap-1.5 sm:gap-4 select-text">
                        <span class="text-sky-700 dark:text-sky-400 sm:w-36 shrink-0 font-bold font-ui flex items-center gap-1.5 select-none">
                            <svg class="h-3.5 w-3.5 text-sky-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            Catatan Teknis (NOC)
                        </span>
                        <span class="text-text-main font-semibold leading-relaxed bg-sky-50/70 dark:bg-sky-900/10 border border-sky-200/80 dark:border-sky-800/40 rounded-xl p-3 flex-1 font-ui whitespace-pre-line shadow-xs"><?php echo e($task->fopTask->ticket->catatan_teknis); ?></span>
                    </div>
                    <?php endif; ?>

                    
                    <?php if($task->fopTask?->notes): ?>
                    <div class="flex flex-col sm:flex-row sm:items-start py-3 border-b border-border gap-1.5 sm:gap-4 select-text">
                        <span class="text-text-secondary sm:w-36 shrink-0 font-semibold font-ui flex items-center gap-1.5 select-none">
                            <svg class="h-3.5 w-3.5 text-text-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2" />
                            </svg>
                            Catatan FOP
                        </span>
                        <span class="text-text-main leading-relaxed bg-surface-muted border border-border rounded-xl p-3 flex-1 font-ui whitespace-pre-line shadow-xs"><?php echo e($task->fopTask->notes); ?></span>
                    </div>
                    <?php endif; ?>

                    
                    <?php if($task->customer || $task->pop): ?>
                    <div class="flex flex-col sm:flex-row sm:items-start py-2.5 gap-1 sm:gap-4 select-text">
                        <span class="text-text-muted sm:w-36 shrink-0 font-ui font-medium flex items-center gap-1.5 select-none">
                            <svg class="h-3.5 w-3.5 text-text-disabled shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            </svg>
                            Lokasi &amp; Peta
                        </span>
                        <div class="text-text-secondary leading-relaxed flex-1 font-ui">
                            <div class="font-semibold text-text-main">
                                <?php if($task->customer): ?>
                                    <?php echo e($task->customer->clean_address); ?>

                                <?php else: ?>
                                    <?php echo e($task->pop?->address ?? '—'); ?> (<?php echo e($task->pop?->name); ?>)
                                <?php endif; ?>
                            </div>
                            <?php if($lat && $lng): ?>
                            <div class="flex items-center gap-2 mt-2 pt-2 border-t border-border/60 text-[11px] flex-wrap select-none">
                                <span class="font-mono text-text-main bg-surface-muted px-2 py-0.5 rounded border border-border">Lat: <strong class="text-sky-600 dark:text-sky-400"><?php echo e($lat); ?></strong> | Lng: <strong class="text-sky-600 dark:text-sky-400"><?php echo e($lng); ?></strong></span>
                                <a href="https://www.google.com/maps/search/?api=1&query=<?php echo e($lat); ?>,<?php echo e($lng); ?>" target="_blank"
                                   class="inline-flex items-center gap-1 font-bold text-sky-600 dark:text-sky-400 hover:underline cursor-pointer">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                    </svg>
                                    Maps →
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    
                    <?php if($task->pending_reason): ?>
                    <div class="flex flex-col sm:flex-row sm:items-start py-2.5 border-t border-border gap-1 sm:gap-4">
                        <span class="text-text-muted sm:w-36 shrink-0 font-ui font-medium flex items-center gap-1.5 select-none">
                            <svg class="h-3.5 w-3.5 text-text-disabled shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            Alasan Pending
                        </span>
                        <span class="font-bold flex-1 text-warning font-ui"><?php echo e($task->pending_reason); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            
            <div class="md:col-span-5 p-4 sm:p-5 space-y-5 bg-slate-50/15 dark:bg-slate-800/5">
                
                
                <?php if($task->status->value === 'selesai' && $task->started_at && $task->completed_at): ?>
                <?php
                    $showStartedAt   = $task->started_at;
                    $showCompletedAt = $task->completed_at;
                    $showActualMin   = (int) $showStartedAt->diffInMinutes($showCompletedAt);
                    $showHours       = intdiv($showActualMin, 60);
                    $showRemMins     = $showActualMin % 60;
                    $showDuration    = $showHours > 0 ? "{$showHours}j {$showRemMins}m" : "{$showActualMin}m";
                    $showOverSla     = $showActualMin > $task->sla_minutes;
                    $showTypeLabel   = $task->task_type->value === \App\Enums\TaskType::PEMASANGAN->value ? 'Pemasangan' : 'Survey';
                ?>
                <div class="space-y-2">
                    <div class="flex items-center justify-between mb-1 select-none">
                        <div class="flex items-center gap-1.5">
                            <svg class="h-4.5 w-4.5 text-sky-600 dark:text-sky-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <p class="text-[10px] font-bold uppercase tracking-wider text-text-muted font-ui">Durasi Kerja <?php echo e($showTypeLabel); ?></p>
                        </div>
                        <span class="text-[9px] font-bold px-2 py-0.5 rounded-full border font-mono"
                              style="<?php echo e($showOverSla
                                  ? 'background:var(--color-error-bg); color:var(--color-error); border-color:var(--color-error-border)'
                                  : 'background:var(--color-success-bg); color:var(--color-success); border-color:var(--color-success-border)'); ?>">
                            <?php echo e($showOverSla ? 'Over SLA' : 'Dalam SLA'); ?>

                        </span>
                    </div>
                    <div class="bg-surface border border-border rounded-xl p-3 space-y-2 text-xs">
                        <div class="flex items-center justify-between font-ui">
                            <div>
                                <p class="text-[9px] font-bold uppercase tracking-wider text-text-muted mb-0.5 select-none">Mulai</p>
                                <p class="font-mono font-bold text-text-main text-xs"><?php echo e($showStartedAt->format('H:i')); ?></p>
                                <p class="text-[9px] text-text-muted select-none"><?php echo e($showStartedAt->translatedFormat('d M Y')); ?></p>
                            </div>
                            <svg class="h-4 w-4 text-slate-400 shrink-0 select-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 8.25L21 12m0 0l-3.75 3.75M21 12H3" />
                            </svg>
                            <div class="text-right">
                                <p class="text-[9px] font-bold uppercase tracking-wider text-text-muted mb-0.5 select-none">Selesai</p>
                                <p class="font-mono font-bold text-text-main text-xs"><?php echo e($showCompletedAt->format('H:i')); ?></p>
                                <p class="text-[9px] text-text-muted select-none"><?php echo e($showCompletedAt->translatedFormat('d M Y')); ?></p>
                            </div>
                        </div>
                        <div class="pt-2 border-t border-border flex items-center justify-between text-xs select-none">
                            <div>
                                <p class="text-[9px] font-bold uppercase tracking-wider text-text-muted font-ui">Durasi Aktual</p>
                                <p class="font-mono font-bold" style="color:<?php echo e($showOverSla ? 'var(--color-error)' : 'var(--color-success)'); ?>">
                                    <?php echo e($showDuration); ?>

                                </p>
                            </div>
                            <div class="text-right">
                                <p class="text-[9px] font-bold uppercase tracking-wider text-text-muted font-ui">Target SLA</p>
                                <p class="font-mono font-bold text-text-secondary"><?php echo e($task->sla_minutes); ?> menit</p>
                            </div>
                        </div>
                        <?php if($task->completedBy): ?>
                        <div class="pt-2 border-t border-border flex items-center gap-1.5 select-none">
                            <svg class="h-3.5 w-3.5 text-text-disabled shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <p class="text-[10px] text-text-muted font-ui">Dilaporkan oleh: <span class="font-bold text-text-main"><?php echo e($task->completedBy->name); ?></span></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                
                <div class="space-y-2">
                    <div class="flex items-center gap-1.5 mb-1 select-none">
                        <svg class="h-4.5 w-4.5 text-sky-600 dark:text-sky-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <p class="text-[10px] font-bold uppercase tracking-wider text-text-muted font-ui">Tim Anggota Lapangan</p>
                    </div>
                    <?php if($task->teamMembers->count() > 0): ?>
                    <div class="grid grid-cols-1 gap-2">
                        <?php $__currentLoopData = $task->teamMembers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $member): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="flex items-center gap-2.5 bg-surface-muted border border-border rounded-xl p-2.5 shadow-xs transition-colors hover:border-sky-300 dark:hover:border-sky-800">
                            <div class="h-7 w-7 rounded-full bg-sky-100 dark:bg-sky-950 text-sky-700 dark:text-sky-300 flex items-center justify-center text-xs font-bold shrink-0 border border-sky-200 dark:border-sky-900/60 select-none">
                                <?php echo e(strtoupper(substr($member->user?->name ?? '?', 0, 2))); ?>

                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-bold text-text-main truncate font-ui leading-tight"><?php echo e($member->user?->name ?? 'User dihapus'); ?></p>
                                <p class="text-[10px] text-text-muted capitalize font-ui mt-0.5 leading-none select-none"><?php echo e($member->role_in_task); ?></p>
                            </div>
                        </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                    <?php else: ?>
                    <p class="text-xs text-text-muted font-ui select-none">Belum ada anggota tim.</p>
                    <?php endif; ?>
                </div>

                
                <?php if(auth()->user()->hasRole(['owner', 'admin', 'fop']) || $task->isMember(auth()->id())): ?>
                <div class="pt-4 border-t border-border">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-text-muted mb-3 font-ui select-none">Riwayat Perubahan Status</p>
                    
                    <?php if($statusTimeline->isNotEmpty()): ?>
                    <div class="relative border-l border-border ml-2.5 space-y-3.5">
                        <?php $__currentLoopData = $statusTimeline; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $log): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="relative pl-4 select-text">
                            
                            <div class="absolute -left-1 top-1.5 h-2.5 w-2.5 rounded-full bg-border border-2 border-surface shrink-0 select-none"></div>
                            <div class="mb-0.5 flex items-center justify-between gap-2">
                                <p class="text-xs font-bold text-text-main font-ui leading-tight">
                                    <?php echo e(\App\Support\TaskAuditTimeline::label($log)); ?>

                                </p>
                                <span class="text-[9px] text-text-muted font-mono shrink-0 font-semibold select-none"><?php echo e($log->created_at->format('d M, H:i')); ?></span>
                            </div>
                            <p class="text-[10px] text-text-muted font-ui select-none">Oleh: <span class="font-bold text-text-secondary"><?php echo e($log->user?->name ?? 'System'); ?></span></p>
                            
                            <?php if($log->action === 'cancelled' && isset($log->new_values['cancel_reason'])): ?>
                            <div class="mt-1 p-1.5 bg-error-bg/30 border border-error-border rounded-lg max-w-full overflow-hidden">
                                <p class="text-[10px] text-error font-semibold font-ui break-words">Alasan: <?php echo e($log->new_values['cancel_reason']); ?></p>
                            </div>
                            <?php elseif($log->action === 'rejected' && isset($log->new_values['reject_reason'])): ?>
                            <div class="mt-1 p-1.5 bg-error-bg/30 border border-error-border rounded-lg max-w-full overflow-hidden">
                                <p class="text-[10px] text-error font-semibold font-ui break-words">Alasan: <?php echo e($log->new_values['reject_reason']); ?></p>
                            </div>
                            
                            <?php elseif(in_array($log->action, ['pending', 'report_deferred'], true) && isset($log->new_values['pending_reason'])): ?>
                            <div class="mt-1 p-1.5 bg-warning-bg/30 border border-warning-border rounded-lg max-w-full overflow-hidden">
                                <p class="text-[10px] text-warning font-semibold font-ui break-words">Alasan: <?php echo e($log->new_values['pending_reason']); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                    <?php else: ?>
                    <p class="text-xs text-text-muted font-ui select-none">Belum ada riwayat aktivitas.</p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

            </div>
        </div>

        
        <div class="p-4 sm:p-5 border-t border-border space-y-6">
            
            
            <div>
                <div class="flex items-center gap-2 mb-3.5 pb-2 border-b border-border select-none">
                    <svg class="h-4.5 w-4.5 text-sky-600 dark:text-sky-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    </svg>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-text-main font-ui">Briefing &amp; Detail Teknis</h3>
                </div>
                
                <?php if($task->task_type === \App\Enums\TaskType::SURVEY): ?>
                <div class="space-y-0.5 text-xs select-text">
                    <div class="flex flex-col sm:flex-row sm:items-start py-2 border-b border-border gap-1 sm:gap-4">
                        <span class="text-text-muted sm:w-36 shrink-0 font-ui font-medium select-none">Status SLA Berjalan</span>
                        <div class="text-text-main font-semibold flex-1 font-ui">
                            <?php if($task->started_at && !$task->completed_at): ?>
                                <?php
                                    $elapsed = (int) $task->started_at->diffInMinutes(now());
                                    $remaining = $task->sla_minutes - $elapsed;
                                ?>
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-bold font-mono <?php echo e($remaining < 0 ? 'text-error' : 'text-primary'); ?>">
                                        <?php echo e($elapsed); ?> menit berjalan
                                    </span>
                                    <span class="text-[10px] text-text-muted font-mono select-none">(<?php echo e($remaining >= 0 ? "Sisa {$remaining} mnt" : "Over SLA " . abs($remaining) . " mnt"); ?>)</span>
                                </div>
                            <?php elseif($task->status->value === 'terjadwal'): ?>
                                <span class="text-warning font-semibold">Menunggu teknisi klik Mulai Survey (Target SLA: <?php echo e($task->sla_minutes); ?> menit)</span>
                            <?php elseif($task->completed_at): ?>
                                <span class="text-success font-semibold">Selesai dikerjakan dalam <?php echo e($task->actualDurationMinutes()); ?> menit</span>
                            <?php else: ?>
                                <span class="text-text-muted"><?php echo e($task->status->label()); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="flex flex-col sm:flex-row sm:items-start py-2 border-b border-border gap-1 sm:gap-4">
                        <span class="text-text-muted sm:w-36 shrink-0 font-ui font-medium select-none">Rencana Paket</span>
                        <span class="text-text-main font-bold flex-1 font-ui">
                            <?php echo e($task->customer?->customerService?->internetPackage?->name ?? $task->customer?->customerService?->package_name_snapshot ?? 'Belum dipilih saat pendaftaran'); ?>

                        </span>
                    </div>
                </div>
                
                <?php elseif($task->task_type === \App\Enums\TaskType::PEMASANGAN): ?>
                <?php
                    $survey = $task->customer?->latestSurvey;
                    $service = $task->customer?->customerService;
                    $device = $task->customer?->customerDevice;
                ?>
                <div class="space-y-4 select-text">
                    <div>
                        <span class="block text-[9px] font-bold text-text-muted uppercase mb-2 font-ui tracking-wider select-none">Hasil Survey Lapangan</span>
                        <?php if($survey): ?>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                            <div class="bg-surface-muted border border-border rounded-xl p-3 shadow-xs">
                                <span class="block text-[9px] text-text-muted font-bold uppercase font-ui select-none">ODP Tujuan &amp; Port</span>
                                <span class="font-bold font-mono text-text-main text-xs mt-1 block"><?php echo e($survey->nearest_odp ?: '-'); ?></span>
                            </div>
                            <div class="bg-surface-muted border border-border rounded-xl p-3 shadow-xs">
                                <span class="block text-[9px] text-text-muted font-bold uppercase font-ui select-none">Estimasi Dropcore</span>
                                <span class="font-bold font-mono text-text-main text-xs mt-1 block"><?php echo e($survey->cable_estimation_meter ? $survey->cable_estimation_meter . ' Meter' : '-'); ?></span>
                            </div>
                            <div class="bg-surface-muted border border-border rounded-xl p-3 shadow-xs">
                                <span class="block text-[9px] text-text-muted font-bold uppercase font-ui select-none">Kebutuhan Alat</span>
                                <span class="font-bold text-text-main text-xs mt-1 block font-ui"><?php echo e($survey->required_tools ?: 'Standar'); ?></span>
                            </div>
                        </div>
                        <?php if($survey->requested_installation_date): ?>
                        <p class="text-[11px] mt-2.5 font-ui font-bold text-sky-600 dark:text-sky-400">
                            Permintaan instalasi pelanggan: <?php echo e(\App\Support\IndonesianDate::date($survey->requested_installation_date)); ?>

                        </p>
                        <?php endif; ?>
                        <?php if($survey->survey_note): ?>
                        <p class="text-[11px] text-text-secondary mt-1.5 italic font-ui">"<?php echo e($survey->survey_note); ?>"</p>
                        <?php endif; ?>
                        <?php else: ?>
                        <p class="text-[11px] text-warning font-semibold font-ui select-none">Data hasil survey sebelumnya belum tercatat di sistem.</p>
                        <?php endif; ?>
                    </div>

                    <?php
                        $estimasiMaterial = $task->customer
                            ? \App\Models\TaskMaterial::where('customer_id', $task->customer->id)->estimasi()->orderBy('id')->get()
                            : collect();
                    ?>
                    <?php if($estimasiMaterial->isNotEmpty()): ?>
                    <div class="pt-3.5 border-t border-border">
                        <span class="block text-[9px] font-bold text-text-muted uppercase mb-2 font-ui tracking-wider select-none">Estimasi Kebutuhan Alat &amp; Material</span>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 text-xs">
                            <?php $__currentLoopData = $estimasiMaterial; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $material): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <div class="flex justify-between items-center bg-surface-muted border border-border p-3 rounded-xl shadow-xs">
                                <span class="text-text-secondary font-ui font-semibold"><?php echo e($material->item_name); ?></span>
                                <span class="font-mono font-bold text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-950/60 px-2.5 py-0.5 rounded-lg border border-sky-200 dark:border-sky-900/50"><?php echo e(rtrim(rtrim(number_format($material->qty, 2, ',', '.'), '0'), ',')); ?> <?php echo e($material->unit); ?></span>
                            </div>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 pt-3.5 border-t border-border text-xs">
                        <div class="bg-surface-muted border border-border rounded-xl p-3 shadow-xs">
                            <span class="block text-[9px] text-text-muted font-bold uppercase font-ui select-none">Paket Layanan</span>
                            <span class="text-xs font-bold text-sky-600 dark:text-sky-400 block mt-1 font-ui"><?php echo e($service?->internetPackage?->name ?? $service?->package_name_snapshot ?? '-'); ?></span>
                            <?php if($service?->monthly_price): ?>
                            <span class="text-[10px] text-text-muted font-mono block mt-0.5 select-none">Rp <?php echo e(number_format($service->monthly_price, 0, ',', '.')); ?> / bulan</span>
                            <?php endif; ?>
                        </div>
                        <div class="bg-surface-muted border border-border rounded-xl p-3 shadow-xs">
                            <span class="block text-[9px] text-text-muted font-bold uppercase font-ui select-none">Alokasi ONT / Modem</span>
                            <?php if($device): ?>
                                <span class="text-xs font-bold text-text-main block mt-1 font-ui"><?php echo e($device->brand); ?> <?php echo e($device->model); ?></span>
                                <span class="text-[10px] font-mono text-text-muted block mt-0.5 font-ui font-semibold">SN: <?php echo e($device->serial_number ?: 'Belum diinput'); ?></span>
                            <?php else: ?>
                                <span class="text-xs text-warning font-semibold block mt-1 font-ui">Perangkat ONT akan dicatat saat laporan pemasangan diselesaikan.</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php elseif($task->task_type === \App\Enums\TaskType::MAINTENANCE): ?>
                <?php
                    $tech = $task->customer?->customerTechnicalDetail;
                    $device = $task->customer?->customerDevice;
                ?>
                <div class="space-y-4 select-text">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                        <div class="bg-surface-muted border border-border rounded-xl p-3 shadow-xs font-ui">
                            <span class="block text-[9px] text-text-muted font-bold uppercase tracking-wider mb-1 select-none">ODP &amp; Port Terhubung</span>
                            <span class="font-bold font-mono text-text-main text-xs block">
                                <?php echo e($device?->odp ?? $tech?->odp_number ?: '-'); ?> 
                                <?php if($device?->odp_port || $tech?->odp_port): ?>
                                    <span class="text-sky-600 dark:text-sky-400 font-bold">(Port <?php echo e($device?->odp_port ?? $tech?->odp_port); ?>)</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="bg-surface-muted border border-border rounded-xl p-3 shadow-xs font-ui">
                            <span class="block text-[9px] text-text-muted font-bold uppercase tracking-wider mb-1 select-none">OLT &amp; Port OLT</span>
                            <span class="font-bold font-mono text-text-main text-xs block">
                                <?php echo e($tech?->olt_number ?: '-'); ?>

                                <?php if($tech?->olt_port): ?> <span class="text-sky-600 dark:text-sky-400 font-bold">(Port <?php echo e($tech->olt_port); ?>)</span> <?php endif; ?>
                            </span>
                        </div>
                        <div class="bg-surface-muted border border-border rounded-xl p-3 shadow-xs font-ui font-mono">
                            <span class="block text-[9px] text-text-muted font-bold uppercase tracking-wider mb-1 select-none">Redaman RX Power</span>
                            <span class="font-bold text-text-main text-xs block">
                                <?php echo e($device?->signal_rx_power ?? $tech?->initial_attenuation ? ($device?->signal_rx_power ?? $tech?->initial_attenuation) . ' dBm' : '-'); ?>

                            </span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        <div class="bg-surface-muted border border-border rounded-xl p-3 shadow-xs">
                            <span class="block text-[9px] text-text-muted font-bold uppercase tracking-wider font-ui mb-1 select-none">Perangkat Terpasang</span>
                            <span class="font-bold text-text-main text-xs block font-ui">
                                <?php echo e($device?->brand ?? 'Modem'); ?> <?php echo e($device?->model); ?>

                            </span>
                            <span class="text-[10px] font-mono text-text-muted mt-0.5 block font-semibold">
                                SN: <?php echo e($device?->serial_number ?? $tech?->router_or_ont_serial ?: '-'); ?>

                            </span>
                        </div>
                        <div class="bg-surface-muted border border-border rounded-xl p-3 shadow-xs">
                            <span class="block text-[9px] text-text-muted font-bold uppercase tracking-wider font-ui mb-1 select-none">PPPoE User / IP Address</span>
                            <span class="font-bold font-mono text-text-main text-xs block"><?php echo e($device?->pppoe_username ?: '-'); ?></span>
                            <span class="text-[10px] font-mono text-sky-600 dark:text-sky-400 mt-0.5 block font-semibold">IP: <?php echo e($device?->ip_address ?? $tech?->ip_address ?: '-'); ?></span>
                        </div>
                    </div>
                </div>

                <?php elseif($task->task_type === \App\Enums\TaskType::AMBIL_MODEM): ?>
                <?php
                    $device = $task->customer?->customerDevice;
                ?>
                <div class="space-y-4 select-text">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        <div class="bg-surface-muted border border-border rounded-xl p-3 shadow-xs">
                            <span class="block text-[9px] text-text-muted font-bold uppercase font-ui select-none">Alasan Deaktivasi</span>
                            <span class="text-xs font-bold text-text-main mt-1 block font-ui"><?php echo e($task->description ?: 'Pengambilan Modem'); ?></span>
                        </div>
                        <div class="bg-surface-muted border border-border rounded-xl p-3 shadow-xs">
                            <span class="block text-[9px] text-text-muted font-bold uppercase font-ui select-none">Janji Temu</span>
                            <span class="text-xs font-bold text-text-main font-mono mt-1 block"><?php echo e($task->scheduled_at?->translatedFormat('l, d M Y — H:i') ?: 'Segera'); ?> WIB</span>
                        </div>
                    </div>

                    <div class="pt-3.5 border-t border-border space-y-2">
                        <span class="block text-[10px] text-text-main font-bold uppercase font-ui tracking-wider select-none">Aset ISP yang Wajib Ditarik</span>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                            <div class="bg-surface-muted border border-border p-3.5 rounded-xl shadow-xs">
                                <span class="block text-[9px] text-text-muted font-bold uppercase font-ui select-none">ONT / Modem</span>
                                <span class="font-bold text-text-main text-xs mt-1 block font-ui"><?php echo e($device?->brand ?: 'Modem ONT'); ?> <?php echo e($device?->model); ?></span>
                                <p class="text-[10px] text-sky-600 dark:text-sky-400 font-mono mt-1 font-bold select-all">SN: <?php echo e($device?->serial_number ?: 'PERIKSA FISIK'); ?></p>
                            </div>
                            <div class="bg-surface-muted border border-border p-3.5 rounded-xl shadow-xs">
                                <span class="block text-[9px] text-text-muted font-bold uppercase mb-1.5 font-ui select-none">Kelengkapan Standar</span>
                                <ul class="list-disc list-inside space-y-0.5 text-text-secondary text-[11px] font-ui font-semibold">
                                    <li>Adaptor Power</li>
                                    <li>Kabel Patchcord / LAN</li>
                                    <li>Router / STB Tambahan</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <?php elseif($task->task_type === \App\Enums\TaskType::CREQ): ?>
                <?php
                    $device = $task->customer?->customerDevice;
                    $tech = $task->customer?->customerTechnicalDetail;
                ?>
                <div class="space-y-3.5 select-text">
                    <div class="bg-surface-muted border border-border rounded-xl p-3 shadow-xs text-xs">
                        <span class="block text-[9px] text-text-muted font-bold uppercase font-ui select-none">Rincian Request</span>
                        <span class="text-xs font-bold text-text-main block mt-1 font-ui"><?php echo e($task->description ?: $task->title); ?></span>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        <div class="bg-surface-muted border border-border rounded-xl p-3 shadow-xs">
                            <span class="block text-[9px] text-text-muted font-bold uppercase font-ui select-none">SSID WiFi Eksisting</span>
                            <span class="text-xs font-bold font-mono text-text-main block mt-1"><?php echo e($device?->wifi_ssid ?? $tech?->ssid ?: 'Standard / Default'); ?></span>
                        </div>
                        <div class="bg-surface-muted border border-border rounded-xl p-3 shadow-xs">
                            <span class="block text-[9px] text-text-muted font-bold uppercase font-ui select-none">IP Gateway Akses</span>
                            <span class="text-xs font-bold font-mono text-text-main block mt-1"><?php echo e($device?->ip_address ?? $tech?->ip_address ?: '192.168.1.1'); ?></span>
                        </div>
                    </div>
                </div>

                <?php elseif(in_array($task->task_type, [\App\Enums\TaskType::OREQ, \App\Enums\TaskType::INFR])): ?>
                <div class="space-y-3 text-xs select-text">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="bg-surface-muted border border-border rounded-xl p-3 shadow-xs">
                            <span class="block text-[9px] text-text-muted font-bold uppercase font-ui select-none">POP Pembina</span>
                            <span class="text-xs font-bold text-text-main mt-1 block font-ui"><?php echo e($task->pop?->name ?? 'Pusat'); ?></span>
                        </div>
                        <div class="bg-surface-muted border border-border rounded-xl p-3 shadow-xs">
                            <span class="block text-[9px] text-text-muted font-bold uppercase font-ui select-none">Target / Lokasi</span>
                            <span class="text-xs text-text-main font-semibold mt-1 block font-ui"><?php echo e($task->pop?->address ?: 'Infrastruktur POP'); ?></span>
                        </div>
                    </div>
                    <div class="bg-surface-muted border border-border rounded-xl p-3.5 shadow-xs">
                        <span class="block text-[9px] text-text-muted font-bold uppercase font-ui select-none">Instruksi Pekerjaan</span>
                        <span class="text-xs font-semibold text-text-main leading-relaxed mt-1 block font-ui"><?php echo e($task->description ?: $task->title); ?></span>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            
            <?php
                $workToolRows = app(\App\Services\TaskWorkToolService::class)->displayRowsForTask($task);
            ?>
            <?php if($workToolRows->isNotEmpty()): ?>
            <div class="pt-5 border-t border-border">
                <div class="flex items-center gap-2 mb-3.5 select-none">
                    <svg class="h-4.5 w-4.5 text-sky-600 dark:text-sky-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z" />
                    </svg>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-text-main font-ui">Alat Kerja Wajib</h3>
                </div>
                <div class="flex flex-wrap gap-2 select-text">
                    <?php $__currentLoopData = $workToolRows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold px-3.5 py-1.5 rounded-xl border border-border bg-surface-muted text-text-main font-ui shadow-xs hover:border-sky-400 transition-colors">
                        <svg class="h-3.5 w-3.5 text-emerald-500 shrink-0 select-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                        <span><?php echo e($row->tool_name); ?></span><?php if($row->note): ?><span class="font-normal text-text-muted text-[10px]"> · <?php echo e($row->note); ?></span><?php endif; ?>
                    </span>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            </div>
            <?php endif; ?>

            
            <?php if($task->task_type->value === \App\Enums\TaskType::SURVEY->value): ?>
            <?php
                $surveyReport = $task->customer?->latestSurvey;
                $surveyFopTask = app(\App\Services\TaskMaterialService::class)->resolveTaskFor($task->customer, \App\Enums\TaskType::SURVEY);
                $surveyMaterials = $surveyFopTask
                    ? $surveyFopTask->materials()->estimasi()->orderBy('id')->get()
                    : collect();
            ?>
            <?php if($surveyReport || $surveyMaterials->isNotEmpty()): ?>
            <div class="pt-5 border-t border-border space-y-4 select-text">
                <div class="flex items-center gap-2 mb-1 select-none">
                    <svg class="h-4.5 w-4.5 text-sky-600 dark:text-sky-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-text-main font-ui">Laporan Result Survey Lapangan</h3>
                </div>

                <?php if($surveyReport): ?>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                    <div class="bg-surface-muted border border-border rounded-xl p-3 shadow-xs">
                        <span class="block text-[9px] text-text-muted font-bold uppercase font-ui select-none">Status Survey</span>
                        <span class="text-xs font-bold block mt-1 font-ui <?php echo e($surveyReport->survey_status === 'completed' ? 'text-emerald-600 dark:text-emerald-400' : ($surveyReport->survey_status === 'failed' ? 'text-rose-600 dark:text-rose-400' : 'text-amber-600 dark:text-amber-400')); ?>">
                            <?php echo e($surveyReport->survey_status === 'completed' ? 'LAYAK PASANG (Selesai)' : ($surveyReport->survey_status === 'failed' ? 'TIDAK LAYAK PASANG (Gagal)' : 'Menunggu / In Progress')); ?>

                        </span>
                    </div>
                    <div class="bg-surface-muted border border-border rounded-xl p-3 shadow-xs">
                        <span class="block text-[9px] text-text-muted font-bold uppercase font-ui select-none">Estimasi Kabel Dropcore</span>
                        <span class="text-xs font-bold font-mono text-sky-600 dark:text-sky-400 block mt-1"><?php echo e($surveyReport->cable_estimation_meter ? $surveyReport->cable_estimation_meter.' Meter' : '-'); ?></span>
                    </div>
                    <div class="bg-surface-muted border border-border rounded-xl p-3 shadow-xs">
                        <span class="block text-[9px] text-text-muted font-bold uppercase font-ui select-none">ODP Terdekat</span>
                        <span class="text-xs font-bold font-mono text-text-main block mt-1"><?php echo e($surveyReport->nearest_odp ?: '-'); ?></span>
                    </div>
                </div>

                <?php if($surveyReport->survey_note): ?>
                <div class="bg-surface-muted border border-border rounded-xl p-4 shadow-xs min-w-0 max-w-full overflow-hidden">
                    <div class="flex items-center gap-1.5 mb-2 select-none">
                        <svg class="h-3.5 w-3.5 text-sky-600 dark:text-sky-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        <span class="text-[10px] text-text-muted font-bold uppercase tracking-wider font-ui">Catatan Lapangan &amp; Kendala</span>
                    </div>
                    <p class="text-xs text-text-main leading-relaxed font-ui whitespace-pre-line break-words [word-break:break-word] min-w-0 font-medium"><?php echo e($surveyReport->survey_note); ?></p>
                </div>
                <?php endif; ?>
                <?php endif; ?>

                <?php if($surveyMaterials->isNotEmpty()): ?>
                <div>
                    <span class="block text-[10px] text-text-muted font-bold uppercase tracking-wider font-ui mb-2 select-none">Estimasi Material Dibutuhkan</span>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        <?php $__currentLoopData = $surveyMaterials; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $material): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="flex justify-between items-center bg-surface-muted border border-border p-3 rounded-xl shadow-xs">
                            <span class="text-text-secondary font-ui font-semibold"><?php echo e($material->item_name); ?><?php if($material->note): ?><span class="text-text-muted text-[10px]"> · <?php echo e($material->note); ?></span><?php endif; ?></span>
                            <span class="font-mono font-bold text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-950/60 px-2.5 py-0.5 rounded border border-sky-200 dark:border-sky-900/50"><?php echo e(rtrim(rtrim(number_format($material->qty, 2, ',', '.'), '0'), ',')); ?> <?php echo e($material->unit); ?></span>
                        </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if($surveyReport?->survey_photo || $surveyReport?->house_photo): ?>
                <div x-data class="bg-surface border border-border rounded-xl overflow-hidden shadow-xs select-none">
                    <div class="flex items-center gap-1.5 px-3.5 py-2.5 border-b border-border bg-surface-muted">
                        <svg class="h-3.5 w-3.5 text-sky-600 dark:text-sky-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                        </svg>
                        <span class="text-[10px] text-text-muted font-bold uppercase tracking-wider font-ui">Foto Hasil Survey (ODP &amp; Rumah)</span>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-3.5">
                        <?php if($surveyReport->survey_photo): ?>
                        <button type="button" @click="$dispatch('open-image-preview', { url: '<?php echo e(asset('storage/'.$surveyReport->survey_photo)); ?>', label: 'Foto ODP Survey' })" class="group relative block w-full rounded-lg overflow-hidden aspect-square bg-slate-100 dark:bg-slate-800 border border-border hover:border-sky-500 transition-all cursor-pointer">
                            <img src="<?php echo e(asset('storage/'.$surveyReport->survey_photo)); ?>" alt="Foto ODP Survey" class="h-full w-full object-cover">
                            <div class="absolute inset-0 flex items-end justify-center p-2 bg-slate-900/0 group-hover:bg-slate-900/60 backdrop-blur-0 group-hover:backdrop-blur-[2px] opacity-0 group-hover:opacity-100 transition-all duration-300">
                                <span class="text-white text-[10px] font-bold text-center font-ui">Foto ODP Survey</span>
                            </div>
                        </button>
                        <?php endif; ?>
                        <?php if($surveyReport->house_photo): ?>
                        <button type="button" @click="$dispatch('open-image-preview', { url: '<?php echo e(asset('storage/'.$surveyReport->house_photo)); ?>', label: 'Foto Rumah Pelanggan' })" class="group relative block w-full rounded-lg overflow-hidden aspect-square bg-slate-100 dark:bg-slate-800 border border-border hover:border-sky-500 transition-all cursor-pointer">
                            <img src="<?php echo e(asset('storage/'.$surveyReport->house_photo)); ?>" alt="Foto Rumah Customer" class="h-full w-full object-cover">
                            <div class="absolute inset-0 flex items-end justify-center p-2 bg-slate-900/0 group-hover:bg-slate-900/60 backdrop-blur-0 group-hover:backdrop-blur-[2px] opacity-0 group-hover:opacity-100 transition-all duration-300">
                                <span class="text-white text-[10px] font-bold text-center font-ui">Foto Rumah Pelanggan</span>
                            </div>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php elseif($task->task_type->value === \App\Enums\TaskType::PEMASANGAN->value): ?>
            <?php
                $installReport = $task->customer?->installations()->latest()->first();
                $installFopTask = app(\App\Services\TaskMaterialService::class)->resolveTaskFor($task->customer, \App\Enums\TaskType::PEMASANGAN);
                $installMaterials = $installFopTask
                    ? $installFopTask->materials()->terpakai()->orderBy('id')->get()
                    : collect();
            ?>
            <?php if($installReport || $installMaterials->isNotEmpty()): ?>
            <div class="pt-5 border-t border-border space-y-4 select-text">
                <div class="flex items-center gap-2 mb-1 select-none">
                    <svg class="h-4.5 w-4.5 text-sky-600 dark:text-sky-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-text-main font-ui">Laporan Hasil Pemasangan (PSB)</h3>
                </div>

                <?php if($installReport): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                    <div class="bg-surface-muted border border-border rounded-xl p-3 shadow-xs">
                        <span class="block text-[9px] text-text-muted font-bold uppercase font-ui select-none">Status Pemasangan</span>
                        <span class="text-xs font-bold block mt-1 font-ui <?php echo e($installReport->installation_status === 'completed' ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'); ?>">
                            <?php echo e($installReport->installation_status === 'completed' ? 'PEMASANGAN SELESAI' : strtoupper($installReport->installation_status)); ?>

                        </span>
                    </div>
                    <div class="bg-surface-muted border border-border rounded-xl p-3 shadow-xs">
                        <span class="block text-[9px] text-text-muted font-bold uppercase font-ui select-none">Waktu Selesai Pemasangan</span>
                        <span class="text-xs font-bold font-mono text-text-main block mt-1"><?php echo e($installReport->completed_at?->translatedFormat('d M Y — H:i') ?: '-'); ?> WIB</span>
                    </div>
                </div>

                <?php if($installReport->installation_note || $installReport->notes): ?>
                <div class="bg-surface-muted border border-border rounded-xl p-4 shadow-xs min-w-0 max-w-full overflow-hidden">
                    <div class="flex items-center gap-1.5 mb-2 select-none">
                        <svg class="h-3.5 w-3.5 text-sky-600 dark:text-sky-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        <span class="text-[10px] text-text-muted font-bold uppercase tracking-wider font-ui">Catatan Pemasangan Lapangan</span>
                    </div>
                    <p class="text-xs text-text-main leading-relaxed font-ui whitespace-pre-line break-words [word-break:break-word] min-w-0 font-medium"><?php echo e($installReport->installation_note ?: $installReport->notes); ?></p>
                </div>
                <?php endif; ?>
                <?php endif; ?>

                <?php if($installMaterials->isNotEmpty()): ?>
                <div>
                    <span class="block text-[10px] text-text-muted font-bold uppercase tracking-wider font-ui mb-2 select-none">Material Pemasangan Terpakai</span>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        <?php $__currentLoopData = $installMaterials; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $material): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="flex justify-between items-center bg-surface-muted border border-border p-3 rounded-xl shadow-xs">
                            <span class="text-text-secondary font-ui font-semibold"><?php echo e($material->item_name); ?><?php if($material->note): ?><span class="text-text-muted text-[10px]"> · <?php echo e($material->note); ?></span><?php endif; ?></span>
                            <span class="font-mono font-bold text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-950/60 px-2.5 py-0.5 rounded border border-sky-200 dark:border-sky-900/50"><?php echo e(rtrim(rtrim(number_format($material->qty, 2, ',', '.'), '0'), ',')); ?> <?php echo e($material->unit); ?></span>
                        </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if($installReport?->installation_photo || $installReport?->contract_photo || $installReport?->signature_photo): ?>
                <div x-data class="bg-surface border border-border rounded-xl overflow-hidden shadow-xs select-none">
                    <div class="flex items-center gap-1.5 px-3.5 py-2.5 border-b border-border bg-surface-muted">
                        <svg class="h-3.5 w-3.5 text-sky-600 dark:text-sky-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                        </svg>
                        <span class="text-[10px] text-text-muted font-bold uppercase tracking-wider font-ui">Foto Hasil Pemasangan &amp; Berita Acara</span>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-3.5">
                        <?php if($installReport->installation_photo): ?>
                        <button type="button" @click="$dispatch('open-image-preview', { url: '<?php echo e(asset('storage/'.$installReport->installation_photo)); ?>', label: 'Foto Bukti Pemasangan' })" class="group relative block w-full rounded-lg overflow-hidden aspect-square bg-slate-100 dark:bg-slate-800 border border-border hover:border-sky-500 transition-all cursor-pointer">
                            <img src="<?php echo e(asset('storage/'.$installReport->installation_photo)); ?>" alt="Foto Pemasangan" class="h-full w-full object-cover">
                            <div class="absolute inset-0 flex items-end justify-center p-2 bg-slate-900/0 group-hover:bg-slate-900/60 backdrop-blur-0 group-hover:backdrop-blur-[2px] opacity-0 group-hover:opacity-100 transition-all duration-300">
                                <span class="text-white text-[10px] font-bold text-center font-ui">Foto Pemasangan</span>
                            </div>
                        </button>
                        <?php endif; ?>
                        <?php if($installReport->contract_photo): ?>
                        <button type="button" @click="$dispatch('open-image-preview', { url: '<?php echo e(asset('storage/'.$installReport->contract_photo)); ?>', label: 'Foto Kontrak / BA' })" class="group relative block w-full rounded-lg overflow-hidden aspect-square bg-slate-100 dark:bg-slate-800 border border-border hover:border-sky-500 transition-all cursor-pointer">
                            <img src="<?php echo e(asset('storage/'.$installReport->contract_photo)); ?>" alt="Foto Kontrak/BA" class="h-full w-full object-cover">
                            <div class="absolute inset-0 flex items-end justify-center p-2 bg-slate-900/0 group-hover:bg-slate-900/60 backdrop-blur-0 group-hover:backdrop-blur-[2px] opacity-0 group-hover:opacity-100 transition-all duration-300">
                                <span class="text-white text-[10px] font-bold text-center font-ui">Foto Kontrak / BA</span>
                            </div>
                        </button>
                        <?php endif; ?>
                        <?php if($installReport->signature_photo): ?>
                        <button type="button" @click="$dispatch('open-image-preview', { url: '<?php echo e(asset('storage/'.$installReport->signature_photo)); ?>', label: 'Foto Tanda Tangan' })" class="group relative block w-full rounded-lg overflow-hidden aspect-square bg-slate-100 dark:bg-slate-800 border border-border hover:border-sky-500 transition-all cursor-pointer">
                            <img src="<?php echo e(asset('storage/'.$installReport->signature_photo)); ?>" alt="Foto Tanda Tangan" class="h-full w-full object-cover">
                            <div class="absolute inset-0 flex items-end justify-center p-2 bg-slate-900/0 group-hover:bg-slate-900/60 backdrop-blur-0 group-hover:backdrop-blur-[2px] opacity-0 group-hover:opacity-100 transition-all duration-300">
                                <span class="text-white text-[10px] font-bold text-center font-ui">Tanda Tangan</span>
                            </div>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php else: ?>
            <?php
                $maintenanceFopTask = app(\App\Services\TaskWorkToolService::class)->resolveTaskFor($task);
                $materialsTerpakai = $maintenanceFopTask
                    ? $maintenanceFopTask->materials()->terpakai()->orderBy('id')->get()
                    : collect();
                $maintenanceReport = $task->maintenanceReport;
            ?>
            <?php if($maintenanceReport || $materialsTerpakai->isNotEmpty()): ?>
            <div class="pt-5 border-t border-border space-y-4 select-text">
                <div class="flex items-center gap-2 mb-1 select-none">
                    <svg class="h-4.5 w-4.5 text-sky-600 dark:text-sky-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-text-main font-ui">Laporan Pekerjaan Teknisi</h3>
                </div>

                <?php if($maintenanceReport?->kendala_teknis): ?>
                <div class="bg-surface-muted border border-border rounded-xl p-4 shadow-xs min-w-0 max-w-full overflow-hidden">
                    <div class="flex items-center gap-1.5 mb-2 select-none">
                        <svg class="h-3.5 w-3.5 text-sky-600 dark:text-sky-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                        </svg>
                        <span class="text-[10px] text-text-muted font-bold uppercase tracking-wider font-ui">Kendala &amp; Solusi</span>
                    </div>
                    <p class="text-xs text-text-main leading-relaxed font-ui whitespace-pre-line break-words [word-break:break-word] min-w-0 font-medium"><?php echo e($maintenanceReport->kendala_teknis); ?></p>
                </div>
                <?php endif; ?>

                <?php if($materialsTerpakai->isNotEmpty()): ?>
                <div>
                    <span class="block text-[10px] text-text-muted font-bold uppercase tracking-wider font-ui mb-2 select-none">Material Terpakai</span>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        <?php $__currentLoopData = $materialsTerpakai; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $material): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="flex justify-between items-center bg-surface-muted border border-border p-3 rounded-xl shadow-xs">
                            <span class="text-text-secondary font-ui font-semibold"><?php echo e($material->item_name); ?><?php if($material->note): ?><span class="text-text-muted text-[10px]"> · <?php echo e($material->note); ?></span><?php endif; ?></span>
                            <span class="font-mono font-bold text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-950/60 px-2.5 py-0.5 rounded border border-sky-200 dark:border-sky-900/50"><?php echo e(rtrim(rtrim(number_format($material->qty, 2, ',', '.'), '0'), ',')); ?> <?php echo e($material->unit); ?></span>
                        </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if($maintenanceReport?->opm_photo || $maintenanceReport?->speedtest_photo): ?>
                <div x-data class="bg-surface border border-border rounded-xl overflow-hidden shadow-xs select-none">
                    <div class="flex items-center gap-1.5 px-3.5 py-2.5 border-b border-border bg-surface-muted">
                        <svg class="h-3.5 w-3.5 text-sky-600 dark:text-sky-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                        </svg>
                        <span class="text-[10px] text-text-muted font-bold uppercase tracking-wider font-ui">Foto OPM &amp; Speedtest</span>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 p-3.5">
                        <?php if($maintenanceReport->opm_photo): ?>
                        <button type="button" @click="$dispatch('open-image-preview', { url: '<?php echo e(asset('storage/'.$maintenanceReport->opm_photo)); ?>', label: 'Foto OPM' })" class="group relative block w-full rounded-lg overflow-hidden aspect-square bg-slate-100 dark:bg-slate-800 border border-border hover:border-sky-500 transition-all cursor-pointer">
                            <img src="<?php echo e(asset('storage/'.$maintenanceReport->opm_photo)); ?>" alt="Foto OPM" class="h-full w-full object-cover">
                            <div class="absolute inset-0 flex items-end justify-center p-2 bg-slate-900/0 group-hover:bg-slate-900/60 backdrop-blur-0 group-hover:backdrop-blur-[2px] opacity-0 group-hover:opacity-100 transition-all duration-300">
                                <span class="text-white text-[10px] font-bold text-center font-ui">Foto OPM</span>
                            </div>
                        </button>
                        <?php endif; ?>
                        <?php if($maintenanceReport->speedtest_photo): ?>
                        <button type="button" @click="$dispatch('open-image-preview', { url: '<?php echo e(asset('storage/'.$maintenanceReport->speedtest_photo)); ?>', label: 'Foto Speedtest' })" class="group relative block w-full rounded-lg overflow-hidden aspect-square bg-slate-100 dark:bg-slate-800 border border-border hover:border-sky-500 transition-all cursor-pointer">
                            <img src="<?php echo e(asset('storage/'.$maintenanceReport->speedtest_photo)); ?>" alt="Foto Speedtest" class="h-full w-full object-cover">
                            <div class="absolute inset-0 flex items-end justify-center p-2 bg-slate-900/0 group-hover:bg-slate-900/60 backdrop-blur-0 group-hover:backdrop-blur-[2px] opacity-0 group-hover:opacity-100 transition-all duration-300">
                                <span class="text-white text-[10px] font-bold text-center font-ui">Foto Speedtest</span>
                            </div>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>

        </div>

        
        <?php if(in_array($task->status->value, ['pending', 'terjadwal'])): ?>
        <?php if(auth()->user()->can('fopReject', $task) || auth()->user()->can('fopPending', $task)): ?>
        <div class="p-4 sm:p-5 border-t border-border flex flex-col sm:flex-row gap-3 items-center justify-between bg-slate-50/20 dark:bg-slate-800/5 select-none">
            <div>
                <h4 class="text-xs font-bold text-text-main mb-0.5 font-ui">Manajemen Task (FOP Koordinator)</h4>
                <p class="text-[11px] text-text-muted font-ui">Kelola task penugasan sebelum dikerjakan oleh tim teknisi.</p>
            </div>
            <div class="flex items-center gap-2 w-full sm:w-auto">
                <?php if($task->status->value === 'pending'): ?>
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('fopReject', $task)): ?>
                    <button x-data @click="$dispatch('open-modal', 'fop-reject-task-pending')"
                            class="flex-1 sm:flex-initial inline-flex items-center justify-center gap-1.5 text-xs font-bold px-3.5 py-2.5 border bg-surface hover:bg-surface-muted transition-all rounded-xl cursor-pointer font-ui active:scale-95 shadow-sm"
                            style="border-color:var(--color-error-border); color:var(--color-error)">
                        Reject Task
                    </button>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if($task->status->value === 'terjadwal'): ?>
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('fopPending', $task)): ?>
                    <button x-data @click="$dispatch('open-modal', 'fop-pending-task')"
                            class="flex-1 sm:flex-initial inline-flex items-center justify-center gap-1.5 text-xs font-bold px-3.5 py-2.5 border bg-surface hover:bg-surface-muted transition-all rounded-xl cursor-pointer font-ui active:scale-95 shadow-sm"
                            style="border-color:var(--color-warning-border); color:var(--color-warning)">
                        Set Pending
                    </button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        
        <?php if($task->status->value === 'selesai' && $task->fop_review_status === 'pending'): ?>
        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('review', $task)): ?>
        <?php if($task->task_type->value === 'PSB'): ?>
        <div class="p-4 sm:p-5 border-t border-border flex flex-col sm:flex-row gap-3 items-center justify-between bg-slate-50/20 dark:bg-slate-800/5 select-none">
            <div class="min-w-0 flex-1">
                <h4 class="text-xs font-bold text-text-main mb-0.5 font-ui">Approve Pemasangan (Verifikasi Admin)</h4>
                <p class="text-[11px] text-text-muted font-ui leading-relaxed">Aktivasi layanan (CID + tagihan awal) hanya boleh diproses melalui halaman Verifikasi Admin.</p>
            </div>
            <?php if($task->customer_id): ?>
                <?php if(auth()->user()->hasPermission('customers.detail.installation.validate') || auth()->user()->hasFullAccess()): ?>
                <a href="<?php echo e(route('customers.verification.admin', $task->customer_id)); ?>"
                   class="w-full sm:w-auto text-center inline-flex items-center justify-center gap-1.5 text-xs font-bold px-4 py-2.5 rounded-xl text-white transition-all shadow-md shadow-sky-500/10 cursor-pointer font-ui active:scale-95"
                   style="background:var(--color-primary)">
                    Buka Verifikasi Admin
                </a>
                <?php else: ?>
                <a href="<?php echo e(route('customers.installation.report', ['customer' => $task->customer_id, 'return_to' => route('tasks.show', $task)])); ?>"
                   class="w-full sm:w-auto text-center inline-flex items-center justify-center gap-1.5 text-xs font-bold px-4 py-2.5 rounded-xl text-white transition-all shadow-md shadow-sky-500/10 cursor-pointer font-ui active:scale-95"
                   style="background:var(--color-primary)">
                    Lihat Laporan Pemasangan
                </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="p-4 sm:p-5 border-t border-border flex flex-col sm:flex-row gap-3 items-center justify-between bg-slate-50/20 dark:bg-slate-800/5 select-none">
            <div>
                <h4 class="text-xs font-bold text-text-main mb-0.5 font-ui">Review Hasil Pekerjaan (Khusus FOP)</h4>
                <p class="text-[11px] text-text-muted font-ui">Task ini telah diselesaikan oleh teknisi dan sedang menunggu persetujuan Anda.</p>
            </div>
            <div class="flex items-center gap-2 w-full sm:w-auto">
                <button x-data @click="$dispatch('open-modal', 'reject-task')"
                        class="flex-1 sm:flex-initial inline-flex items-center justify-center gap-1.5 text-xs font-bold px-3.5 py-2.5 border bg-surface hover:bg-surface-muted transition-all rounded-xl cursor-pointer font-ui active:scale-95 shadow-sm"
                        style="border-color:var(--color-error-border); color:var(--color-error)">
                    Reject Laporan
                </button>
                <form action="<?php echo e(route('tasks.review', $task)); ?>" method="POST" class="flex-1 sm:flex-initial">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="approve">
                    <button type="submit"
                            class="w-full inline-flex items-center justify-center gap-1.5 text-xs font-bold px-4 py-2.5 rounded-xl text-white transition-all shadow-md shadow-sky-600/10 cursor-pointer font-ui active:scale-95"
                            style="background:var(--color-primary)">
                        Approve Task
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    
    <?php if(in_array($task->status->value, ['terjadwal', 'in_progress', 'pending'])): ?>
    <div class="flex flex-wrap items-center justify-end gap-2.5 pt-1.5 font-ui select-none">
        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('statusReschedule', $task)): ?>
        <button type="button" x-data @click="$dispatch('open-modal', 'reschedule-task-<?php echo e($task->id); ?>')"
                class="inline-flex items-center justify-center gap-1.5 text-xs font-bold px-4 py-2.5 rounded-xl border bg-surface hover:bg-warning/5 cursor-pointer active:scale-95 transition-all shadow-sm"
                style="border-color:var(--color-warning-border); color:var(--color-warning)">
            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>Set Pending</span>
        </button>
        <?php endif; ?>
        <?php if($task->status->value === 'terjadwal'): ?>
            <?php if($task->scheduled_at && !$task->scheduled_at->startOfDay()->isFuture()): ?>
            <?php if($task->task_type->value === \App\Enums\TaskType::SURVEY->value): ?>
                <?php if($task->customer_id && auth()->user()->hasPermission('customers.detail.survey.update') && $task->teamMembers->pluck('user_id')->contains(auth()->id())): ?>
                <form action="<?php echo e(route('customers.survey.start', $task->customer_id)); ?>" method="POST" class="w-full sm:w-auto">
                    <?php echo csrf_field(); ?>
                    <button type="submit"
                            class="w-full inline-flex items-center justify-center gap-1.5 text-xs font-bold px-4 py-2.5 rounded-xl text-white transition-all shadow-md shadow-amber-500/10 cursor-pointer active:scale-95"
                            style="background:var(--color-warning)">
                        <svg class="h-4.5 w-4.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                        </svg>
                        Mulai Survey
                    </button>
                </form>
                <?php endif; ?>
            <?php elseif($task->task_type->value === \App\Enums\TaskType::PEMASANGAN->value): ?>
                <?php if($task->customer_id && auth()->user()->hasPermission('customers.detail.installation.update') && $task->teamMembers->pluck('user_id')->contains(auth()->id())): ?>
                <form action="<?php echo e(route('customers.installation.start', $task->customer_id)); ?>" method="POST" class="w-full sm:w-auto">
                    <?php echo csrf_field(); ?>
                    <button type="submit"
                            class="w-full inline-flex items-center justify-center gap-1.5 text-xs font-bold px-4 py-2.5 rounded-xl text-white transition-all shadow-md shadow-amber-500/10 cursor-pointer active:scale-95"
                            style="background:var(--color-warning)">
                        <svg class="h-4.5 w-4.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                        </svg>
                        Mulai Pemasangan
                    </button>
                </form>
                <?php endif; ?>
            <?php else: ?>
                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('statusStart', $task)): ?>
                <form action="<?php echo e(route('tasks.start', $task)); ?>" method="POST" class="w-full sm:w-auto">
                    <?php echo csrf_field(); ?>
                    <button type="submit"
                            class="w-full inline-flex items-center justify-center gap-1.5 text-xs font-bold px-4 py-2.5 rounded-xl text-white transition-all shadow-md shadow-amber-500/10 cursor-pointer active:scale-95"
                            style="background:var(--color-warning)">
                        <svg class="h-4.5 w-4.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                        </svg>
                        <?php echo e($task->task_type->value === \App\Enums\TaskType::MAINTENANCE->value ? 'Mulai Maintenance' : 'Mulai Task'); ?>

                    </button>
                </form>
                <?php endif; ?>
            <?php endif; ?>
            <?php else: ?>
            <span class="w-full sm:w-auto text-center text-xs text-text-muted px-4 py-2.5 border border-border rounded-xl bg-surface font-semibold">
                Dijadwalkan <?php echo e($task->scheduled_at?->translatedFormat('l, d M Y')); ?>

            </span>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('statusComplete', $task)): ?>
        <?php if(in_array($task->status->value, ['in_progress', 'pending'])): ?>
            <?php
                $reportUrl = match(true) {
                    $task->task_type->value === \App\Enums\TaskType::SURVEY->value => route('customers.survey.report', ['customer' => $task->customer_id, 'return_to' => route('tasks.show', $task)]),
                    $task->task_type->value === \App\Enums\TaskType::PEMASANGAN->value => route('customers.installation.report', ['customer' => $task->customer_id, 'return_to' => route('tasks.show', $task)]),
                    default => route('tasks.maintenance.report', $task),
                };
                $reportLabel = match(true) {
                    $task->task_type->value === \App\Enums\TaskType::SURVEY->value => 'Laporan Survey',
                    $task->task_type->value === \App\Enums\TaskType::PEMASANGAN->value => 'Laporan Pemasangan',
                    default => 'Isi Laporan',
                };
            ?>
            <?php if($task->status->value === 'in_progress'): ?>
                <?php if (isset($component)) { $__componentOriginalb21bb3349113a9ccaaa78d0a239117e9 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb21bb3349113a9ccaaa78d0a239117e9 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.task.report-choice-dialog','data' => ['task' => $task,'reportUrl' => $reportUrl,'class' => 'w-full sm:w-auto inline-flex items-center justify-center gap-1.5 text-xs font-bold py-2.5 px-4 rounded-xl text-white bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 active:scale-[0.98] transition-all shadow-md shadow-emerald-500/10 cursor-pointer whitespace-nowrap']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('task.report-choice-dialog'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['task' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($task),'report-url' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($reportUrl),'class' => 'w-full sm:w-auto inline-flex items-center justify-center gap-1.5 text-xs font-bold py-2.5 px-4 rounded-xl text-white bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 active:scale-[0.98] transition-all shadow-md shadow-emerald-500/10 cursor-pointer whitespace-nowrap']); ?>
                    <svg class="h-4.5 w-4.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span><?php echo e($reportLabel); ?></span>
                 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb21bb3349113a9ccaaa78d0a239117e9)): ?>
<?php $attributes = $__attributesOriginalb21bb3349113a9ccaaa78d0a239117e9; ?>
<?php unset($__attributesOriginalb21bb3349113a9ccaaa78d0a239117e9); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb21bb3349113a9ccaaa78d0a239117e9)): ?>
<?php $component = $__componentOriginalb21bb3349113a9ccaaa78d0a239117e9; ?>
<?php unset($__componentOriginalb21bb3349113a9ccaaa78d0a239117e9); ?>
<?php endif; ?>
            <?php else: ?>
                <a href="<?php echo e($reportUrl); ?>"
                   class="w-full sm:w-auto inline-flex items-center justify-center gap-1.5 text-xs font-bold px-4 py-2.5 rounded-xl text-white transition-all shadow-md shadow-emerald-500/10 cursor-pointer active:scale-95 whitespace-nowrap"
                   style="background:var(--color-success)">
                    <svg class="h-4.5 w-4.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>Lanjutkan Laporan</span>
                </a>
            <?php endif; ?>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>


<?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('fopReject', $task)): ?>
<?php if (isset($component)) { $__componentOriginal7762953202be6518eecd1cfbd075bf2f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal7762953202be6518eecd1cfbd075bf2f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.modal','data' => ['name' => 'fop-reject-task-pending','title' => 'Reject Pending Task','maxWidth' => 'sm']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.modal'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'fop-reject-task-pending','title' => 'Reject Pending Task','maxWidth' => 'sm']); ?>
    <p class="text-xs text-text-secondary mb-3 font-ui">
        Task ini belum dijadwalkan dan akan tetap berstatus <span class="font-semibold text-text-main">Pending</span>, namun dengan keterangan reject.
    </p>
    <form id="form-fop-reject-pending" action="<?php echo e(route('tasks.fop-reject', $task)); ?>" method="POST">
        <?php echo csrf_field(); ?>
        <div class="space-y-1.5 font-ui">
            <label class="block text-[11px] font-semibold uppercase tracking-wider text-text-muted">Alasan Reject <span class="text-error">*</span></label>
            <?php if (isset($component)) { $__componentOriginal62d1193389a71cd99ff302a00abbf991 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal62d1193389a71cd99ff302a00abbf991 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.textarea','data' => ['name' => 'reject_reason','rows' => '3','placeholder' => 'Alasan reject task...','required' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.textarea'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'reject_reason','rows' => '3','placeholder' => 'Alasan reject task...','required' => true]); ?>
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
        </div>
    </form>
     <?php $__env->slot('footer', null, []); ?> 
        <?php if (isset($component)) { $__componentOriginala8bb031a483a05f647cb99ed3a469847 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala8bb031a483a05f647cb99ed3a469847 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.button','data' => ['type' => 'button','variant' => 'secondary','xOn:click' => '$dispatch(\'close-modal\', \'fop-reject-task-pending\')']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'button','variant' => 'secondary','x-on:click' => '$dispatch(\'close-modal\', \'fop-reject-task-pending\')']); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.button','data' => ['type' => 'submit','form' => 'form-fop-reject-pending','variant' => 'danger']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'submit','form' => 'form-fop-reject-pending','variant' => 'danger']); ?>
            Reject Task
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
<?php endif; ?>


<?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('fopPending', $task)): ?>
<?php if (isset($component)) { $__componentOriginal7762953202be6518eecd1cfbd075bf2f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal7762953202be6518eecd1cfbd075bf2f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.modal','data' => ['name' => 'fop-pending-task','title' => 'Set Task Menjadi Pending','maxWidth' => 'sm']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.modal'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'fop-pending-task','title' => 'Set Task Menjadi Pending','maxWidth' => 'sm']); ?>
    <p class="text-xs text-text-secondary mb-3 font-ui">
        Task ini akan diubah statusnya dari <span class="font-semibold text-text-main">Terjadwal</span> menjadi <span class="font-semibold text-text-main">Pending</span>. Tim teknisi yang sudah di-assign tidak akan terhapus.
    </p>
    <form id="form-fop-pending" action="<?php echo e(route('tasks.fop-pending', $task)); ?>" method="POST">
        <?php echo csrf_field(); ?>
        <div class="space-y-1.5 font-ui">
            <label class="block text-[11px] font-semibold uppercase tracking-wider text-text-muted">Alasan Pending <span class="text-error">*</span></label>
            <?php if (isset($component)) { $__componentOriginal62d1193389a71cd99ff302a00abbf991 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal62d1193389a71cd99ff302a00abbf991 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.textarea','data' => ['name' => 'pending_reason','rows' => '3','placeholder' => 'Alasan mengapa di-pending...','required' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.textarea'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'pending_reason','rows' => '3','placeholder' => 'Alasan mengapa di-pending...','required' => true]); ?>
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
        </div>
    </form>
     <?php $__env->slot('footer', null, []); ?> 
        <?php if (isset($component)) { $__componentOriginala8bb031a483a05f647cb99ed3a469847 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala8bb031a483a05f647cb99ed3a469847 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.button','data' => ['type' => 'button','variant' => 'secondary','xOn:click' => '$dispatch(\'close-modal\', \'fop-pending-task\')']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'button','variant' => 'secondary','x-on:click' => '$dispatch(\'close-modal\', \'fop-pending-task\')']); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.button','data' => ['type' => 'submit','form' => 'form-fop-pending','variant' => 'warning']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'submit','form' => 'form-fop-pending','variant' => 'warning']); ?>
            Set Pending
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
<?php endif; ?>



<?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('statusReschedule', $task)): ?>
<?php if (isset($component)) { $__componentOriginal7762953202be6518eecd1cfbd075bf2f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal7762953202be6518eecd1cfbd075bf2f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.modal','data' => ['name' => 'reschedule-task-'.e($task->id).'','title' => 'Pending Task (Reschedule)','maxWidth' => 'sm']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.modal'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'reschedule-task-'.e($task->id).'','title' => 'Pending Task (Reschedule)','maxWidth' => 'sm']); ?>
    <p class="text-xs text-text-secondary mb-3 font-ui">
        Task ini akan dilepas dari Anda dan dikembalikan ke antrian Task FOP untuk dijadwalkan ulang ke teknisi/hari lain.
        <span class="font-semibold text-text-main">Assignment Anda pada task ini akan dihapus.</span>
    </p>
    <form id="form-reschedule-<?php echo e($task->id); ?>" action="<?php echo e(route('tasks.reschedule', $task)); ?>" method="POST">
        <?php echo csrf_field(); ?>
        <div class="space-y-1.5 font-ui">
            <label class="block text-[11px] font-semibold uppercase tracking-wider text-text-muted">Alasan Pending <span class="text-error">*</span></label>
            <?php if (isset($component)) { $__componentOriginal62d1193389a71cd99ff302a00abbf991 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal62d1193389a71cd99ff302a00abbf991 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.textarea','data' => ['name' => 'pending_reason','rows' => '3','placeholder' => 'Alasan mengapa task ini di-pending/reschedule...','required' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.textarea'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'pending_reason','rows' => '3','placeholder' => 'Alasan mengapa task ini di-pending/reschedule...','required' => true]); ?>
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
        </div>
    </form>
     <?php $__env->slot('footer', null, []); ?> 
        <?php if (isset($component)) { $__componentOriginala8bb031a483a05f647cb99ed3a469847 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala8bb031a483a05f647cb99ed3a469847 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.button','data' => ['type' => 'button','variant' => 'secondary','xOn:click' => '$dispatch(\'close-modal\', \'reschedule-task-'.e($task->id).'\')']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'button','variant' => 'secondary','x-on:click' => '$dispatch(\'close-modal\', \'reschedule-task-'.e($task->id).'\')']); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.button','data' => ['type' => 'submit','form' => 'form-reschedule-'.e($task->id).'','variant' => 'warning']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'submit','form' => 'form-reschedule-'.e($task->id).'','variant' => 'warning']); ?>
            Pending Task
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
<?php endif; ?>


<?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('review', $task)): ?>
<?php if (isset($component)) { $__componentOriginal7762953202be6518eecd1cfbd075bf2f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal7762953202be6518eecd1cfbd075bf2f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.modal','data' => ['name' => 'reject-task','title' => 'Reject Laporan Task','maxWidth' => 'sm']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.modal'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'reject-task','title' => 'Reject Laporan Task','maxWidth' => 'sm']); ?>
    <p class="text-xs text-text-secondary mb-3 font-ui">
        Task ini akan dikembalikan ke status <span class="font-semibold text-text-main">In Progress</span>. 
        Teknisi harus memperbaiki laporan berdasarkan alasan reject.
    </p>
    <form id="form-reject-task" action="<?php echo e(route('tasks.review', $task)); ?>" method="POST">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="reject">
        <div class="space-y-1.5 font-ui">
            <label class="block text-[11px] font-semibold uppercase tracking-wider text-text-muted">Alasan Reject <span class="text-error">*</span></label>
            <?php if (isset($component)) { $__componentOriginal62d1193389a71cd99ff302a00abbf991 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal62d1193389a71cd99ff302a00abbf991 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.textarea','data' => ['name' => 'reason','rows' => '3','placeholder' => 'Alasan reject (misal: Foto bukti kurang jelas)...','required' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.textarea'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'reason','rows' => '3','placeholder' => 'Alasan reject (misal: Foto bukti kurang jelas)...','required' => true]); ?>
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
        </div>
    </form>
     <?php $__env->slot('footer', null, []); ?> 
        <?php if (isset($component)) { $__componentOriginala8bb031a483a05f647cb99ed3a469847 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala8bb031a483a05f647cb99ed3a469847 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.button','data' => ['type' => 'button','variant' => 'secondary','xOn:click' => '$dispatch(\'close-modal\', \'reject-task\')']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'button','variant' => 'secondary','x-on:click' => '$dispatch(\'close-modal\', \'reject-task\')']); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.button','data' => ['type' => 'submit','form' => 'form-reject-task','variant' => 'danger']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'submit','form' => 'form-reject-task','variant' => 'danger']); ?>
            Konfirmasi Reject
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
<?php endif; ?>


<?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('cancel', $task)): ?>
<?php if (isset($component)) { $__componentOriginal7762953202be6518eecd1cfbd075bf2f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal7762953202be6518eecd1cfbd075bf2f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.modal','data' => ['name' => 'cancel-task','title' => 'Batalkan Task','maxWidth' => 'sm']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.modal'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'cancel-task','title' => 'Batalkan Task','maxWidth' => 'sm']); ?>
    <p class="text-xs text-text-secondary mb-3 font-ui">
        Task <span class="font-mono font-semibold"><?php echo e($task->task_number); ?></span> akan dibatalkan.
        Tindakan ini tidak dapat dibatalkan.
    </p>
    <form id="form-cancel-task" action="<?php echo e(route('tasks.cancel', $task)); ?>" method="POST">
        <?php echo csrf_field(); ?>
        <div class="space-y-1.5 font-ui">
            <label class="block text-[11px] font-semibold uppercase tracking-wider text-text-muted">Alasan Pembatalan <span class="text-error">*</span></label>
            <?php if (isset($component)) { $__componentOriginal62d1193389a71cd99ff302a00abbf991 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal62d1193389a71cd99ff302a00abbf991 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.textarea','data' => ['name' => 'cancel_reason','rows' => '3','placeholder' => 'Alasan pembatalan...','required' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.textarea'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'cancel_reason','rows' => '3','placeholder' => 'Alasan pembatalan...','required' => true]); ?>
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
        </div>
    </form>
     <?php $__env->slot('footer', null, []); ?> 
        <?php if (isset($component)) { $__componentOriginala8bb031a483a05f647cb99ed3a469847 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala8bb031a483a05f647cb99ed3a469847 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.button','data' => ['type' => 'button','variant' => 'secondary','xOn:click' => '$dispatch(\'close-modal\', \'cancel-task\')']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'button','variant' => 'secondary','x-on:click' => '$dispatch(\'close-modal\', \'cancel-task\')']); ?>
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.button','data' => ['type' => 'submit','form' => 'form-cancel-task','variant' => 'danger']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'submit','form' => 'form-cancel-task','variant' => 'danger']); ?>
            Ya, Batalkan Task
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
<?php endif; ?>



<?php
$taskData = [
    'id' => $task->id,
    'task_number' => $task->task_number,
    'customer_name' => $task->customer?->full_name ?? '—',
    'customer_address' => $task->customer?->address ?? '—',
    'pop_name' => $task->pop?->name ?? '—',
    'task_type' => $task->task_type->value,
    'submit_url_survey' => route('customers.survey.store', $task),
    'submit_url_install' => route('customers.installation.store', $task),
    'current_package_id' => $task->customer?->customerService?->internet_package_id,
];
?>

<?php if (isset($component)) { $__componentOriginalc54844c8500937c8c904b75d0190ca4d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalc54844c8500937c8c904b75d0190ca4d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.image-preview-modal','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.image-preview-modal'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalc54844c8500937c8c904b75d0190ca4d)): ?>
<?php $attributes = $__attributesOriginalc54844c8500937c8c904b75d0190ca4d; ?>
<?php unset($__attributesOriginalc54844c8500937c8c904b75d0190ca4d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalc54844c8500937c8c904b75d0190ca4d)): ?>
<?php $component = $__componentOriginalc54844c8500937c8c904b75d0190ca4d; ?>
<?php unset($__componentOriginalc54844c8500937c8c904b75d0190ca4d); ?>
<?php endif; ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/tasks/show.blade.php ENDPATH**/ ?>