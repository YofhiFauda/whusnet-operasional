
<?php
    $barColor = match(true) {
        $task->status->value === 'terjadwal'   => 'var(--color-info)',
        $task->status->value === 'in_progress' => 'var(--color-warning)',
        $task->status->value === 'selesai'     => 'var(--color-success)',
        $task->status->value === 'dibatalkan'  => 'var(--color-error)',
        $task->status->value === 'pending' && $task->report_deferred => '#7c3aed',
        $task->status->value === 'pending'     => '#a16207',
        default       => 'var(--color-border)',
    };
    $statusStyle = match(true) {
        $task->status->value === 'terjadwal'   => 'background:var(--color-info-bg); color:var(--color-info); border-color:var(--color-info-border)',
        $task->status->value === 'in_progress' => 'background:var(--color-warning-bg); color:var(--color-warning); border-color:var(--color-warning-border)',
        $task->status->value === 'selesai'     => 'background:var(--color-success-bg); color:var(--color-success); border-color:var(--color-success-border)',
        $task->status->value === 'dibatalkan'  => 'background:var(--color-error-bg); color:var(--color-error); border-color:var(--color-error-border)',
        $task->status->value === 'pending' && $task->report_deferred => 'background:#f5f3ff; color:#6d28d9; border-color:#c4b5fd',
        $task->status->value === 'pending'     => 'background:#fefce8; color:#a16207; border-color:#fde68a',
        default       => 'background:var(--color-surface-muted); color:var(--color-text-muted); border-color:var(--color-border)',
    };
?>

<div id="task-card-<?php echo e($task->id); ?>"
     class="bg-surface border border-border rounded-lg overflow-hidden
            <?php echo e($task->status->value === 'in_progress' ? 'ring-2 ring-amber-400' : ''); ?>"
     data-task-id="<?php echo e($task->id); ?>">

    
    <div class="h-1 w-full" style="background: <?php echo e($barColor); ?>"></div>

    <div class="px-4 py-4">

        
        <div class="flex items-start justify-between gap-3 mb-3">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full border <?php echo e($task->task_type->cardClasses()); ?>">
                    <?php echo e($task->task_type->label()); ?>

                </span>
                <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full border" style="<?php echo e($statusStyle); ?>">
                    <?php echo e($task->status->displayLabel($task->report_deferred)); ?>

                </span>
                <?php if($task->isOverSla()): ?>
                <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full border"
                      style="background:var(--color-error-bg); color:var(--color-error); border-color:var(--color-error-border)">
                    Melewati SLA
                </span>
                <?php endif; ?>
            </div>
            <span class="font-mono text-[11px] text-text-muted shrink-0"><?php echo e($task->task_number); ?></span>
        </div>

        
        <p class="font-semibold text-text-main"><?php echo e($task->customer?->full_name ?? $task->title); ?></p>
        <?php if($task->customer): ?>
        <p class="text-xs text-text-muted mt-0.5">
            <?php echo e($task->customer->clean_address ?? ''); ?>

            <?php if($task->pop): ?>&mdash; <?php echo e($task->pop->name); ?><?php endif; ?>
        </p>
        <?php endif; ?>

        
        <?php if($task->status->value !== 'terjadwal'): ?>
        <?php
            $lat = $task->customer?->customerAddress?->latitude ?? $task->pop?->latitude;
            $lng = $task->customer?->customerAddress?->longitude ?? $task->pop?->longitude;
        ?>
        <?php if($lat && $lng): ?>
        <div class="mt-2.5 p-2 bg-surface-muted border border-border rounded-md flex items-center justify-between gap-3" data-coordinate-card>
            <div class="flex flex-col gap-0.5 min-w-0">
                <span class="text-[9px] font-semibold uppercase tracking-wider text-text-muted">Koordinat Lokasi</span>
                <span class="font-mono text-[10px] text-text-secondary truncate">
                    <?php echo e($lat); ?>, <?php echo e($lng); ?>

                </span>
            </div>
            <a href="https://www.google.com/maps/search/?api=1&query=<?php echo e($lat); ?>,<?php echo e($lng); ?>" 
               target="_blank"
               class="shrink-0 inline-flex items-center gap-1 text-[11px] font-semibold px-2 py-1 border border-border rounded bg-surface hover:bg-surface-muted text-primary transition-colors cursor-pointer"
               data-map-button>
                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                Maps
            </a>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        
        <div class="flex items-center gap-1.5 mt-2 text-xs text-text-secondary">
            <svg class="h-3.5 w-3.5 shrink-0 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span class="font-mono font-semibold"><?php echo e($task->scheduled_at?->format('H:i')); ?></span>
            <span class="text-text-muted">· SLA <?php echo e($task->sla_minutes); ?> menit</span>
        </div>

        
        <div class="flex items-center gap-2 mt-4 pt-3 border-t border-border">
            
            <?php if($task->status->value !== 'terjadwal'): ?>
            <a href="<?php echo e(route('tasks.show', $task)); ?>"
               class="flex-1 text-center text-xs font-semibold py-2 px-3 border border-border rounded-md bg-background hover:bg-surface-muted text-text-secondary transition-colors">
                Buka Detail
            </a>
            <?php endif; ?>

            <?php if(in_array($task->status->value, ['in_progress', 'pending'])): ?>
                <?php
                    $reportUrl = match(true) {
                        $task->task_type->value === 'SURVEY' => route('customers.survey.report', $task->customer_id),
                        $task->task_type->value === 'PSB' => route('customers.installation.report', $task->customer_id),
                        default => route('tasks.maintenance.report', $task),
                    };
                ?>
                <?php if($task->status->value === 'in_progress'): ?>
                    <?php if (isset($component)) { $__componentOriginalb21bb3349113a9ccaaa78d0a239117e9 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb21bb3349113a9ccaaa78d0a239117e9 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.task.report-choice-dialog','data' => ['task' => $task,'reportUrl' => $reportUrl,'class' => 'flex-1 justify-center']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('task.report-choice-dialog'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['task' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($task),'report-url' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($reportUrl),'class' => 'flex-1 justify-center']); ?>
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
                       class="flex-1 text-center text-xs font-semibold py-2 px-3 rounded-md text-white transition-colors"
                       style="background:var(--color-success)">
                        Lanjutkan Laporan
                    </a>
                <?php endif; ?>
            <?php endif; ?>

            <?php if($task->status->value === 'terjadwal'): ?>
                <?php if($task->task_type->value === 'SURVEY'): ?>
                    <?php if($task->customer_id && auth()->user()->hasPermission('customers.detail.survey.update') && $task->teamMembers->pluck('user_id')->contains(auth()->id())): ?>
                    <form action="<?php echo e(route('customers.survey.start', $task->customer_id)); ?>" method="POST" class="flex-1">
                        <?php echo csrf_field(); ?>
                        <button type="submit"
                                class="w-full text-xs font-semibold py-2 px-3 rounded-md text-white transition-colors"
                                style="background:var(--color-warning)">
                            Mulai Survey
                        </button>
                    </form>
                    <?php endif; ?>
                <?php elseif($task->task_type->value === 'PSB'): ?>
                    <?php if($task->customer_id && auth()->user()->hasPermission('customers.detail.installation.update') && $task->teamMembers->pluck('user_id')->contains(auth()->id())): ?>
                    <form action="<?php echo e(route('customers.installation.start', $task->customer_id)); ?>" method="POST" class="flex-1">
                        <?php echo csrf_field(); ?>
                        <button type="submit"
                                class="w-full text-xs font-semibold py-2 px-3 rounded-md text-white transition-colors"
                                style="background:var(--color-warning)">
                            Mulai Pemasangan
                        </button>
                    </form>
                    <?php endif; ?>
                <?php else: ?>
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('statusStart', $task)): ?>
                    <form action="<?php echo e(route('tasks.start', $task)); ?>" method="POST" class="flex-1">
                        <?php echo csrf_field(); ?>
                        <button type="submit"
                                class="w-full text-xs font-semibold py-2 px-3 rounded-md text-white transition-colors"
                                style="background:var(--color-warning)">
                            Mulai Task
                        </button>
                    </form>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>

    </div>
</div>
<?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/tasks/partials/own-card.blade.php ENDPATH**/ ?>