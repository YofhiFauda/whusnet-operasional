
<td class="px-3 py-2" id="tech-cell-<?php echo e($task->id); ?>">
    <div class="flex flex-wrap gap-1 items-start min-w-[150px]">
        <?php
            $visibleTechs = $task->technicians->take(2);
            $hiddenTechsCount = $task->technicians->count() - 2;
        ?>
        <?php $__empty_1 = true; $__currentLoopData = $visibleTechs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tech): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <?php
                // Ambil nama depan saja untuk menghemat ruang
                $firstName = explode(' ', trim($tech->name))[0];
            ?>
            <button type="button"
                @click="openSwitchModal(<?php echo e($task->id); ?>, '<?php echo e($task->task_number); ?>', <?php echo \Illuminate\Support\Js::from($task->tugas)->toHtml() ?>, '<?php echo e($task->task_date?->toDateString()); ?>', <?php echo e($tech->id); ?>, <?php echo \Illuminate\Support\Js::from($tech->name)->toHtml() ?>)"
                class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-blue-50 text-blue-700 border border-blue-100 hover:bg-blue-100 hover:border-blue-300 transition-colors"
                title="<?php echo e($tech->name); ?> — klik buat Switch Teknisi">
                <?php echo e(\Illuminate\Support\Str::limit($firstName, 12)); ?>

            </button>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <span class="text-slate-400 dark:text-slate-500 text-[10px] italic">Unassigned</span>
        <?php endif; ?>

        <?php if($hiddenTechsCount > 0): ?>
            <div class="relative" x-data="{ openHidden: false }">
                <button type="button" @click="openHidden = !openHidden"
                    class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-slate-100 dark:bg-slate-700/50 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700 hover:bg-slate-200 transition-colors">
                    +<?php echo e($hiddenTechsCount); ?>

                </button>
                <div x-show="openHidden" @click.away="openHidden = false"
                    class="absolute z-40 mt-1 min-w-[140px] bg-surface border border-border rounded shadow-lg py-1"
                    style="display: none;">
                    <?php $__currentLoopData = $task->technicians->skip(2); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tech): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <button type="button"
                            @click="openSwitchModal(<?php echo e($task->id); ?>, '<?php echo e($task->task_number); ?>', <?php echo \Illuminate\Support\Js::from($task->tugas)->toHtml() ?>, '<?php echo e($task->task_date?->toDateString()); ?>', <?php echo e($tech->id); ?>, <?php echo \Illuminate\Support\Js::from($tech->name)->toHtml() ?>); openHidden = false"
                            class="w-full text-left px-3 py-1.5 text-[11px] text-text-secondary hover:bg-surface-muted transition-colors">
                            <?php echo e($tech->name); ?>

                        </button>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</td>
<td class="px-3 py-2 whitespace-nowrap" id="team-cell-<?php echo e($task->id); ?>">
    <?php if($task->team): ?>
        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-sky-50 dark:bg-sky-900/20 text-sky-700 dark:text-sky-400 border border-sky-100 dark:border-sky-800/30">
            <?php echo e($task->team->name); ?>

        </span>
    <?php elseif($task->technicians->count() === 1): ?>
        <button type="button"
                @click="openTeamSelectionModal(<?php echo e($task->id); ?>, '<?php echo e($task->task_number); ?>', '<?php echo e(addslashes($task->tugas)); ?>', '<?php echo e($task->task_date?->format('Y-m-d')); ?>')"
                class="text-[10px] text-blue-600 hover:text-blue-800 font-medium underline decoration-dotted">
            + Masukkan ke Team...
        </button>
    <?php else: ?>
        <?php
            $taskDate = $task->task_date?->toDateString();
            $techIds = $task->technicians->pluck('id')->all();
            $candidates = \App\Models\FopTaskTeam::whereDate('work_date', $taskDate)
                ->whereHas('members', fn($q) => $q->whereIn('users.id', $techIds))
                ->get()
                ->map(fn($t) => ['team_id' => $t->id, 'team_name' => $t->name])
                ->all();
        ?>
        <?php if(count($candidates) >= 2): ?>
            <button type="button"
                    @click="triggerConflictModal(<?php echo e($task->id); ?>, '<?php echo e($task->task_number); ?>', <?php echo e(json_encode($candidates)); ?>)"
                    class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-medium bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 border border-red-100 dark:border-red-800/30 hover:bg-red-100 transition-colors"
                    title="Klik untuk memilih team">
                ⚠️ Konflik Roster
            </button>
        <?php else: ?>
            <span class="text-slate-300 text-[10px]">—</span>
        <?php endif; ?>
    <?php endif; ?>
</td>
<td class="px-3 py-2 whitespace-nowrap" id="status-cell-<?php echo e($task->id); ?>">
    <?php
        // FopTask.status share vocab persis sama TaskStatus (unifikasi
        // 2026-07-20) — kalau udah ada Task eksekusi terhubung, pakai label/
        // badge dari situ (bawa nuansa report_deferred). Kalau belum (FopTask
        // standalone, task_id null, masih 'draft' — belum ada teknisi
        // di-assign), pakai punya FopTask sendiri, dikasih label khusus biar
        // gak nyesatin ("draft" doang kurang jelas buat FOP).
        $statusValue = $task->status->value;
        $statusLabel = $task->task
            ? $task->task->status->displayLabel($task->task->report_deferred)
            : ($statusValue === 'draft' ? 'Belum Ditugaskan' : $task->status->displayLabel());
        $statusClasses = $task->task
            ? $task->task->status->displayBadgeClasses($task->task->report_deferred)
            : ($statusValue === 'draft' ? 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 bg-slate-50 dark:bg-slate-800/50' : $task->status->displayBadgeClasses());
    ?>
    <div class="flex flex-col gap-1 items-start">
        <span class="inline-flex items-center px-2 py-1 rounded text-[11px] font-medium border w-fit <?php echo e($statusClasses); ?>"
              title="Status realtime — derived otomatis dari status Task teknisi, gak bisa diedit manual">
            <?php echo e($statusLabel); ?>

        </span>
        <div class="flex flex-col gap-0.5 mt-0.5">
            
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('fop_tasks.cancel')): ?>
                <?php if(!in_array($statusValue, ['selesai', 'dibatalkan']) && !in_array($task->category->value, ['SURVEY', 'PSB'])): ?>
                    <button type="button"
                            @click="openCancelModal(<?php echo e($task->id); ?>, '<?php echo e($task->task_number); ?>')"
                            class="text-[10px] text-red-600 dark:text-red-400 underline decoration-dotted text-left cursor-pointer">
                        Cancel
                    </button>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</td>
<?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/fop_tasks/partials/row-cells.blade.php ENDPATH**/ ?>