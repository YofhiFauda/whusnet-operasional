<?php
    $accentColor = match(true) {
        $task->isOverSla() || ($task->status->value === 'terjadwal' && $task->scheduled_at && $task->scheduled_at->isPast() && !$task->scheduled_at->isToday()) => 'bg-rose-500',
        $task->status->value === 'in_progress' => 'bg-amber-500',
        $task->status->value === 'selesai' => 'bg-emerald-500',
        $task->status->value === 'dibatalkan' => 'bg-slate-400 dark:bg-slate-600',
        $task->task_type->value === 'SURVEY' => 'bg-sky-500',
        $task->task_type->value === 'PSB' => 'bg-emerald-500',
        default => 'bg-slate-300 dark:bg-slate-700',
    };

    $statusClasses = match($task->status->value) {
        'terjadwal' => 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/30 dark:text-blue-400 dark:border-blue-900/50',
        'in_progress' => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/30 dark:text-amber-400 dark:border-amber-900/50',
        'selesai' => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/30 dark:text-emerald-400 dark:border-emerald-900/50',
        'dibatalkan' => 'bg-slate-50 text-slate-600 border-slate-200 dark:bg-slate-800/30 dark:text-slate-400 dark:border-slate-700/50',
        'pending' => $task->report_deferred
            ? 'bg-purple-50 text-purple-700 border-purple-200 dark:bg-purple-950/30 dark:text-purple-400 dark:border-purple-900/50'
            : 'bg-yellow-50 text-yellow-700 border-yellow-200 dark:bg-yellow-950/30 dark:text-yellow-400 dark:border-yellow-900/50',
        default => 'bg-slate-50 text-slate-500 border-slate-200 dark:bg-slate-800/30 dark:text-slate-500 dark:border-slate-700/50',
    };

    $lat = $task->customer?->customerAddress?->latitude ?? $task->pop?->latitude;
    $lng = $task->customer?->customerAddress?->longitude ?? $task->pop?->longitude;
    $phone = $task->customer?->primary_phone;
    $phoneWa = $phone ? preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $phone)) : null;
?>

<div id="task-card-<?php echo e($task->id); ?>"
     class="relative bg-surface border border-border rounded-2xl overflow-hidden pl-5 hover:border-slate-300 dark:hover:border-slate-700 hover:shadow-sm transition-all duration-200 <?php echo e($task->status->value === 'in_progress' ? 'ring-1 ring-amber-400 shadow-md shadow-amber-400/5' : ''); ?>"
     data-task-id="<?php echo e($task->id); ?>"
     data-task-type="<?php echo e($task->task_type->value); ?>"
     data-task-status="<?php echo e($task->status->value); ?>"
     data-customer-name="<?php echo e(strtolower($task->customer?->full_name ?? $task->title)); ?>"
     data-customer-address="<?php echo e(strtolower($task->customer?->clean_address ?? '')); ?>"
     data-task-number="<?php echo e(strtolower($task->task_number)); ?>"
     data-is-overdue="<?php echo e(($task->status->value === 'terjadwal' && $task->scheduled_at && $task->scheduled_at->isPast() && !$task->scheduled_at->isToday()) || $task->isOverSla() ? 'true' : 'false'); ?>"
     data-priority-weight="<?php echo e($task->fopTask?->priority?->sortOrder() ?? 5); ?>"
     data-scheduled-timestamp="<?php echo e($task->scheduled_at?->timestamp ?? 0); ?>">

    
    <div class="absolute left-0 top-0 bottom-0 w-1.5 <?php echo e($accentColor); ?>"></div>

    <div class="p-4 sm:p-5 flex flex-col justify-between h-full">
        <div>
            
            <div class="flex items-start justify-between gap-3 mb-3">
                <div class="flex items-center gap-1.5 flex-wrap">
                    <span class="text-[10px] font-bold uppercase tracking-wider px-2.5 py-0.5 rounded-md border <?php echo e($task->task_type->cardClasses()); ?>">
                        <?php echo e($task->task_type->label()); ?>

                    </span>
                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded-md border <?php echo e($statusClasses); ?>">
                        <?php echo e($task->status->displayLabel($task->report_deferred)); ?>

                    </span>
                    <?php if($task->isOverSla()): ?>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-md border bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/30 dark:text-rose-400 dark:border-rose-900/50 flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500 animate-pulse"></span>
                        Melewati SLA
                    </span>
                    <?php endif; ?>
                    <?php if($task->status->value === 'terjadwal' && $task->scheduled_at && $task->scheduled_at->isPast() && !$task->scheduled_at->isToday()): ?>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-md border bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/30 dark:text-rose-400 dark:border-rose-900/50 flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500 animate-pulse"></span>
                        Jadwal Terlewat
                    </span>
                    <?php endif; ?>
                </div>
                <span class="font-mono text-[10px] font-semibold text-text-muted shrink-0"><?php echo e($task->task_number); ?></span>
            </div>

            
            <h3 class="font-bold text-text-main text-base leading-snug group-hover:text-primary transition-colors">
                <?php echo e($task->customer?->full_name ?? $task->title); ?>

            </h3>

            
            <?php if($task->customer): ?>
            <p class="text-xs text-text-muted mt-1.5 flex items-start gap-1.5 leading-relaxed font-ui">
                <svg class="h-3.5 w-3.5 text-text-muted/70 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <span class="line-clamp-2">
                    <?php echo e($task->customer->clean_address ?? ''); ?>

                    <?php if($task->pop): ?>
                        <span class="text-text-muted/65">&mdash; <?php echo e($task->pop->name); ?></span>
                    <?php endif; ?>
                </span>
            </p>
            <?php endif; ?>

            
            <?php if($task->status->value !== 'terjadwal' && $lat && $lng): ?>
            <div class="mt-3 p-2.5 bg-surface-muted border border-border rounded-xl flex items-center justify-between gap-3" data-coordinate-card>
                <div class="flex flex-col gap-0.5 min-w-0">
                    <span class="text-[9px] font-semibold uppercase tracking-wider text-text-muted">Koordinat Lokasi</span>
                    <span class="font-mono text-[10px] text-text-secondary truncate">
                        <?php echo e($lat); ?>, <?php echo e($lng); ?>

                    </span>
                </div>
            </div>
            <?php endif; ?>

            
            <div class="flex items-center gap-1.5 mt-3 text-xs text-text-secondary font-medium">
                <svg class="h-4 w-4 shrink-0 text-text-muted/70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="font-mono font-semibold">
                    <?php echo e($task->scheduled_at?->isToday() ? $task->scheduled_at->format('H:i') : $task->scheduled_at?->translatedFormat('d M, H:i')); ?>

                </span>
                <span class="text-text-muted/80">&middot; SLA <?php echo e($task->sla_minutes); ?> menit</span>
            </div>

            
            <?php if($task->status->value === 'in_progress' && $task->started_at): ?>
                <?php
                    $slaDeadlineIso = $task->started_at->addMinutes($task->sla_minutes)->toIso8601String();
                ?>
                <div class="mt-3">
                    <?php if (isset($component)) { $__componentOriginalb8d3d89751f3d81017aa8a59bd985fb5 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb8d3d89751f3d81017aa8a59bd985fb5 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.countdown-timer','data' => ['deadline' => ''.e($slaDeadlineIso).'','totalSeconds' => $task->sla_minutes * 60,'label' => 'Sisa SLA']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('countdown-timer'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['deadline' => ''.e($slaDeadlineIso).'','total-seconds' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($task->sla_minutes * 60),'label' => 'Sisa SLA']); ?>
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
                </div>
            <?php endif; ?>

            
            <?php if($task->status->value === 'selesai' && $task->started_at && $task->completed_at): ?>
                <?php
                    $actualMinutes = (int) $task->started_at->diffInMinutes($task->completed_at);
                    $actualHours = intdiv($actualMinutes, 60);
                    $actualRemMins = $actualMinutes % 60;
                    $durationLabel = $actualHours > 0 ? "{$actualHours} jam {$actualRemMins} menit" : "{$actualRemMins} menit";
                    $isOverSla = $actualMinutes > $task->sla_minutes;
                    $typeLabel = $task->task_type->value === 'PSB' ? 'Pemasangan' : 'Survey';
                ?>
                <div class="mt-3 flex items-center gap-1.5 flex-wrap">
                    <svg class="h-3.5 w-3.5 shrink-0 text-text-muted/70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="text-[11px] font-medium text-text-secondary font-ui">Waktu <?php echo e($typeLabel); ?>:</span>
                    <span class="text-[11px] font-mono font-semibold text-text-main">
                        <?php echo e($task->started_at->format('H:i')); ?> &ndash; <?php echo e($task->completed_at->format('H:i')); ?>

                    </span>
                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded-lg border font-ui <?php echo e($isOverSla ? 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-950/30 dark:text-rose-400 dark:border-rose-900/50' : 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/30 dark:text-emerald-400 dark:border-emerald-900/50'); ?>">
                        <?php echo e($durationLabel); ?>

                    </span>
                </div>
            <?php endif; ?>
        </div>

        
        <div class="flex items-center justify-between gap-2 mt-4 pt-3.5 border-t border-border">
            
            <div class="flex items-center gap-1.5 shrink-0">
                <?php if($phoneWa): ?>
                <a href="https://wa.me/<?php echo e($phoneWa); ?>" 
                   target="_blank" 
                   title="Hubungi WhatsApp"
                   class="p-2.5 rounded-xl border border-border bg-surface text-text-secondary hover:bg-emerald-50 dark:hover:bg-emerald-950/30 hover:text-emerald-600 dark:hover:text-emerald-400 hover:border-emerald-200 dark:hover:border-emerald-900/50 active:scale-95 transition-all shadow-sm cursor-pointer">
                    <svg class="h-4.5 w-4.5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.768-.001 1.298.409 2.522 1.189 3.518l-.756 2.766 2.831-.744a5.748 5.748 0 002.504.588h.002c3.18 0 5.767-2.586 5.768-5.766 0-1.541-.6-2.99-1.691-4.08-1.091-1.09-2.539-1.69-4.079-1.648zm0 10.153a4.398 4.398 0 01-2.241-.614l-.16-.095-1.666.438.444-1.624-.105-.167a4.394 4.394 0 01-.67-2.326c.001-2.426 1.975-4.4 4.402-4.4 1.177 0 2.283.458 3.115 1.29a4.382 4.382 0 011.29 3.117c-.001 2.426-1.975-4.4-4.409 4.4z"/>
                    </svg>
                </a>
                <?php endif; ?>

                <?php if($task->status->value !== 'terjadwal' && $lat && $lng): ?>
                <a href="https://www.google.com/maps/search/?api=1&query=<?php echo e($lat); ?>,<?php echo e($lng); ?>" 
                   target="_blank" 
                   title="Petunjuk Arah Maps"
                   class="p-2.5 rounded-xl border border-border bg-surface text-text-secondary hover:bg-sky-50 dark:hover:bg-sky-950/30 hover:text-sky-600 dark:hover:text-sky-400 hover:border-sky-200 dark:hover:border-sky-900/50 active:scale-95 transition-all shadow-sm cursor-pointer">
                    <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </a>
                <?php endif; ?>
            </div>

            
            <div class="flex items-center gap-2 flex-1 justify-end min-w-0">
                <?php if($task->status->value !== 'terjadwal'): ?>
                <a href="<?php echo e(route('tasks.show', $task)); ?>"
                   class="inline-flex items-center justify-center text-xs font-bold py-2.5 px-3.5 border border-border rounded-xl bg-surface hover:bg-surface-muted text-text-secondary hover:text-text-main transition-all duration-150 shadow-sm cursor-pointer whitespace-nowrap active:scale-95 font-ui">
                    Buka Detail
                </a>
                <?php endif; ?>

                <?php if($task->status->value === 'terjadwal'): ?>
                    <?php if($task->task_type->value === 'SURVEY'): ?>
                        <?php if($task->customer_id && auth()->user()->hasPermission('customers.detail.survey.update') && $task->teamMembers->pluck('user_id')->contains(auth()->id())): ?>
                        <form action="<?php echo e(route('customers.survey.start', $task->customer_id)); ?>" method="POST" class="flex-1 max-w-[200px]">
                            <?php echo csrf_field(); ?>
                            <button type="submit"
                                    class="w-full text-center text-xs font-bold py-2.5 px-4 rounded-xl text-white bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 active:scale-[0.98] transition-all shadow-md shadow-amber-500/10 cursor-pointer whitespace-nowrap font-ui">
                                Mulai Survey
                            </button>
                        </form>
                        <?php endif; ?>
                    <?php elseif($task->task_type->value === 'PSB'): ?>
                        <?php if($task->customer_id && auth()->user()->hasPermission('customers.detail.installation.update') && $task->teamMembers->pluck('user_id')->contains(auth()->id())): ?>
                        <form action="<?php echo e(route('customers.installation.start', $task->customer_id)); ?>" method="POST" class="flex-1 max-w-[200px]">
                            <?php echo csrf_field(); ?>
                            <button type="submit"
                                    class="w-full text-center text-xs font-bold py-2.5 px-4 rounded-xl text-white bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 active:scale-[0.98] transition-all shadow-md shadow-amber-500/10 cursor-pointer whitespace-nowrap font-ui">
                                Mulai Pemasangan
                            </button>
                        </form>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('statusStart', $task)): ?>
                        <form action="<?php echo e(route('tasks.start', $task)); ?>" method="POST" class="flex-1 max-w-[200px]">
                            <?php echo csrf_field(); ?>
                            <button type="submit"
                                    class="w-full text-center text-xs font-bold py-2.5 px-4 rounded-xl text-white bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 active:scale-[0.98] transition-all shadow-md shadow-amber-500/10 cursor-pointer whitespace-nowrap font-ui">
                                <?php echo e($task->task_type->value === \App\Enums\TaskType::MAINTENANCE->value && !request()->routeIs('tasks.own.card-partial') ? 'Mulai Maintenance' : 'Mulai Task'); ?>

                            </button>
                        </form>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('statusComplete', $task)): ?>
                <?php if(in_array($task->status->value, ['in_progress', 'pending'])): ?>
                    <?php
                        $reportUrl = match(true) {
                            $task->task_type->value === 'SURVEY' => route('customers.survey.report', ['customer' => $task->customer_id, 'return_to' => route('tasks.own')]),
                            $task->task_type->value === 'PSB' => route('customers.installation.report', ['customer' => $task->customer_id, 'return_to' => route('tasks.own')]),
                            default => route('tasks.maintenance.report', $task),
                        };
                    ?>
                    <?php if($task->status->value === 'in_progress'): ?>
                        <?php if (isset($component)) { $__componentOriginalb21bb3349113a9ccaaa78d0a239117e9 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb21bb3349113a9ccaaa78d0a239117e9 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.task.report-choice-dialog','data' => ['task' => $task,'reportUrl' => $reportUrl,'class' => 'inline-flex items-center justify-center text-center text-xs font-bold py-2.5 px-4 rounded-xl text-white bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 active:scale-[0.98] transition-all shadow-md shadow-emerald-500/10 cursor-pointer whitespace-nowrap font-ui']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('task.report-choice-dialog'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['task' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($task),'report-url' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($reportUrl),'class' => 'inline-flex items-center justify-center text-center text-xs font-bold py-2.5 px-4 rounded-xl text-white bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 active:scale-[0.98] transition-all shadow-md shadow-emerald-500/10 cursor-pointer whitespace-nowrap font-ui']); ?>
                            Isi Laporan
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
                           class="inline-flex items-center justify-center text-center text-xs font-bold py-2.5 px-4 rounded-xl text-white bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 active:scale-[0.98] transition-all shadow-md shadow-emerald-500/10 cursor-pointer whitespace-nowrap font-ui">
                            Lanjutkan Laporan
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/tasks/partials/own-card.blade.php ENDPATH**/ ?>