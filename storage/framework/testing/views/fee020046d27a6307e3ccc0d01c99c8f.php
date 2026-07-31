<div class="space-y-6">
    <!-- Header Summary -->
    <div class="border border-border rounded-xl p-5 bg-gradient-to-r from-surface-muted/30 via-surface to-surface-muted/30 shadow-sm flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-teal-100 text-teal-600 flex items-center justify-center font-bold text-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                </div>
                <h3 class="text-sm font-bold text-text-main">Riwayat Ticketing & Tugas Operasional</h3>
            </div>
            <p class="text-xs text-text-muted mt-1.5">
                Daftar seluruh tiket/tugas yang diturunkan untuk pelanggan ini. <strong>Tekan pada baris tiket</strong> untuk melihat riwayat lengkap progres dan penanganan tiket tersebut.
            </p>
        </div>

        <?php
            $totalTickets = $customerTasks->count() + $customerFopTasks->count();
            $completedTickets = $customerTasks->filter(fn($t) => ($t->status->value ?? $t->status) === 'selesai')->count() +
                                $customerFopTasks->filter(fn($t) => ($t->status->value ?? $t->status) === 'selesai')->count();
        ?>

        <div class="flex items-center gap-3 shrink-0">
            <div class="bg-surface border border-border rounded-lg px-3.5 py-2 text-center">
                <span class="block text-[10px] font-bold text-text-muted uppercase">Total Tiket</span>
                <span class="text-base font-extrabold text-text-main"><?php echo e($totalTickets); ?></span>
            </div>
            <div class="bg-emerald-50/10 border border-emerald-500/30 rounded-lg px-3.5 py-2 text-center">
                <span class="block text-[10px] font-bold text-emerald-500 uppercase">Selesai</span>
                <span class="text-base font-extrabold text-emerald-500"><?php echo e($completedTickets); ?></span>
            </div>
        </div>
    </div>

    <?php if($totalTickets === 0): ?>
        <div class="py-12 text-center text-text-muted border border-border rounded-xl bg-surface-muted/20">
            <svg class="mx-auto h-12 w-12 text-border mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122" />
            </svg>
            <h4 class="text-sm font-semibold text-text-main">Belum Ada Tiket yang Diturunkan</h4>
            <p class="text-xs text-text-muted mt-1 max-w-md mx-auto">
                Pelanggan ini belum memiliki riwayat tugas lapangan (Survey, Pemasangan, Maintenance, maupun Request).
            </p>
        </div>
    <?php else: ?>
        <div class="space-y-4">
            <!-- Central Tasks (Sprint 8) -->
            <?php $__currentLoopData = $customerTasks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $task): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php
                    $uniqueId = 'task_' . $task->id;
                    $typeLabel = $task->task_type instanceof \App\Enums\TaskType ? $task->task_type->label() : ($task->task_type ?? 'Tugas');
                    $typeBadge = $task->task_type instanceof \App\Enums\TaskType ? $task->task_type->badgeClasses() : 'bg-blue-50 text-blue-700 border-blue-200';
                    $statusLabel = $task->status instanceof \App\Enums\TaskStatus ? $task->status->label() : ($task->status ?? 'Pending');
                    $statusBadge = $task->status instanceof \App\Enums\TaskStatus ? $task->status->badgeClasses() : 'bg-slate-100 text-slate-700 border-slate-200';
                    
                    $assignedTeam = $task->teamMembers->map(function($m) {
                        return $m->user ? $m->user->name : null;
                    })->filter()->implode(', ');
                    if(empty($assignedTeam)) $assignedTeam = 'Belum ditugaskan';
                ?>

                <div class="border border-border rounded-xl bg-surface shadow-sm overflow-hidden transition-all hover:border-border/80">
                    <!-- Ticket Header / Clickable Bar -->
                    <div onclick="toggleTicketHistory('<?php echo e($uniqueId); ?>')" class="p-4 bg-surface hover:bg-surface-muted/50 transition-colors cursor-pointer flex flex-col md:flex-row md:items-center justify-between gap-3 select-none">
                        <div class="flex items-start md:items-center gap-3.5">
                            <!-- Icon & Number -->
                            <div class="w-10 h-10 rounded-lg bg-surface-muted border border-border flex flex-col items-center justify-center shrink-0 text-text-secondary font-mono text-[11px] font-bold">
                                <svg class="w-4 h-4 mb-0.5 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                            </div>

                            <div>
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-mono font-bold text-xs text-sky-700"><?php echo e($task->task_number); ?></span>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold border <?php echo e($typeBadge); ?>"><?php echo e(strtoupper($typeLabel)); ?></span>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-semibold border <?php echo e($statusBadge); ?>"><?php echo e($statusLabel); ?></span>
                                </div>
                                <h4 class="text-xs font-bold text-text-main mt-1"><?php echo e($task->title); ?></h4>
                                <?php if($task->description): ?>
                                    <p class="text-[11px] text-text-muted mt-0.5 line-clamp-1"><?php echo e($task->description); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="flex items-center justify-between md:justify-end gap-6 border-t md:border-t-0 pt-3 md:pt-0 border-border text-xs">
                            <div class="text-right">
                                <span class="block text-[10px] text-text-muted font-medium">Petugas Lapangan</span>
                                <span class="font-semibold text-text-secondary"><?php echo e($assignedTeam); ?></span>
                                <span class="block text-[10px] text-text-muted mt-0.5">
                                    <?php echo e($task->created_at ? \Carbon\Carbon::parse($task->created_at)->translatedFormat('d M Y') : '-'); ?>

                                </span>
                            </div>

                            <div class="flex items-center gap-1.5 text-primary font-bold text-xs shrink-0 bg-primary-soft px-3 py-2 rounded-lg group hover:bg-primary-soft/80 transition-colors">
                                <span>Lihat Riwayat</span>
                                <svg id="arrow_<?php echo e($uniqueId); ?>" class="w-4 h-4 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Expandable Detail & Timeline -->
                    <div id="<?php echo e($uniqueId); ?>" class="hidden border-t border-border bg-surface-muted/20 p-5 space-y-6">
                        <!-- Task Metadata Panel -->
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 bg-surface p-4 rounded-xl border border-border text-xs">
                            <div>
                                <span class="block text-[10px] font-bold text-text-muted uppercase">Dibuat Oleh</span>
                                <span class="font-bold text-text-main"><?php echo e($task->creator ? $task->creator->name : 'System'); ?></span>
                                <span class="block text-[10px] text-text-muted"><?php echo e($task->created_at ? \Carbon\Carbon::parse($task->created_at)->translatedFormat('d M Y, H:i') : '-'); ?> WIB</span>
                            </div>
                            <div>
                                <span class="block text-[10px] font-bold text-text-muted uppercase">Jadwal / Terjadwal</span>
                                <span class="font-bold text-text-main"><?php echo e($task->scheduled_at ? \Carbon\Carbon::parse($task->scheduled_at)->translatedFormat('d M Y, H:i') : 'Belum Dijadwalkan'); ?></span>
                            </div>
                            <div>
                                <span class="block text-[10px] font-bold text-text-muted uppercase">FOP Reviewer</span>
                                <span class="font-bold text-text-main"><?php echo e($task->fop ? $task->fop->name : '-'); ?></span>
                                <span class="block text-[10px] text-text-muted">Status: <?php echo e(strtoupper($task->fop_review_status ?? 'PENDING')); ?></span>
                            </div>
                            <div>
                                <span class="block text-[10px] font-bold text-text-muted uppercase">Target SLA</span>
                                <span class="font-bold text-text-main"><?php echo e($task->sla_minutes ? $task->sla_minutes . ' Menit' : '-'); ?></span>
                            </div>
                        </div>

                        <!-- Evidences -->
                        <?php if($task->evidences->count() > 0): ?>
                            <div class="grid grid-cols-1 gap-4">
                                <?php if($task->evidences->count() > 0): ?>
                                    <div class="bg-surface p-4 rounded-xl border border-border">
                                        <h5 class="text-xs font-bold text-text-main mb-2.5 flex items-center gap-1.5">
                                            <svg class="w-3.5 h-3.5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" /></svg>
                                            Bukti Foto Pekerjaan (Evidence)
                                        </h5>
                                        <div class="grid grid-cols-3 gap-2">
                                            <?php $__currentLoopData = $task->evidences; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ev): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <a href="<?php echo e(Storage::url($ev->file_path)); ?>" target="_blank" class="block aspect-square rounded-lg overflow-hidden border border-border bg-surface-muted hover:opacity-90">
                                                    <img src="<?php echo e(Storage::url($ev->file_path)); ?>" alt="Evidence" class="w-full h-full object-cover">
                                                </a>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Step-by-Step Ticket Audit History -->
                        <div class="bg-surface p-5 rounded-xl border border-border">
                            <h5 class="text-xs font-bold text-text-main uppercase tracking-wider mb-4 pb-2 border-b border-border flex items-center gap-2">
                                <svg class="w-4 h-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                Riwayat Log Penanganan Tiket Ini
                            </h5>

                            <?php if($task->auditLogs->count() > 0): ?>
                                <div class="relative pl-5 border-l-2 border-primary/20 space-y-4 ml-1">
                                    <?php $__currentLoopData = $task->auditLogs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $alog): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <div class="relative">
                                            <div class="absolute -left-[25px] top-1 w-4 h-4 rounded-full bg-primary border-2 border-surface shadow-sm"></div>
                                            <div class="text-xs">
                                                <div class="flex items-center justify-between gap-2">
                                                    <span class="font-bold text-text-main">
                                                        <?php echo e(match($alog->action) {
                                                            'create' => 'Tiket Dibuat',
                                                            'update' => 'Pembaruan Progres / Status Tiket',
                                                            'delete' => 'Tiket Dihapus',
                                                            default => ucfirst($alog->action)
                                                        }); ?>

                                                    </span>
                                                    <span class="text-[11px] text-text-muted font-mono">
                                                        <?php echo e($alog->created_at ? \Carbon\Carbon::parse($alog->created_at)->translatedFormat('d M Y, H:i:s') : '-'); ?>

                                                    </span>
                                                </div>
                                                <div class="text-text-muted mt-0.5 flex items-center gap-1.5">
                                                    <span>Oleh:</span>
                                                    <span class="font-semibold text-text-secondary"><?php echo e($alog->user ? $alog->user->name : 'System'); ?></span>
                                                </div>
                                                <?php if(!empty($alog->new_values)): ?>
                                                    <div class="mt-1.5 p-2 rounded bg-surface-muted border border-border font-mono text-[11px] text-text-secondary">
                                                        <?php $__currentLoopData = $alog->new_values; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                            <?php if(is_scalar($v)): ?>
                                                                <div><strong class="text-text-secondary"><?php echo e(ucwords(str_replace('_', ' ', $k))); ?>:</strong> <?php echo e($v); ?></div>
                                                            <?php endif; ?>
                                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </div>
                            <?php else: ?>
                                <!-- Fallback Milestones if no individual auditLogs exist yet -->
                                <div class="relative pl-5 border-l-2 border-border space-y-4 ml-1">
                                    <?php if($task->created_at): ?>
                                    <div class="relative">
                                        <div class="absolute -left-[25px] top-1 w-4 h-4 rounded-full bg-primary border-2 border-surface"></div>
                                        <div class="text-xs">
                                            <span class="font-bold text-text-main">Tiket Diterbitkan</span>
                                            <p class="text-text-muted">Dibuat oleh <?php echo e($task->creator ? $task->creator->name : 'System'); ?> pada <?php echo e(\Carbon\Carbon::parse($task->created_at)->translatedFormat('d M Y, H:i')); ?> WIB</p>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <?php if($task->scheduled_at): ?>
                                    <div class="relative">
                                        <div class="absolute -left-[25px] top-1 w-4 h-4 rounded-full bg-blue-500 border-2 border-surface"></div>
                                        <div class="text-xs">
                                            <span class="font-bold text-text-main">Dijadwalkan untuk Pengerjaan</span>
                                            <p class="text-text-muted">Jadwal: <?php echo e(\Carbon\Carbon::parse($task->scheduled_at)->translatedFormat('d M Y, H:i')); ?> WIB</p>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <?php if($task->started_at): ?>
                                    <div class="relative">
                                        <div class="absolute -left-[25px] top-1 w-4 h-4 rounded-full bg-amber-500 border-2 border-surface"></div>
                                        <div class="text-xs">
                                            <span class="font-bold text-text-main">Pengerjaan Dimulai (In Progress)</span>
                                            <p class="text-text-muted">Mulai dikerjakan pada <?php echo e(\Carbon\Carbon::parse($task->started_at)->translatedFormat('d M Y, H:i')); ?> WIB</p>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <?php if($task->completed_at): ?>
                                    <div class="relative">
                                        <div class="absolute -left-[25px] top-1 w-4 h-4 rounded-full bg-emerald-600 border-2 border-surface"></div>
                                        <div class="text-xs">
                                            <span class="font-bold text-emerald-500">Tiket Selesai Dikerjakan</span>
                                            <p class="text-text-muted">Selesai pada <?php echo e(\Carbon\Carbon::parse($task->completed_at)->translatedFormat('d M Y, H:i')); ?> WIB</p>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <?php if($task->cancelled_at): ?>
                                    <div class="relative">
                                        <div class="absolute -left-[25px] top-1 w-4 h-4 rounded-full bg-rose-600 border-2 border-surface"></div>
                                        <div class="text-xs">
                                            <span class="font-bold text-rose-500">Tiket Dibatalkan</span>
                                            <p class="text-text-muted">Dibatalkan pada <?php echo e(\Carbon\Carbon::parse($task->cancelled_at)->translatedFormat('d M Y, H:i')); ?> WIB. Alasan: <?php echo e($task->cancel_reason ?? '-'); ?></p>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

            <!-- FOP Tasks / Legacy Field Tasks -->
            <?php $__currentLoopData = $customerFopTasks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ftask): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php
                    $uniqueId = 'foptask_' . $ftask->id;
                    $statusStr = $ftask->status instanceof \App\Enums\TaskStatus ? $ftask->status->value : ($ftask->status ?? 'draft');

                    $statusBadge = match($statusStr) {
                        'completed', 'selesai' => 'bg-emerald-50/10 text-emerald-500 border-emerald-500/20',
                        'in_progress', 'proses', 'terjadwal' => 'bg-amber-50/10 text-amber-500 border-amber-500/20',
                        'cancelled', 'batal', 'dibatalkan' => 'bg-rose-50/10 text-rose-500 border-rose-500/20',
                        default => 'bg-surface-muted text-text-muted border-border'
                    };

                    $assignedTeam = $ftask->technicians->map(fn($t) => $t->name)->implode(', ');
                    if(empty($assignedTeam)) $assignedTeam = 'Belum ditugaskan';
                ?>

                <div class="border border-border rounded-xl bg-surface shadow-sm overflow-hidden transition-all hover:border-border/80">
                    <div onclick="toggleTicketHistory('<?php echo e($uniqueId); ?>')" class="p-4 bg-surface hover:bg-surface-muted/50 transition-colors cursor-pointer flex flex-col md:flex-row md:items-center justify-between gap-3 select-none">
                        <div class="flex items-start md:items-center gap-3.5">
                            <div class="w-10 h-10 rounded-lg bg-surface-muted border border-border flex flex-col items-center justify-center shrink-0 text-teal-600 font-mono text-[11px] font-bold">
                                <svg class="w-4 h-4 mb-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                            </div>
                            <div>
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-mono font-bold text-xs text-teal-600"><?php echo e($ftask->task_number ?? ('FOP-TSK-' . $ftask->id)); ?></span>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold border bg-teal-50/10 text-teal-500 border-teal-500/20">FOP FIELD TASK</span>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-semibold border <?php echo e($statusBadge); ?>"><?php echo e(strtoupper($statusStr)); ?></span>
                                </div>
                                <h4 class="text-xs font-bold text-text-main mt-1"><?php echo e($ftask->issue ?? $ftask->tugas ?? 'Tugas Lapangan'); ?></h4>
                                <?php if($ftask->notes): ?>
                                    <p class="text-[11px] text-text-muted mt-0.5 line-clamp-1"><?php echo e($ftask->notes); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="flex items-center justify-between md:justify-end gap-6 border-t md:border-t-0 pt-3 md:pt-0 border-border text-xs">
                            <div class="text-right">
                                <span class="block text-[10px] text-text-muted font-medium">Teknisi Ditugaskan</span>
                                <span class="font-semibold text-text-secondary"><?php echo e($assignedTeam); ?></span>
                                <span class="block text-[10px] text-text-muted mt-0.5">
                                    <?php echo e($ftask->task_date ? \Carbon\Carbon::parse($ftask->task_date)->translatedFormat('d M Y') : '-'); ?>

                                </span>
                            </div>

                            <div class="flex items-center gap-1.5 text-primary font-bold text-xs shrink-0 bg-primary-soft px-3 py-2 rounded-lg group hover:bg-primary-soft/80 transition-colors">
                                <span>Lihat Riwayat</span>
                                <svg id="arrow_<?php echo e($uniqueId); ?>" class="w-4 h-4 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <div id="<?php echo e($uniqueId); ?>" class="hidden border-t border-border bg-surface-muted/20 p-5 space-y-4">
                        <div class="bg-surface p-5 rounded-xl border border-border">
                            <h5 class="text-xs font-bold text-text-main uppercase tracking-wider mb-4 pb-2 border-b border-border flex items-center gap-2">
                                <svg class="w-4 h-4 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                Riwayat Pengerjaan Tugas Lapangan
                            </h5>
                            <div class="relative pl-5 border-l-2 border-primary/20 space-y-4 ml-1">
                                <div class="relative">
                                    <div class="absolute -left-[25px] top-1 w-4 h-4 rounded-full bg-primary border-2 border-surface"></div>
                                    <div class="text-xs">
                                        <span class="font-bold text-text-main">Tugas Lapangan Diturunkan</span>
                                        <p class="text-text-muted">Tanggal Tugas: <?php echo e($ftask->task_date ? \Carbon\Carbon::parse($ftask->task_date)->translatedFormat('d M Y, H:i') : '-'); ?> WIB</p>
                                        <?php if($ftask->notes): ?>
                                            <p class="mt-1.5 p-2 rounded bg-surface-muted border border-border font-mono text-[11px] text-text-secondary"><?php echo e($ftask->notes); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    <?php endif; ?>
</div>

<script>
    function toggleTicketHistory(id) {
        const el = document.getElementById(id);
        const arrow = document.getElementById('arrow_' + id);
        if (el) {
            if (el.classList.contains('hidden')) {
                el.classList.remove('hidden');
                if (arrow) arrow.classList.add('rotate-180');
            } else {
                el.classList.add('hidden');
                if (arrow) arrow.classList.remove('rotate-180');
            }
        }
    }
</script>
<?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/customers/tabs/_riwayat_ticketing.blade.php ENDPATH**/ ?>