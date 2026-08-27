<div class="space-y-6">
    <!-- Header Summary -->
    <div class="border border-slate-200 dark:border-slate-700 rounded-lg p-5 bg-slate-50/70 dark:bg-slate-900/40 shadow-sm flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-teal-100 text-teal-600 flex items-center justify-center font-bold text-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                </div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100">Riwayat Ticketing & Tugas Operasional</h3>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1.5">
                Daftar seluruh tiket/tugas yang diturunkan untuk pelanggan ini. <strong>Tekan pada baris tiket</strong> untuk melihat riwayat lengkap progres dan penanganan tiket tersebut.
            </p>
        </div>

        <?php
            $totalTickets = $customerTasks->count() + $customerTickets->count() + $customerFopTasks->count();
            $completedTickets = $customerTasks->filter(fn($t) => ($t->status->value ?? $t->status) === 'selesai')->count() +
                                $customerTickets->filter(function ($tk) {
                                    // Dua rezim "selesai" tergantung handler — lihat Ticket::bucket().
                                    return $tk->handler === \App\Enums\TicketHandler::FOP
                                        ? $tk->fopTask?->status === \App\Enums\TaskStatus::SELESAI
                                        : $tk->status === \App\Enums\TicketHandlingStatus::CLOSED;
                                })->count() +
                                $customerFopTasks->filter(fn($t) => ($t->status->value ?? $t->status) === 'selesai')->count();
        ?>

        <div class="flex items-center gap-3 shrink-0">
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg px-3.5 py-2 text-center">
                <span class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase">Total Tiket</span>
                <span class="text-base font-extrabold text-slate-900 dark:text-slate-100"><?php echo e($totalTickets); ?></span>
            </div>
            <div class="bg-emerald-50/10 border border-emerald-500/30 rounded-lg px-3.5 py-2 text-center">
                <span class="block text-[10px] font-bold text-emerald-500 uppercase">Selesai</span>
                <span class="text-base font-extrabold text-emerald-500"><?php echo e($completedTickets); ?></span>
            </div>
        </div>
    </div>

    <?php if($totalTickets === 0): ?>
        <div class="py-12 text-center text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-slate-700 rounded-lg bg-slate-50/50 dark:bg-slate-900/30">
            <svg class="mx-auto h-12 w-12 text-slate-300 dark:text-slate-600 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122" />
            </svg>
            <h4 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Belum Ada Tiket yang Diturunkan</h4>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 max-w-md mx-auto">
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

                <div class="border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 shadow-sm overflow-hidden transition-all hover:border-slate-300 dark:border-slate-600">
                    <!-- Ticket Header / Clickable Bar -->
                    <div onclick="toggleTicketHistory('<?php echo e($uniqueId); ?>')" class="p-4 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:bg-slate-900/50 transition-colors cursor-pointer flex flex-col md:flex-row md:items-center justify-between gap-3 select-none">
                        <div class="flex items-start md:items-center gap-3.5">
                            <!-- Icon & Number -->
                            <div class="w-10 h-10 rounded-lg bg-slate-100 dark:bg-slate-700 border border-slate-200 dark:border-slate-700 flex flex-col items-center justify-center shrink-0 text-slate-700 dark:text-slate-300 font-mono text-[11px] font-bold">
                                <svg class="w-4 h-4 mb-0.5 text-slate-500 dark:text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" /></svg>
                            </div>

                            <div>
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-mono font-bold text-xs text-sky-700"><?php echo e($task->task_number); ?></span>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold border <?php echo e($typeBadge); ?>"><?php echo e(strtoupper($typeLabel)); ?></span>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-semibold border <?php echo e($statusBadge); ?>"><?php echo e($statusLabel); ?></span>
                                </div>
                                <h4 class="text-xs font-bold text-slate-900 dark:text-slate-100 mt-1"><?php echo e($task->title); ?></h4>
                                <?php if($task->description): ?>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 line-clamp-1"><?php echo e($task->description); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="flex items-center justify-between md:justify-end gap-6 border-t md:border-t-0 pt-3 md:pt-0 border-slate-200 dark:border-slate-700 text-xs">
                            <div class="text-right">
                                <span class="block text-[10px] text-slate-500 dark:text-slate-400 font-medium">Petugas Lapangan</span>
                                <span class="font-semibold text-slate-700 dark:text-slate-300"><?php echo e($assignedTeam); ?></span>
                                <span class="block text-[10px] text-slate-500 dark:text-slate-400 mt-0.5">
                                    <?php echo e($task->created_at ? \Carbon\Carbon::parse($task->created_at)->translatedFormat('d M Y') : '-'); ?>

                                </span>
                            </div>

                            <div class="flex items-center gap-1.5 text-sky-600 dark:text-sky-400 font-bold text-xs shrink-0 bg-sky-50 dark:bg-sky-950/40 px-3 py-2 rounded-lg group hover:bg-sky-50/80 dark:bg-sky-950/40 transition-colors">
                                <span>Lihat Riwayat</span>
                                <svg id="arrow_<?php echo e($uniqueId); ?>" class="w-4 h-4 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Expandable Detail & Timeline -->
                    <div id="<?php echo e($uniqueId); ?>" class="hidden border-t border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/30 p-5 space-y-6">
                        <!-- Task Metadata Panel -->
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 bg-white dark:bg-slate-800 p-4 rounded-lg border border-slate-200 dark:border-slate-700 text-xs">
                            <div>
                                <span class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase">Dibuat Oleh</span>
                                <span class="font-bold text-slate-900 dark:text-slate-100"><?php echo e($task->creator ? $task->creator->name : 'System'); ?></span>
                                <span class="block text-[10px] text-slate-500 dark:text-slate-400"><?php echo e($task->created_at ? \Carbon\Carbon::parse($task->created_at)->translatedFormat('d M Y, H:i') : '-'); ?> WIB</span>
                            </div>
                            <div>
                                <span class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase">Jadwal / Terjadwal</span>
                                <span class="font-bold text-slate-900 dark:text-slate-100"><?php echo e($task->scheduled_at ? \Carbon\Carbon::parse($task->scheduled_at)->translatedFormat('d M Y, H:i') : 'Belum Dijadwalkan'); ?></span>
                            </div>
                            <div>
                                <span class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase">FOP Reviewer</span>
                                <span class="font-bold text-slate-900 dark:text-slate-100"><?php echo e($task->fop ? $task->fop->name : '-'); ?></span>
                                <span class="block text-[10px] text-slate-500 dark:text-slate-400">Status: <?php echo e(strtoupper($task->fop_review_status ?? 'PENDING')); ?></span>
                            </div>
                            <div>
                                <span class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase">Target SLA</span>
                                <span class="font-bold text-slate-900 dark:text-slate-100"><?php echo e($task->sla_minutes ? $task->sla_minutes . ' Menit' : '-'); ?></span>
                            </div>
                        </div>

                        <!-- Step-by-Step Ticket Audit History -->
                        <div class="bg-white dark:bg-slate-800 p-5 rounded-lg border border-slate-200 dark:border-slate-700">
                            <h5 class="text-xs font-bold text-slate-900 dark:text-slate-100 uppercase tracking-wider mb-4 pb-2 border-b border-slate-200 dark:border-slate-700 flex items-center gap-2">
                                <svg class="w-4 h-4 text-sky-600 dark:text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                Riwayat Log Penanganan Tiket Ini
                            </h5>

                            <?php if($task->auditLogs->count() > 0): ?>
                                <div class="relative pl-5 border-l-2 border-sky-200 dark:border-sky-800 space-y-4 ml-1">
                                    <?php $__currentLoopData = $task->auditLogs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $alog): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <div class="relative">
                                            <div class="absolute -left-[25px] top-1 w-4 h-4 rounded-full bg-sky-600 border-2 border-white dark:border-slate-800 shadow-sm"></div>
                                            <div class="text-xs">
                                                <div class="flex items-center justify-between gap-2">
                                                    <span class="font-bold text-slate-900 dark:text-slate-100">
                                                        <?php echo e(match($alog->action) {
                                                            'create' => 'Tiket Dibuat',
                                                            'update' => 'Pembaruan Progres / Status Tiket',
                                                            'delete' => 'Tiket Dihapus',
                                                            default => ucfirst($alog->action)
                                                        }); ?>

                                                    </span>
                                                    <span class="text-[11px] text-slate-500 dark:text-slate-400 font-mono">
                                                        <?php echo e($alog->created_at ? \Carbon\Carbon::parse($alog->created_at)->translatedFormat('d M Y, H:i:s') : '-'); ?>

                                                    </span>
                                                </div>
                                                <div class="text-slate-500 dark:text-slate-400 mt-0.5 flex items-center gap-1.5">
                                                    <span>Oleh:</span>
                                                    <span class="font-semibold text-slate-700 dark:text-slate-300"><?php echo e($alog->user ? $alog->user->name : 'System'); ?></span>
                                                </div>
                                                <?php if(!empty($alog->new_values)): ?>
                                                    <div class="mt-1.5 p-2 rounded bg-slate-100 dark:bg-slate-700 border border-slate-200 dark:border-slate-700 font-mono text-[11px] text-slate-700 dark:text-slate-300">
                                                        <?php $__currentLoopData = $alog->new_values; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                            <?php if(is_scalar($v)): ?>
                                                                <div><strong class="text-slate-700 dark:text-slate-300"><?php echo e(ucwords(str_replace('_', ' ', $k))); ?>:</strong> <?php echo e($v); ?></div>
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
                                <div class="relative pl-5 border-l-2 border-slate-200 dark:border-slate-700 space-y-4 ml-1">
                                    <?php if($task->created_at): ?>
                                    <div class="relative">
                                        <div class="absolute -left-[25px] top-1 w-4 h-4 rounded-full bg-sky-600 border-2 border-white dark:border-slate-800"></div>
                                        <div class="text-xs">
                                            <span class="font-bold text-slate-900 dark:text-slate-100">Tiket Diterbitkan</span>
                                            <p class="text-slate-500 dark:text-slate-400">Dibuat oleh <?php echo e($task->creator ? $task->creator->name : 'System'); ?> pada <?php echo e(\Carbon\Carbon::parse($task->created_at)->translatedFormat('d M Y, H:i')); ?> WIB</p>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <?php if($task->scheduled_at): ?>
                                    <div class="relative">
                                        <div class="absolute -left-[25px] top-1 w-4 h-4 rounded-full bg-blue-500 border-2 border-white dark:border-slate-800"></div>
                                        <div class="text-xs">
                                            <span class="font-bold text-slate-900 dark:text-slate-100">Dijadwalkan untuk Pengerjaan</span>
                                            <p class="text-slate-500 dark:text-slate-400">Jadwal: <?php echo e(\Carbon\Carbon::parse($task->scheduled_at)->translatedFormat('d M Y, H:i')); ?> WIB</p>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <?php if($task->started_at): ?>
                                    <div class="relative">
                                        <div class="absolute -left-[25px] top-1 w-4 h-4 rounded-full bg-amber-500 border-2 border-white dark:border-slate-800"></div>
                                        <div class="text-xs">
                                            <span class="font-bold text-slate-900 dark:text-slate-100">Pengerjaan Dimulai (In Progress)</span>
                                            <p class="text-slate-500 dark:text-slate-400">Mulai dikerjakan pada <?php echo e(\Carbon\Carbon::parse($task->started_at)->translatedFormat('d M Y, H:i')); ?> WIB</p>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <?php if($task->completed_at): ?>
                                    <div class="relative">
                                        <div class="absolute -left-[25px] top-1 w-4 h-4 rounded-full bg-emerald-600 border-2 border-white dark:border-slate-800"></div>
                                        <div class="text-xs">
                                            <span class="font-bold text-emerald-500">Tiket Selesai Dikerjakan</span>
                                            <p class="text-slate-500 dark:text-slate-400">Selesai pada <?php echo e(\Carbon\Carbon::parse($task->completed_at)->translatedFormat('d M Y, H:i')); ?> WIB</p>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <?php if($task->cancelled_at): ?>
                                    <div class="relative">
                                        <div class="absolute -left-[25px] top-1 w-4 h-4 rounded-full bg-rose-600 border-2 border-white dark:border-slate-800"></div>
                                        <div class="text-xs">
                                            <span class="font-bold text-rose-500">Tiket Dibatalkan</span>
                                            <p class="text-slate-500 dark:text-slate-400">Dibatalkan pada <?php echo e(\Carbon\Carbon::parse($task->cancelled_at)->translatedFormat('d M Y, H:i')); ?> WIB. Alasan: <?php echo e($task->cancel_reason ?? '-'); ?></p>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

            <!-- Tickets (Helpdesk/NOC/FOP) -->
            <?php $__currentLoopData = $customerTickets; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ticket): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php
                    // Satu tiket, satu baris — regime tampilan ngikut handler
                    // (sama seperti Ticket::bucket()/statusLabel()):
                    //  - handler=FOP → sudah turun ke Ticketing FOP, tampil sebagai "TICKET FOP".
                    //  - handler=HELPDESK/NOC → belum/tidak pernah ke FOP, tampil sebagai "TICKET HELPDESK/NOC".
                    $isFop = $ticket->handler === \App\Enums\TicketHandler::FOP;
                    $uniqueId = 'ticket_' . $ticket->id;
                    $typeLabel = $ticket->type instanceof \App\Enums\TaskType ? $ticket->type->label() : ($ticket->type ?? 'Tiket');
                    $assignedTeam = $isFop
                        ? ($ticket->fopTask?->technicians->map(fn($t) => $t->name)->implode(', ') ?: 'Belum ditugaskan')
                        : null;
                ?>

                <div class="border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 shadow-sm overflow-hidden transition-all hover:border-slate-300 dark:border-slate-600">
                    <div onclick="toggleTicketHistory('<?php echo e($uniqueId); ?>')" class="p-4 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:bg-slate-900/50 transition-colors cursor-pointer flex flex-col md:flex-row md:items-center justify-between gap-3 select-none">
                        <div class="flex items-start md:items-center gap-3.5">
                            <div class="w-10 h-10 rounded-lg bg-slate-100 dark:bg-slate-700 border border-slate-200 dark:border-slate-700 flex flex-col items-center justify-center shrink-0 <?php echo e($isFop ? 'text-teal-600' : 'text-amber-600'); ?> font-mono text-[11px] font-bold">
                                <svg class="w-4 h-4 mb-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                            </div>
                            <div>
                                <div class="flex items-center gap-2 flex-wrap">
                                    <?php if($isFop): ?>
                                        <span class="font-mono font-bold text-xs text-teal-600"><?php echo e($ticket->fopTask?->task_number ?? $ticket->ticket_number); ?></span>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold border bg-teal-50/10 text-teal-500 border-teal-500/20">TICKET FOP</span>
                                    <?php else: ?>
                                        <span class="font-mono font-bold text-xs text-amber-600"><?php echo e($ticket->ticket_number); ?></span>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold border bg-amber-50/10 text-amber-600 border-amber-500/20">TICKET <?php echo e(strtoupper($ticket->handler->label())); ?></span>
                                    <?php endif; ?>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-semibold border <?php echo e($ticket->statusBadgeClasses()); ?>"><?php echo e(strtoupper($ticket->statusLabel())); ?></span>
                                </div>
                                <h4 class="text-xs font-bold text-slate-900 dark:text-slate-100 mt-1"><?php echo e($typeLabel); ?> — <?php echo e($ticket->detail_keluhan ?? 'Tiket Pelanggan'); ?></h4>
                                <?php if(!$isFop && $ticket->catatan_teknis): ?>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 line-clamp-1"><?php echo e($ticket->catatan_teknis); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="flex items-center justify-between md:justify-end gap-6 border-t md:border-t-0 pt-3 md:pt-0 border-slate-200 dark:border-slate-700 text-xs">
                            <div class="text-right">
                                <span class="block text-[10px] text-slate-500 dark:text-slate-400 font-medium"><?php echo e($isFop ? 'Teknisi Ditugaskan' : 'Ditangani Oleh'); ?></span>
                                <span class="font-semibold text-slate-700 dark:text-slate-300"><?php echo e($isFop ? $assignedTeam : $ticket->handler->label()); ?></span>
                                <span class="block text-[10px] text-slate-500 dark:text-slate-400 mt-0.5">
                                    <?php echo e($ticket->created_at ? \Carbon\Carbon::parse($ticket->created_at)->translatedFormat('d M Y') : '-'); ?>

                                </span>
                            </div>

                            <div class="flex items-center gap-1.5 text-sky-600 dark:text-sky-400 font-bold text-xs shrink-0 bg-sky-50 dark:bg-sky-950/40 px-3 py-2 rounded-lg group hover:bg-sky-50/80 dark:bg-sky-950/40 transition-colors">
                                <span>Lihat Riwayat</span>
                                <svg id="arrow_<?php echo e($uniqueId); ?>" class="w-4 h-4 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <div id="<?php echo e($uniqueId); ?>" class="hidden border-t border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/30 p-5 space-y-4">
                        <div class="bg-white dark:bg-slate-800 p-5 rounded-lg border border-slate-200 dark:border-slate-700">
                            <h5 class="text-xs font-bold text-slate-900 dark:text-slate-100 uppercase tracking-wider mb-4 pb-2 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between gap-2">
                                <span class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-sky-600 dark:text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    <?php echo e($isFop ? 'Riwayat Penanganan Ticket FOP' : 'Riwayat Penanganan Ticket ' . $ticket->handler->label()); ?>

                                </span>
                                <?php if($isFop && $ticket->fop_task_id): ?>
                                    <a href="<?php echo e(route('fop-tasks.history.show', $ticket->fop_task_id)); ?>" class="text-[11px] font-semibold text-sky-600 hover:underline normal-case">Lihat Detail FOP &rarr;</a>
                                <?php else: ?>
                                    <a href="<?php echo e(route('tickets.show', $ticket->id)); ?>" class="text-[11px] font-semibold text-sky-600 hover:underline normal-case">Lihat Detail Tiket &rarr;</a>
                                <?php endif; ?>
                            </h5>

                            <div class="relative pl-5 border-l-2 border-sky-200 dark:border-sky-800 space-y-4 ml-1">
                                <div class="relative">
                                    <div class="absolute -left-[25px] top-1 w-4 h-4 rounded-full bg-sky-600 border-2 border-white dark:border-slate-800"></div>
                                    <div class="text-xs">
                                        <span class="font-bold text-slate-900 dark:text-slate-100">Tiket Dibuat</span>
                                        <p class="text-slate-500 dark:text-slate-400">Oleh <?php echo e($ticket->creator?->name ?? 'System'); ?> pada <?php echo e($ticket->created_at ? \Carbon\Carbon::parse($ticket->created_at)->translatedFormat('d M Y, H:i') : '-'); ?> WIB</p>
                                    </div>
                                </div>

                                <?php if($isFop): ?>
                                    <div class="relative">
                                        <div class="absolute -left-[25px] top-1 w-4 h-4 rounded-full bg-teal-600 border-2 border-white dark:border-slate-800"></div>
                                        <div class="text-xs">
                                            <span class="font-bold text-slate-900 dark:text-slate-100">Turun ke Ticketing FOP</span>
                                            <p class="text-slate-500 dark:text-slate-400">Status pengerjaan lapangan saat ini: <?php echo e($ticket->statusLabel()); ?></p>
                                        </div>
                                    </div>
                                <?php elseif($ticket->status === \App\Enums\TicketHandlingStatus::CLOSED): ?>
                                    <div class="relative">
                                        <div class="absolute -left-[25px] top-1 w-4 h-4 rounded-full bg-emerald-600 border-2 border-white dark:border-slate-800"></div>
                                        <div class="text-xs">
                                            <span class="font-bold text-emerald-500">Selesai di <?php echo e($ticket->handler->label()); ?></span>
                                            <p class="text-slate-500 dark:text-slate-400">Diselesaikan pada <?php echo e($ticket->resolved_at ? \Carbon\Carbon::parse($ticket->resolved_at)->translatedFormat('d M Y, H:i') : '-'); ?> WIB</p>
                                        </div>
                                    </div>
                                <?php elseif($ticket->status === \App\Enums\TicketHandlingStatus::CANCELLED): ?>
                                    <div class="relative">
                                        <div class="absolute -left-[25px] top-1 w-4 h-4 rounded-full bg-rose-600 border-2 border-white dark:border-slate-800"></div>
                                        <div class="text-xs">
                                            <span class="font-bold text-rose-500">Dibatalkan di <?php echo e($ticket->handler->label()); ?></span>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="relative">
                                        <div class="absolute -left-[25px] top-1 w-4 h-4 rounded-full bg-amber-500 border-2 border-white dark:border-slate-800"></div>
                                        <div class="text-xs">
                                            <span class="font-bold text-slate-900 dark:text-slate-100">Sedang ditangani <?php echo e($ticket->handler->label()); ?></span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

            <!-- FOP Tasks / Legacy Field Tasks (bukan dari tiket) -->
            <?php $__currentLoopData = $customerFopTasks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ftask): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php
                    $uniqueId = 'foptask_' . $ftask->id;
                    $statusStr = $ftask->status instanceof \App\Enums\TaskStatus ? $ftask->status->value : ($ftask->status ?? 'draft');

                    $statusBadge = match($statusStr) {
                        'completed', 'selesai' => 'bg-emerald-50/10 text-emerald-500 border-emerald-500/20',
                        'in_progress', 'proses', 'terjadwal' => 'bg-amber-50/10 text-amber-500 border-amber-500/20',
                        'cancelled', 'batal', 'dibatalkan' => 'bg-rose-50/10 text-rose-500 border-rose-500/20',
                        default => 'bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400 border-slate-200 dark:border-slate-700'
                    };

                    $assignedTeam = $ftask->technicians->map(fn($t) => $t->name)->implode(', ');
                    if(empty($assignedTeam)) $assignedTeam = 'Belum ditugaskan';
                ?>

                <div class="border border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 shadow-sm overflow-hidden transition-all hover:border-slate-300 dark:border-slate-600">
                    <div onclick="toggleTicketHistory('<?php echo e($uniqueId); ?>')" class="p-4 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:bg-slate-900/50 transition-colors cursor-pointer flex flex-col md:flex-row md:items-center justify-between gap-3 select-none">
                        <div class="flex items-start md:items-center gap-3.5">
                            <div class="w-10 h-10 rounded-lg bg-slate-100 dark:bg-slate-700 border border-slate-200 dark:border-slate-700 flex flex-col items-center justify-center shrink-0 text-teal-600 font-mono text-[11px] font-bold">
                                <svg class="w-4 h-4 mb-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                            </div>
                            <div>
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-mono font-bold text-xs text-teal-600"><?php echo e($ftask->task_number ?? ('FOP-TSK-' . $ftask->id)); ?></span>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold border bg-teal-50/10 text-teal-500 border-teal-500/20">FOP FIELD TASK</span>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-semibold border <?php echo e($statusBadge); ?>"><?php echo e(strtoupper($statusStr)); ?></span>
                                </div>
                                <h4 class="text-xs font-bold text-slate-900 dark:text-slate-100 mt-1"><?php echo e($ftask->issue ?? $ftask->tugas ?? 'Tugas Lapangan'); ?></h4>
                                <?php if($ftask->notes): ?>
                                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 line-clamp-1"><?php echo e($ftask->notes); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="flex items-center justify-between md:justify-end gap-6 border-t md:border-t-0 pt-3 md:pt-0 border-slate-200 dark:border-slate-700 text-xs">
                            <div class="text-right">
                                <span class="block text-[10px] text-slate-500 dark:text-slate-400 font-medium">Teknisi Ditugaskan</span>
                                <span class="font-semibold text-slate-700 dark:text-slate-300"><?php echo e($assignedTeam); ?></span>
                                <span class="block text-[10px] text-slate-500 dark:text-slate-400 mt-0.5">
                                    <?php echo e($ftask->task_date ? \Carbon\Carbon::parse($ftask->task_date)->translatedFormat('d M Y') : '-'); ?>

                                </span>
                            </div>

                            <div class="flex items-center gap-1.5 text-sky-600 dark:text-sky-400 font-bold text-xs shrink-0 bg-sky-50 dark:bg-sky-950/40 px-3 py-2 rounded-lg group hover:bg-sky-50/80 dark:bg-sky-950/40 transition-colors">
                                <span>Lihat Riwayat</span>
                                <svg id="arrow_<?php echo e($uniqueId); ?>" class="w-4 h-4 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <div id="<?php echo e($uniqueId); ?>" class="hidden border-t border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/30 p-5 space-y-4">
                        <div class="bg-white dark:bg-slate-800 p-5 rounded-lg border border-slate-200 dark:border-slate-700">
                            <h5 class="text-xs font-bold text-slate-900 dark:text-slate-100 uppercase tracking-wider mb-4 pb-2 border-b border-slate-200 dark:border-slate-700 flex items-center gap-2">
                                <svg class="w-4 h-4 text-sky-600 dark:text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                Riwayat Pengerjaan Tugas Lapangan
                            </h5>
                            <div class="relative pl-5 border-l-2 border-sky-200 dark:border-sky-800 space-y-4 ml-1">
                                <div class="relative">
                                    <div class="absolute -left-[25px] top-1 w-4 h-4 rounded-full bg-sky-600 border-2 border-white dark:border-slate-800"></div>
                                    <div class="text-xs">
                                        <span class="font-bold text-slate-900 dark:text-slate-100">Tugas Lapangan Diturunkan</span>
                                        <p class="text-slate-500 dark:text-slate-400">Tanggal Tugas: <?php echo e($ftask->task_date ? \Carbon\Carbon::parse($ftask->task_date)->translatedFormat('d M Y, H:i') : '-'); ?> WIB</p>
                                        <?php if($ftask->notes): ?>
                                            <p class="mt-1.5 p-2 rounded bg-slate-100 dark:bg-slate-700 border border-slate-200 dark:border-slate-700 font-mono text-[11px] text-slate-700 dark:text-slate-300"><?php echo e($ftask->notes); ?></p>
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