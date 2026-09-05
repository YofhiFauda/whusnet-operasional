<?php $__env->startSection('title', 'Detail Riwayat — ' . $fopTask->task_number); ?>

<?php $__env->startSection('content'); ?>
<div class="px-4 py-6 max-w-5xl mx-auto space-y-5">

    
    <nav class="flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400 font-ui">
        <a href="<?php echo e(route('fop-tasks.history')); ?>" class="hover:text-blue-600 transition-colors">Riwayat Task FOP</a>
        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
        </svg>
        <span class="font-mono"><?php echo e($fopTask->task_number); ?></span>
    </nav>

    
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
        <div>
            <div class="flex items-center gap-2 flex-wrap mb-1.5">
                <span class="px-1.5 py-0.5 rounded text-[10px] font-medium border font-ui <?php echo e($fopTask->category instanceof \App\Enums\TaskType ? $fopTask->category->badgeClasses() : 'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-400'); ?>">
                    <?php echo e($fopTask->category instanceof \App\Enums\TaskType ? $fopTask->category->value : $fopTask->category); ?>

                </span>
                <?php
                    $statusBadge = match ($fopTask->status->value) {
                        'selesai' => 'bg-green-50 text-green-700 border-green-200',
                        'dibatalkan' => 'bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 border-red-200',
                        default => 'bg-slate-50 dark:bg-slate-800/50 text-slate-600 dark:text-slate-400 border-slate-200 dark:border-slate-700',
                    };
                    $statusLabel = $fopTask->task
                        ? $fopTask->task->status->displayLabel($fopTask->task->report_deferred)
                        : $fopTask->status->displayLabel();
                ?>
                <span class="px-1.5 py-0.5 rounded text-[10px] font-medium border font-ui <?php echo e($statusBadge); ?>">
                    <?php echo e($statusLabel); ?>

                </span>
            </div>
            <h1 class="text-xl font-bold text-slate-900 dark:text-slate-100 tracking-tight font-ui"><?php echo e($fopTask->tugas); ?></h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 font-ui font-mono"><?php echo e($fopTask->task_number); ?></p>
        </div>
    </div>

    <?php
        // MTN & C-REQ yang asalnya dari Ticketing — Issue/Gangguan & Catatan
        // Teknis buat tipe ini WAJIB dibaca dari $ticket (utuh, proper),
        // bukan $fopTask->issue (kepotong 255 char) — dipindah ke atas Info
        // Task biar baris Issue generik bisa disembunyikan buat tipe ini
        // (dipindah/diganti versi robust di section "Detail Ticket" bawah).
        $isTicketOriginType = in_array($fopTask->category, [\App\Enums\TaskType::MAINTENANCE, \App\Enums\TaskType::CREQ], true);
        $ticket = $fopTask->ticket;
        $showTicketDetail = $isTicketOriginType && $ticket && auth()->user()->hasPermission('tickets.view');
    ?>

    
    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded shadow-sm overflow-hidden">
        <div class="px-4 py-2.5 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50">
            <h2 class="text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider font-ui">Info Task</h2>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 p-4 text-[11px] font-ui">
            <div>
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Tanggal</p>
                <p class="font-medium text-slate-800 dark:text-slate-200 font-data"><?php echo e($fopTask->task_date?->format('d/m/Y H:i') ?? '—'); ?></p>
            </div>
            <div>
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Area</p>
                <p class="font-medium text-slate-800 dark:text-slate-200"><?php echo e($fopTask->village?->name ?? '—'); ?></p>
            </div>
            <div>
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">POP / Cabang</p>
                <p class="font-medium text-slate-800 dark:text-slate-200"><?php echo e($fopTask->pop?->name ?? '—'); ?></p>
            </div>
            <div>
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Prioritas</p>
                <p class="font-medium text-slate-800 dark:text-slate-200"><?php echo e($fopTask->priority->value); ?></p>
            </div>
            
            <?php if (! ($showTicketDetail)): ?>
            <div class="col-span-2">
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Issue</p>
                <p class="font-medium text-red-600 dark:text-red-400"><?php echo e($fopTask->issue ?? '—'); ?></p>
            </div>
            <?php endif; ?>
            <?php if($fopTask->status->value === 'dibatalkan'): ?>
            <div class="col-span-2">
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Alasan Cancel</p>
                <p class="font-medium text-slate-800 dark:text-slate-200"><?php echo e($fopTask->cancel_reason ?? '—'); ?></p>
            </div>
            <?php endif; ?>
            <?php if($fopTask->notes && !$showTicketDetail): ?>
            <div class="col-span-2 sm:col-span-4">
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Catatan</p>
                <p class="font-medium text-slate-800 dark:text-slate-200"><?php echo e($fopTask->notes); ?></p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    
    <?php if (! ($showTicketDetail)): ?>
    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded shadow-sm overflow-hidden">
        <div class="px-4 py-2.5 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50">
            <h2 class="text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider font-ui">Detail Registrasi</h2>
        </div>

        <?php if($fopTask->customer): ?>
        <?php $regCustomer = $fopTask->customer; ?>

        
        <div class="flex flex-wrap items-center gap-x-6 gap-y-1 px-4 pt-3 text-[11px] font-ui">
            <p><span class="text-slate-400 dark:text-slate-500">Teknisi:</span> <span class="font-medium text-slate-800 dark:text-slate-200"><?php echo e($fopTask->technicians->pluck('name')->implode(', ') ?: '—'); ?></span></p>
            <p><span class="text-slate-400 dark:text-slate-500">Team:</span> <span class="font-medium text-slate-800 dark:text-slate-200"><?php echo e($fopTask->team?->name ?? '—'); ?></span></p>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 p-4 text-[11px] font-ui">
            <div>
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">CID</p>
                <p class="font-medium text-blue-700 font-data"><?php echo e($regCustomer->display_id ?: '—'); ?></p>
            </div>
            <div>
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Nama</p>
                <p class="font-medium text-slate-800 dark:text-slate-200"><?php echo e($regCustomer->full_name ?: '—'); ?></p>
            </div>
            <div>
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">No. HP</p>
                <p class="font-medium text-slate-800 dark:text-slate-200 font-data"><?php echo e($regCustomer->primary_phone ?: ($regCustomer->phone ?: '—')); ?></p>
            </div>
            <div>
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">ODP</p>
                <p class="font-medium text-slate-800 dark:text-slate-200 font-data"><?php echo e($regCustomer->customerTechnicalDetail?->odp_number ?: '—'); ?></p>
            </div>
            <div>
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Paket</p>
                <p class="font-medium text-slate-800 dark:text-slate-200"><?php echo e($regCustomer->internetPackage ? $regCustomer->internetPackage->package_code . ' - ' . $regCustomer->internetPackage->name : '—'); ?></p>
            </div>
            <div class="col-span-2">
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Alamat</p>
                <p class="font-medium text-slate-800 dark:text-slate-200"><?php echo e($regCustomer->address ?: ($regCustomer->customerAddress?->village ?: '—')); ?></p>
            </div>
            <div>
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Perangkat Pelanggan</p>
                <p class="font-medium text-slate-800 dark:text-slate-200">
                    <?php echo e($regCustomer->customerDevice ? trim(($regCustomer->customerDevice->device_type ?? '') . ' ' . ($regCustomer->customerDevice->brand ?? '') . ' ' . ($regCustomer->customerDevice->model ?? '')) ?: '—' : '—'); ?>

                </p>
            </div>
            <div>
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Koordinat</p>
                <?php if($regCustomer->latitude && $regCustomer->longitude): ?>
                    <a href="https://www.google.com/maps/search/?api=1&query=<?php echo e($regCustomer->latitude); ?>,<?php echo e($regCustomer->longitude); ?>" target="_blank" rel="noopener" class="font-medium text-blue-600 hover:underline font-data">
                        <?php echo e($regCustomer->latitude); ?>, <?php echo e($regCustomer->longitude); ?>

                    </a>
                <?php else: ?>
                    <p class="font-medium text-slate-800 dark:text-slate-200">—</p>
                <?php endif; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="flex flex-wrap items-center gap-x-6 gap-y-1 px-4 py-3 text-[11px] font-ui">
            <p><span class="text-slate-400 dark:text-slate-500">Teknisi:</span> <span class="font-medium text-slate-800 dark:text-slate-200"><?php echo e($fopTask->technicians->pluck('name')->implode(', ') ?: '—'); ?></span></p>
            <p><span class="text-slate-400 dark:text-slate-500">Team:</span> <span class="font-medium text-slate-800 dark:text-slate-200"><?php echo e($fopTask->team?->name ?? '—'); ?></span></p>
        </div>
        <p class="px-4 pb-4 text-[11px] text-slate-400 dark:text-slate-500 italic font-ui">Task ini tidak terhubung ke pelanggan tertentu.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if($showTicketDetail): ?>
    
    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded shadow-sm overflow-hidden">
        <div class="px-4 py-2.5 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 flex items-center justify-between">
            <h2 class="text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider font-ui">Detail Ticket</h2>
            <a href="<?php echo e(route('tickets.show', $ticket)); ?>" class="text-[11px] font-medium text-blue-600 hover:underline font-ui">
                <?php echo e($ticket->ticket_number); ?> — Buka di Ticketing →
            </a>
        </div>

        
        <div class="flex flex-wrap items-center gap-2 px-4 pt-3 text-[11px] font-ui">
            <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded border <?php echo e($ticket->type->badgeClasses()); ?>">
                <?php echo e($ticket->type->value); ?>

            </span>
            <?php if($ticket->issueCategory): ?>
                <span class="text-[10px] font-bold px-2 py-0.5 rounded border border-sky-200 dark:border-sky-900 text-sky-700 dark:text-sky-400 bg-sky-50 dark:bg-sky-950/50">
                    <?php echo e($ticket->issueCategory->name); ?>

                </span>
            <?php endif; ?>
            <?php if($ticket->priority): ?>
                <span class="text-[10px] font-semibold text-slate-500 dark:text-slate-400">
                    Prioritas: <?php echo e($ticket->priority->value); ?>

                </span>
            <?php endif; ?>
            <span class="text-[10px] font-bold px-2 py-0.5 rounded border <?php echo e($ticket->statusBadgeClasses()); ?>">
                <?php echo e($ticket->statusLabel()); ?>

            </span>
        </div>

        
        <div class="flex flex-wrap items-center gap-x-6 gap-y-1 px-4 pt-3 text-[11px] font-ui">
            <p><span class="text-slate-400 dark:text-slate-500">Assigned by:</span> <span class="font-medium text-slate-800 dark:text-slate-200"><?php echo e($ticket->creator->name ?? '—'); ?></span></p>
            <p><span class="text-slate-400 dark:text-slate-500">Created:</span> <span class="font-medium text-slate-800 dark:text-slate-200 font-data"><?php echo e(\App\Support\IndonesianDate::dateTime($ticket->created_at)); ?></span></p>
        </div>

        
        <div class="px-4 pt-3">
            <p class="text-[10px] font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider font-ui mb-2">Data Pelanggan (saat ticket dibuat)</p>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 px-4 pb-4 text-[11px] font-ui">
            <div>
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">CID</p>
                <p class="font-medium text-blue-700 font-data">
                    <?php echo e($ticket->customer?->display_id ?: ($ticket->customer?->cid ?: ($ticket->customer?->customer_code ?: '—'))); ?>

                </p>
            </div>
            <div>
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Nama</p>
                <p class="font-medium text-slate-800 dark:text-slate-200"><?php echo e($ticket->customer_name ?: '—'); ?></p>
            </div>
            <div>
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">No. HP</p>
                <p class="font-medium text-slate-800 dark:text-slate-200 font-data"><?php echo e($ticket->customer_phone ?: '—'); ?></p>
            </div>
            <div>
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">ODP</p>
                <p class="font-medium text-slate-800 dark:text-slate-200 font-data"><?php echo e($ticket->customer_odp ?: '—'); ?></p>
            </div>
            <div>
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Paket</p>
                <p class="font-medium text-slate-800 dark:text-slate-200"><?php echo e($ticket->customer_package ?: '—'); ?></p>
            </div>
            <div class="col-span-2">
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Alamat</p>
                <p class="font-medium text-slate-800 dark:text-slate-200"><?php echo e($ticket->customer_address ?: '—'); ?></p>
            </div>
            <div>
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Perangkat Pelanggan</p>
                <p class="font-medium text-slate-800 dark:text-slate-200"><?php echo e($ticket->customer_device ?: '—'); ?></p>
            </div>
            <div>
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Koordinat</p>
                <?php if($ticket->customerMapsUrl()): ?>
                    <a href="<?php echo e($ticket->customerMapsUrl()); ?>" target="_blank" rel="noopener" class="font-medium text-blue-600 hover:underline font-data">
                        <?php echo e($ticket->customer_latitude); ?>, <?php echo e($ticket->customer_longitude); ?>

                    </a>
                <?php else: ?>
                    <p class="font-medium text-slate-800 dark:text-slate-200">—</p>
                <?php endif; ?>
            </div>
        </div>

        
        <div class="px-4 pb-4 border-t border-slate-100 dark:border-slate-700/50 pt-3 space-y-3">
            <div class="border border-amber-200 dark:border-amber-800/50 bg-amber-50/60 rounded overflow-hidden">
                <p class="px-3 py-1.5 text-[10px] font-semibold text-amber-700 dark:text-amber-400 uppercase tracking-wider font-ui border-b border-amber-200 dark:border-amber-800/50 bg-amber-50 dark:bg-amber-900/20">
                    Issue / Gangguan
                </p>
                <p class="px-3 py-2.5 text-[11px] font-medium text-slate-800 dark:text-slate-200 whitespace-pre-line font-ui"><?php echo e($ticket->detail_keluhan); ?></p>
            </div>
            <div class="border border-slate-200 dark:border-slate-700 bg-slate-50/60 rounded overflow-hidden">
                <p class="px-3 py-1.5 text-[10px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider font-ui border-b border-slate-200 dark:border-slate-700 bg-slate-100 dark:bg-slate-700/50">
                    Catatan Teknis
                </p>
                <p class="px-3 py-2.5 text-[11px] font-medium text-slate-800 dark:text-slate-200 whitespace-pre-line font-ui"><?php echo e($ticket->catatan_teknis ?: '— Belum ada catatan teknis.'); ?></p>
            </div>
        </div>

        <?php if($ticket->attachments->isNotEmpty()): ?>
        <div class="px-4 pb-4 border-t border-slate-100 dark:border-slate-700/50 pt-3">
            <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-1.5 font-ui">Lampiran (<?php echo e($ticket->attachments->count()); ?>)</p>
            <div class="flex flex-wrap gap-3">
                <?php $__currentLoopData = $ticket->attachments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $attachment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <a href="<?php echo e(route('tickets.attachments.download', $attachment)); ?>"
                       class="inline-flex items-center gap-1.5 text-[11px] text-blue-600 hover:underline font-ui">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                        </svg>
                        <?php echo e($attachment->original_name); ?>

                    </a>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    
    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded shadow-sm overflow-hidden">
        <div class="px-4 py-2.5 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50">
            <h2 class="text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider font-ui">Riwayat Ticketing</h2>
        </div>
        <?php if($ticket->histories->isNotEmpty()): ?>
        <div class="divide-y divide-slate-100 dark:divide-slate-700/50">
            <?php $__currentLoopData = $ticket->histories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ticketHistory): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="px-4 py-2.5 flex items-center justify-between gap-3 text-[11px] font-ui">
                <div class="flex items-center gap-2">
                    <span class="font-medium text-slate-800 dark:text-slate-200"><?php echo e($ticketHistory->action->label()); ?></span>
                    <?php if($ticketHistory->reason): ?>
                    <span class="text-slate-400 dark:text-slate-500">— <?php echo e($ticketHistory->reason); ?></span>
                    <?php endif; ?>
                </div>
                <div class="flex items-center gap-3 text-slate-500 dark:text-slate-400 font-data">
                    <span><?php echo e($ticketHistory->actor?->name ?? 'Sistem'); ?></span>
                    <span><?php echo e($ticketHistory->happened_at->format('d/m/Y H:i')); ?></span>
                </div>
            </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
        <?php else: ?>
        <p class="p-4 text-[11px] text-slate-400 dark:text-slate-500 italic font-ui">Belum ada riwayat ticketing.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    
    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded shadow-sm overflow-hidden">
        <div class="px-4 py-2.5 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50">
            <h2 class="text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider font-ui">Durasi & SLA Pengerjaan</h2>
        </div>
        <?php $report = $fopTask->task?->report; ?>
        <?php if($report): ?>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 p-4 text-[11px] font-ui">
            <div>
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Mulai</p>
                <p class="font-medium text-slate-800 dark:text-slate-200 font-data"><?php echo e($report->started_at?->format('d/m/Y H:i') ?? '—'); ?></p>
            </div>
            <div>
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Pending Terakhir</p>
                <p class="font-medium text-slate-800 dark:text-slate-200 font-data"><?php echo e($report->pending_at?->format('d/m/Y H:i') ?? '—'); ?></p>
            </div>
            <div>
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Resume Terakhir</p>
                <p class="font-medium text-slate-800 dark:text-slate-200 font-data"><?php echo e($report->resumed_at?->format('d/m/Y H:i') ?? '—'); ?></p>
            </div>
            <div>
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Selesai</p>
                <p class="font-medium text-slate-800 dark:text-slate-200 font-data"><?php echo e($report->completed_at?->format('d/m/Y H:i') ?? '—'); ?></p>
            </div>
            <div>
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Durasi Aktual</p>
                <p class="font-semibold text-slate-800 dark:text-slate-200 font-data"><?php echo e($report->accumulatedDurationMinutes()); ?> menit</p>
            </div>
            <div>
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Target SLA</p>
                <p class="font-semibold text-slate-800 dark:text-slate-200 font-data"><?php echo e($report->sla_target_minutes ?? '—'); ?> menit</p>
            </div>
            <div>
                <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Status SLA</p>
                <?php if($report->sla_status === 'on_time'): ?>
                    <p class="font-semibold text-green-700">Tepat Waktu</p>
                <?php elseif($report->sla_status === 'over'): ?>
                    <p class="font-semibold text-red-600 dark:text-red-400">Lewat SLA (<?php echo e($report->sla_overrun_minutes); ?> mnt)</p>
                <?php else: ?>
                    <p class="font-medium text-slate-400 dark:text-slate-500">—</p>
                <?php endif; ?>
            </div>
        </div>
        <?php else: ?>
        <p class="p-4 text-[11px] text-slate-400 dark:text-slate-500 italic font-ui">Belum ada data siklus pengerjaan (teknisi belum pernah klik Mulai).</p>
        <?php endif; ?>
    </div>

    
    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded shadow-sm overflow-hidden">
        <div class="px-4 py-2.5 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50">
            <h2 class="text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider font-ui">Laporan</h2>
        </div>

        <?php if($fopTask->category === \App\Enums\TaskType::SURVEY): ?>
            <?php if($survey): ?>
            <div class="grid grid-cols-2 gap-4 p-4 text-[11px] font-ui">
                <div class="col-span-2">
                    
                    <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Alat Khusus / Kendala Peralatan</p>
                    <p class="font-medium text-slate-800 dark:text-slate-200"><?php echo e($survey->required_tools ?? '—'); ?></p>
                </div>
                <div>
                    <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Estimasi Kabel</p>
                    <p class="font-medium text-slate-800 dark:text-slate-200"><?php echo e($survey->cable_estimation_meter ? $survey->cable_estimation_meter . ' meter' : '—'); ?></p>
                </div>
                <div>
                    <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">ODP Terdekat</p>
                    <p class="font-medium text-slate-800 dark:text-slate-200"><?php echo e($survey->nearest_odp ?? '—'); ?></p>
                </div>
                <?php if($survey->requested_installation_date): ?>
                <div class="col-span-2">
                    <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Request Pemasangan Pelanggan</p>
                    <p class="font-medium text-slate-800 dark:text-slate-200 font-mono"><?php echo e(\App\Support\IndonesianDate::date($survey->requested_installation_date)); ?></p>
                </div>
                <?php endif; ?>
                <?php
                    $estimasiMaterial = $fopTask->materials()->estimasi()->orderBy('id')->get();
                ?>
                <?php if($estimasiMaterial->isNotEmpty()): ?>
                <div class="col-span-2">
                    <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-1">Estimasi Kebutuhan Alat</p>
                    <?php $__currentLoopData = $estimasiMaterial; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $material): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="flex justify-between border-b border-slate-100 dark:border-slate-700/50 py-1">
                        <span class="text-slate-600 dark:text-slate-400"><?php echo e($material->item_name); ?></span>
                        <span class="font-mono font-semibold text-slate-800 dark:text-slate-200"><?php echo e(rtrim(rtrim(number_format($material->qty, 2, ',', '.'), '0'), ',')); ?> <?php echo e($material->unit); ?></span>
                    </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
                <?php endif; ?>
                <div class="col-span-2">
                    <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Catatan Survey</p>
                    <p class="font-medium text-slate-800 dark:text-slate-200 whitespace-pre-line"><?php echo e($survey->survey_note ?? '—'); ?></p>
                </div>
                <?php if($survey->survey_photo || $survey->house_photo): ?>
                <div class="col-span-2 flex gap-3 flex-wrap">
                    <?php if($survey->survey_photo): ?>
                    <a href="<?php echo e(Storage::url($survey->survey_photo)); ?>" target="_blank" class="text-blue-600 hover:underline">Foto ODP →</a>
                    <?php endif; ?>
                    <?php if($survey->house_photo): ?>
                    <a href="<?php echo e(Storage::url($survey->house_photo)); ?>" target="_blank" class="text-blue-600 hover:underline">Foto Rumah →</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <p class="p-4 text-[11px] text-slate-400 dark:text-slate-500 italic font-ui">Belum ada laporan survey.</p>
            <?php endif; ?>
        <?php elseif($fopTask->category === \App\Enums\TaskType::PEMASANGAN): ?>
            <?php if($installation || $technicalDetail): ?>
            <div class="grid grid-cols-2 gap-4 p-4 text-[11px] font-ui">
                <div>
                    <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">ODP</p>
                    <p class="font-medium text-slate-800 dark:text-slate-200"><?php echo e($technicalDetail?->odp_number ?? '—'); ?> / Port <?php echo e($technicalDetail?->odp_port ?? '—'); ?></p>
                </div>
                <div>
                    <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Redaman</p>
                    <p class="font-medium text-slate-800 dark:text-slate-200"><?php echo e($technicalDetail?->actual_attenuation ?? '—'); ?></p>
                </div>
                <div>
                    <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Speedtest</p>
                    <p class="font-medium text-slate-800 dark:text-slate-200 font-data">↓<?php echo e($technicalDetail?->test_download ?? '—'); ?> / ↑<?php echo e($technicalDetail?->test_upload ?? '—'); ?> Mbps</p>
                </div>
                <div>
                    <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Kualitas Sinyal</p>
                    <p class="font-medium text-slate-800 dark:text-slate-200">Jitter <?php echo e($technicalDetail?->jitter_ms ?? '—'); ?>ms, Loss <?php echo e($technicalDetail?->packet_loss_percent ?? '—'); ?>%</p>
                </div>
                <?php
                    // ADHOC-54 — gabungan Aktif (inventory_serials) + Pasif
                    // (task_materials) per fop_task_id, sesuai §3.4 rancangan-ui.md.
                    // Ditaruh DI SINI (bukan halaman Verifikasi Admin) karena FOP
                    // gak punya `customers.detail.installation.validate` (itu
                    // permission approval, bukan buat FOP) — halaman ini gerbangnya
                    // `fop_tasks.view` yang FOP emang udah punya.
                    $materialTerpakai = $fopTask->materials()->terpakai()->orderBy('id')->get();
                    $installedSerials = $fopTask->inventorySerials()->with('item')->get();
                ?>
                <?php if($installedSerials->isNotEmpty()): ?>
                <div class="col-span-2">
                    <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-1">Perangkat Aktif Terpasang (Gudang)</p>
                    <?php $__currentLoopData = $installedSerials; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $serial): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="flex justify-between border-b border-slate-100 dark:border-slate-700/50 py-1">
                        <span class="text-slate-600 dark:text-slate-400"><?php echo e($serial->item->name ?? '-'); ?></span>
                        <span class="font-mono font-semibold text-slate-800 dark:text-slate-200">SN <?php echo e($serial->serial_number); ?></span>
                    </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
                <?php endif; ?>
                <?php if($materialTerpakai->isNotEmpty()): ?>
                <div class="col-span-2">
                    <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-1">Perangkat Pasif Terpakai</p>
                    <?php $__currentLoopData = $materialTerpakai; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $material): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="flex justify-between border-b border-slate-100 dark:border-slate-700/50 py-1">
                        <span class="text-slate-600 dark:text-slate-400"><?php echo e($material->item_name); ?></span>
                        <span class="font-mono font-semibold text-slate-800 dark:text-slate-200"><?php echo e(rtrim(rtrim(number_format($material->qty, 2, ',', '.'), '0'), ',')); ?> <?php echo e($material->unit); ?></span>
                    </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
                <?php endif; ?>

                <div class="col-span-2">
                    <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Catatan Pemasangan</p>
                    <p class="font-medium text-slate-800 dark:text-slate-200 whitespace-pre-line"><?php echo e($installation?->installation_note ?? '—'); ?></p>
                </div>
                <?php if($installation?->installation_photo || $installation?->signature_photo || $installation?->contract_photo): ?>
                <div class="col-span-2 flex gap-3 flex-wrap">
                    <?php if($installation->installation_photo): ?>
                    <a href="<?php echo e(Storage::url($installation->installation_photo)); ?>" target="_blank" class="text-blue-600 hover:underline">Foto Pemasangan →</a>
                    <?php endif; ?>
                    <?php if($installation->contract_photo): ?>
                    <a href="<?php echo e(Storage::url($installation->contract_photo)); ?>" target="_blank" class="text-blue-600 hover:underline">Foto Kontrak →</a>
                    <?php endif; ?>
                    <?php if($installation->signature_photo): ?>
                    <a href="<?php echo e(Storage::url($installation->signature_photo)); ?>" target="_blank" class="text-blue-600 hover:underline">Foto Tanda Tangan →</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <p class="p-4 text-[11px] text-slate-400 dark:text-slate-500 italic font-ui">Belum ada laporan pemasangan.</p>
            <?php endif; ?>
        <?php elseif($fopTask->category === \App\Enums\TaskType::MAINTENANCE): ?>
            <?php if($maintenance): ?>
            <div class="grid grid-cols-2 gap-4 p-4 text-[11px] font-ui">
                <div class="col-span-2 min-w-0 max-w-full overflow-hidden">
                    <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Kendala Teknis</p>
                    <p class="font-medium text-slate-800 dark:text-slate-200 whitespace-pre-line break-words [word-break:break-word]"><?php echo e($maintenance->kendala_teknis); ?></p>
                </div>
                <div class="col-span-2">
                    <p class="text-slate-400 dark:text-slate-500 uppercase tracking-wider text-[10px] mb-0.5">Alat Dipakai</p>
                    <div class="flex flex-wrap gap-1.5 mt-1">
                        <?php $__currentLoopData = ['kabel' => 'Kabel', 'modem' => 'Modem', 'patchcord' => 'Patchcord', 'sleeve' => 'Sleeve', 'lainnya' => 'Lainnya']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <?php if($maintenance->{$field}): ?>
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-blue-50 text-blue-700 border border-blue-100"><?php echo e($label); ?>: <?php echo e($maintenance->{$field}); ?></span>
                            <?php endif; ?>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                </div>
                <?php if($maintenance->opm_photo || $maintenance->speedtest_photo): ?>
                <div class="col-span-2 flex gap-3 flex-wrap">
                    <?php if($maintenance->opm_photo): ?>
                    <a href="<?php echo e(Storage::url($maintenance->opm_photo)); ?>" target="_blank" class="text-blue-600 hover:underline">Foto OPM →</a>
                    <?php endif; ?>
                    <?php if($maintenance->speedtest_photo): ?>
                    <a href="<?php echo e(Storage::url($maintenance->speedtest_photo)); ?>" target="_blank" class="text-blue-600 hover:underline">Foto Speedtest →</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <p class="p-4 text-[11px] text-slate-400 dark:text-slate-500 italic font-ui">Belum ada laporan maintenance.</p>
            <?php endif; ?>
        <?php else: ?>
            <p class="p-4 text-[11px] text-slate-400 dark:text-slate-500 italic font-ui">Tipe task ini tidak punya laporan lapangan terstruktur.</p>
        <?php endif; ?>
    </div>

    
    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded shadow-sm overflow-hidden">
        <div class="px-4 py-2.5 border-b border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50">
            <h2 class="text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider font-ui">Histori Status</h2>
        </div>
        <?php if($fopTask->statusHistories->isNotEmpty()): ?>
        <div class="divide-y divide-slate-100 dark:divide-slate-700/50">
            <?php $__currentLoopData = $fopTask->statusHistories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $history): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="px-4 py-2.5 flex items-center justify-between gap-3 text-[11px] font-ui">
                <div class="flex items-center gap-2">
                    <span class="font-medium text-slate-800 dark:text-slate-200"><?php echo e($history->label()); ?></span>
                    <?php if($history->from_status): ?>
                    <span class="text-slate-400 dark:text-slate-500">dari <?php echo e($history->from_status); ?></span>
                    <?php endif; ?>
                </div>
                <div class="flex items-center gap-3 text-slate-500 dark:text-slate-400 font-data">
                    <span><?php echo e($history->changedByUser?->name ?? 'Sistem'); ?></span>
                    <span><?php echo e($history->changed_at->format('d/m/Y H:i')); ?></span>
                </div>
            </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
        <?php else: ?>
        <p class="p-4 text-[11px] text-slate-400 dark:text-slate-500 italic font-ui">Belum ada histori transisi status.</p>
        <?php endif; ?>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/fop_tasks/history_detail.blade.php ENDPATH**/ ?>