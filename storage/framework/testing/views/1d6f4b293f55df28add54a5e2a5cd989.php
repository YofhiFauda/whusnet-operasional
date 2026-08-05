<?php $__env->startSection('title', 'Task Saya'); ?>

<?php $__env->startSection('content'); ?>
<div x-data="{}" class="max-w-2xl mx-auto px-4 py-6 space-y-5">

    
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <svg class="h-5 w-5 text-primary shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
            </svg>
            <div>
                <h1 class="text-base font-semibold text-text-main leading-tight">Task Saya Hari Ini</h1>
                <p class="text-xs text-text-muted">
                    Halo, <?php echo e(auth()->user()->name); ?> 👋 &mdash; <?php echo e(now()->translatedFormat('l, d F Y')); ?>

                </p>
            </div>
        </div>
        <div class="text-right">
            <p class="text-2xl font-bold font-mono text-text-main leading-none"><?php echo e($tasks->count()); ?></p>
            <p class="text-[11px] text-text-muted">task hari ini</p>
        </div>
    </div>

    
    
    <div x-data="technicianNotifier()"
         x-show="banner.visible"
         x-cloak
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 -translate-y-3"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-3"
         class="flex items-start gap-3 px-4 py-3 rounded-lg border shadow-md cursor-pointer"
         style="background:var(--color-primary-soft,#eff6ff); border-color:var(--color-primary-border,#93c5fd); color:var(--color-primary,#2563eb)"
         @click="scrollToCard()"
         id="task-notification-banner"
         role="alert"
         aria-live="polite">

        
        <svg class="h-5 w-5 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
        </svg>

        <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold leading-tight" x-text="banner.title"></p>
            <p class="text-xs mt-0.5 opacity-80" x-text="banner.subtitle"></p>
            <p class="text-[11px] mt-1 opacity-60">Klik banner ini untuk melihat task baru &darr;</p>
        </div>

        
        <button @click.stop="dismissBanner()"
                class="shrink-0 p-1 rounded hover:opacity-70 transition-opacity"
                title="Tutup notifikasi"
                aria-label="Tutup notifikasi">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    

    
    <?php if($tasks->count() > 0): ?>
    <div class="space-y-3" id="today-task-list">
        <?php $__currentLoopData = $tasks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $task): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="bg-surface border border-border rounded-lg overflow-hidden
            <?php if($task->status->value === 'in_progress'): ?> ring-2 ring-amber-400 <?php endif; ?>">

            
            <?php
                $barColor = match($task->status->value) {
                    'terjadwal'   => 'var(--color-info)',
                    'in_progress' => 'var(--color-warning)',
                    'selesai'     => 'var(--color-success)',
                    'dibatalkan'  => 'var(--color-error)',
                    default       => 'var(--color-border)',
                };
            ?>
            <div class="h-1 w-full" style="background: <?php echo e($barColor); ?>"></div>

            <div class="px-4 py-4">

                
                <div class="flex items-start justify-between gap-3 mb-3">
                    <div class="flex items-center gap-2 flex-wrap">
                        
                        <span class="text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full border <?php echo e($task->task_type->cardClasses()); ?>">
                            <?php echo e($task->task_type->label()); ?>

                        </span>
                        
                        <?php
                            $statusStyle = match(true) {
                                $task->status->value === 'terjadwal'   => 'background:var(--color-info-bg); color:var(--color-info); border-color:var(--color-info-border)',
                                $task->status->value === 'in_progress' => 'background:var(--color-warning-bg); color:var(--color-warning); border-color:var(--color-warning-border)',
                                $task->status->value === 'selesai'     => 'background:var(--color-success-bg); color:var(--color-success); border-color:var(--color-success-border)',
                                $task->status->value === 'dibatalkan'  => 'background:var(--color-error-bg); color:var(--color-error); border-color:var(--color-error-border)',
                                $task->status->value === 'pending' && $task->report_deferred => 'background:#f5f3ff; color:#6d28d9; border-color:#c4b5fd',
                                $task->status->value === 'pending'     => 'background:#fefce8; color:#a16207; border-color:#fde68a',
                                default                                => 'background:var(--color-surface-muted); color:var(--color-text-muted); border-color:var(--color-border)',
                            };
                        ?>
                        <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full border" style="<?php echo e($statusStyle); ?>">
                            <?php echo e($task->status->displayLabel($task->report_deferred)); ?>

                        </span>
                        <?php if($task->isOverSla()): ?>
                        <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full border"
                              style="background:var(--color-error-bg); color:var(--color-error); border-color:var(--color-error-border)">
                            Melewati SLA
                        </span>
                        <?php endif; ?>
                        <?php if($task->status->value === 'terjadwal' && $task->scheduled_at && $task->scheduled_at->isPast() && !$task->scheduled_at->isToday()): ?>
                        <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full border"
                              style="background:var(--color-error-bg); color:var(--color-error); border-color:var(--color-error-border)">
                            Jadwal Terlewat
                        </span>
                        <?php endif; ?>
                    </div>
                    <span class="font-mono text-[11px] text-text-muted shrink-0"><?php echo e($task->task_number); ?></span>
                </div>

                
                <p class="font-semibold text-text-main"><?php echo e($task->customer?->full_name ?? $task->title); ?></p>
                <?php if($task->customer): ?>
                <p class="text-xs text-text-muted mt-0.5">
                    <?php echo e($task->customer->clean_address ?? ''); ?>

                    <?php if($task->pop): ?>
                        &mdash; <?php echo e($task->pop->name); ?>

                    <?php endif; ?>
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
                    <span class="font-mono font-semibold">
                        <?php echo e($task->scheduled_at?->isToday() ? $task->scheduled_at->format('H:i') : $task->scheduled_at?->translatedFormat('d M, H:i')); ?>

                    </span>
                    <span class="text-text-muted">· SLA <?php echo e($task->sla_minutes); ?> menit</span>
                </div>

                
                <?php if($task->status->value === 'in_progress' && $task->started_at): ?>
                <?php
                    $slaDeadlineIso = $task->started_at
                        ->addMinutes($task->sla_minutes)
                        ->toIso8601String();
                ?>
                <div class="mt-2">
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
                    $taskStartedAt   = $task->started_at;
                    $taskCompletedAt = $task->completed_at;
                    $actualMinutes   = (int) $taskStartedAt->diffInMinutes($taskCompletedAt);
                    $actualHours     = intdiv($actualMinutes, 60);
                    $actualRemMins   = $actualMinutes % 60;
                    $durationLabel   = $actualHours > 0
                        ? "{$actualHours} jam {$actualRemMins} menit"
                        : "{$actualRemMins} menit";
                    $isOverSla       = $actualMinutes > $task->sla_minutes;
                    $typeLabel       = $task->task_type->value === 'PSB' ? 'Pemasangan' : 'Survey';
                ?>
                <div class="mt-2 flex items-center gap-1.5">
                    <svg class="h-3 w-3 shrink-0 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="text-[11px] font-medium text-text-secondary">
                        Waktu <?php echo e($typeLabel); ?>:
                    </span>
                    <span class="text-[11px] font-mono font-semibold text-text-main">
                        <?php echo e($taskStartedAt->format('H:i')); ?> – <?php echo e($taskCompletedAt->format('H:i')); ?>

                    </span>
                    <span class="text-[11px] font-semibold px-1.5 py-0.5 rounded"
                          style="<?php echo e($isOverSla
                              ? 'background:var(--color-error-bg); color:var(--color-error)'
                              : 'background:var(--color-success-bg); color:var(--color-success)'); ?>">
                        <?php echo e($durationLabel); ?>

                    </span>
                </div>
                <?php endif; ?>

                
                <div class="flex items-center gap-2 mt-4 pt-3 border-t border-border">
                    
                    <?php if($task->status->value !== 'terjadwal'): ?>
                    <a href="<?php echo e(route('tasks.show', $task)); ?>"
                       class="flex-1 text-center text-xs font-semibold py-2 px-3 border border-border rounded-md bg-background hover:bg-surface-muted text-text-secondary transition-colors">
                        Buka Detail
                    </a>
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
                                    <?php echo e($task->task_type->value === \App\Enums\TaskType::MAINTENANCE->value ? 'Mulai Maintenance' : 'Mulai Task'); ?>

                                </button>
                            </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('statusComplete', $task)): ?>
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
                    <?php endif; ?>
                </div>

            </div>
        </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
    <?php else: ?>
    <div class="bg-surface border border-border rounded-lg flex flex-col items-center justify-center py-16 gap-3"
         data-empty-tasks>
        <svg class="h-10 w-10 text-text-muted opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
        </svg>
        <p class="text-sm text-text-muted">Tidak ada task untuk hari ini.</p>
        <p class="text-xs text-text-muted">Hubungi FOP jika ada penugasan yang belum muncul.</p>
    </div>
    <?php endif; ?>

    
    <?php if($upcomingTasks->count() > 0): ?>
    <div data-section="mendatang">
        <p class="text-[11px] font-semibold uppercase tracking-widest text-text-muted mb-3">Jadwal Mendatang</p>
        <div class="space-y-2">
            <?php $__currentLoopData = $upcomingTasks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $task): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <a href="<?php echo e(route('tasks.show', $task)); ?>"
               class="flex items-center justify-between bg-surface border border-border rounded-lg px-4 py-3 hover:bg-surface-muted transition-colors">
                <div class="flex items-center gap-3">
                    <span class="text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full border <?php echo e($task->task_type->cardClasses()); ?>">
                        <?php echo e($task->task_type->label()); ?>

                    </span>
                    <div>
                        <p class="text-sm font-medium text-text-main"><?php echo e($task->customer?->full_name ?? $task->title); ?></p>
                        <p class="text-[11px] text-text-muted">
                            <?php echo e($task->scheduled_at?->translatedFormat('l, d M')); ?> &middot;
                            <?php echo e($task->scheduled_at?->format('H:i')); ?>

                        </p>
                    </div>
                </div>
                <svg class="h-4 w-4 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                </svg>
            </a>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php $__env->startPush('scripts'); ?>
<script>
/**
 * technicianNotifier — Alpine.js component (S8.2-T010)
 * ─────────────────────────────────────────────────────
 * Listen ke Laravel Reverb channel private-teknisi.{userId} untuk event TaskScheduled.
 * Saat event diterima:
 *   1. Tampilkan banner notifikasi di atas list task.
 *   2. event_type 'created'     → fetch card parsial dari /tasks-saya/partial/{taskId}, inject ke DOM.
 *      event_type 'rescheduled' → fetch ulang & ganti card yang udah ada di tempat (jadwal lama jangan
 *      nyangkut di layar) — kalau card belum ada di DOM, treat sama kayak 'created'.
 *   3. Klik banner → smooth-scroll ke card.
 *   4. Auto-dismiss banner setelah 10 detik.
 */
function technicianNotifier() {
    return {
        banner: {
            visible: false,
            title: '',
            subtitle: '',
            taskId: null,
        },
        dismissTimer: null,

        init() {
            const userId = <?php echo e(auth()->id()); ?>;

            // Retry loop: tunggu window.Echo tersedia (race condition antara
            // Alpine mount dan Vite bundle selesai load echo.js).
            // Retry maksimal 10x dengan interval 300ms (total ~3 detik).
            let attempts = 0;
            const maxAttempts = 10;

            const attach = () => {
                if (typeof window.Echo !== 'undefined') {
                    window.Echo.private(`teknisi.${userId}`)
                        .listen('TaskScheduled', (event) => {
                            this.handleTaskScheduled(event);
                        });
                    return;
                }

                attempts++;
                if (attempts < maxAttempts) {
                    setTimeout(attach, 300);
                } else {
                    console.warn('[technicianNotifier] window.Echo tidak tersedia setelah 3 detik. Notifikasi real-time tidak aktif.');
                }
            };

            attach();
        },

        handleTaskScheduled(event) {
            // Hitung jadwal yang tampil di banner
            let jadwalLabel = '';
            if (event.scheduled_at) {
                const dt = new Date(event.scheduled_at);
                // Format: YYYY-MM-DD HH:mm (sederhana, tanpa dependency locale)
                const pad = (n) => String(n).padStart(2, '0');
                jadwalLabel = `${dt.getFullYear()}-${pad(dt.getMonth()+1)}-${pad(dt.getDate())} ${pad(dt.getHours())}:${pad(dt.getMinutes())}`;
            }

            // Teks banner berbeda berdasarkan konteks event
            const isRescheduled = event.event_type === 'rescheduled';
            this.banner.title    = isRescheduled
                ? `Jadwal diperbarui: ${event.title}`
                : `Task baru ditugaskan: ${event.title}`;
            this.banner.subtitle = jadwalLabel ? `Jadwal: ${jadwalLabel}` : '';
            this.banner.taskId   = event.id;
            this.banner.visible  = true;

            // Auto-dismiss setelah 10 detik
            clearTimeout(this.dismissTimer);
            this.dismissTimer = setTimeout(() => this.dismissBanner(), 10000);

            // 'created' → card belum ada, inject baru. 'rescheduled' → card
            // biasanya udah ada di DOM tapi jadwalnya basi, refetch & ganti di
            // tempat (refreshTaskCard nangani juga kalau ternyata belum ada).
            if (isRescheduled) {
                this.refreshTaskCard(event.id);
            } else {
                this.injectTaskCard(event.id);
            }
        },

        dismissBanner() {
            this.banner.visible = false;
            clearTimeout(this.dismissTimer);
        },

        scrollToCard() {
            if (!this.banner.taskId) return;
            const card = document.getElementById(`task-card-${this.banner.taskId}`);
            if (card) {
                card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                // Highlight card sebentar
                card.style.transition = 'box-shadow 0.3s';
                card.style.boxShadow = '0 0 0 3px var(--color-primary, #2563eb)';
                setTimeout(() => { card.style.boxShadow = ''; }, 2000);
            }
            this.dismissBanner();
        },

        async injectTaskCard(taskId) {
            // Cek apakah card sudah ada (mencegah duplikasi jika event diterima dua kali)
            if (document.getElementById(`task-card-${taskId}`)) return;

            const freshCard = await this.fetchCard(taskId);
            if (!freshCard) return;

            // Dapatkan container task list hari ini
            let container = document.getElementById('today-task-list');

            if (!container) {
                // Jika container belum ada (halaman kosong / tidak ada task hari ini),
                // cari parent wrapper dan buat container baru
                const emptyState = document.querySelector('[data-empty-tasks]');
                if (emptyState) {
                    // Sembunyikan empty state
                    emptyState.style.display = 'none';
                }
                // Buat container baru dan inject sebelum section Mendatang atau di akhir content
                container = document.createElement('div');
                container.id = 'today-task-list';
                container.className = 'space-y-3';
                // Cari titik sisip di atas section Mendatang
                const mendatangSection = document.querySelector('[data-section="mendatang"]');
                const contentWrapper = document.querySelector('.max-w-2xl');
                if (mendatangSection && contentWrapper) {
                    contentWrapper.insertBefore(container, mendatangSection);
                } else if (contentWrapper) {
                    contentWrapper.appendChild(container);
                }
            }

            // Inject card di awal list (task baru tampil di atas)
            container.insertBefore(freshCard, container.firstChild);
            this.initAlpineOn(freshCard);
        },

        // Jadwal task berubah (event_type 'rescheduled') — card yang udah
        // kelihatan di layar masih nampilin jadwal lama, jadi diganti di
        // tempat pakai HTML terbaru dari server (satu sumber kebenaran,
        // gak ngoprek tampilan jadwal di JS). Kalau card belum ada di DOM
        // (task-nya baru masuk ke "hari ini" gara-gara reschedule), inject
        // baru — sama kayak event 'created'.
        async refreshTaskCard(taskId) {
            const existing = document.getElementById(`task-card-${taskId}`);

            if (!existing) {
                this.injectTaskCard(taskId);
                return;
            }

            const freshCard = await this.fetchCard(taskId);
            if (!freshCard) return;

            existing.replaceWith(freshCard);
            this.initAlpineOn(freshCard);
        },

        async fetchCard(taskId) {
            try {
                const res = await fetch(`/tasks-saya/partial/${taskId}`, {
                    headers: {
                        'Accept': 'text/html',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });

                if (!res.ok) {
                    console.warn(`[technicianNotifier] Gagal fetch card task #${taskId}: HTTP ${res.status}`);
                    return null;
                }

                const html = await res.text();
                const wrapper = document.createElement('div');
                wrapper.innerHTML = html.trim();
                return wrapper.firstElementChild;
            } catch (err) {
                console.error('[technicianNotifier] Error saat fetch task card:', err);
                return null;
            }
        },

        initAlpineOn(card) {
            // Card partial bisa berisi komponen Alpine (mis. dialog laporan) —
            // Alpine gak auto-scan DOM yang di-inject manual lewat innerHTML.
            if (card && window.Alpine && typeof window.Alpine.initTree === 'function') {
                window.Alpine.initTree(card);
            }
        },
    };
}
</script>
<?php $__env->stopPush(); ?>
<?php $__env->stopSection(); ?>


<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/tasks/own.blade.php ENDPATH**/ ?>