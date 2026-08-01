<?php $__env->startSection('title', $task->task_number . ' — Task Detail'); ?>

<?php $__env->startSection('content'); ?>
<div class="max-w-[1180px] mx-auto px-4 sm:px-6 py-4 space-y-4">

    
    <nav class="flex items-center gap-1.5 text-xs text-text-muted">
        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('viewAll', \App\Models\Task::class)): ?>
        <a href="<?php echo e(auth()->user()->hasPermission('task.view.own') ? route('tasks.own') : route('fop.dashboard')); ?>" class="hover:text-primary transition-colors font-ui">Task</a>
        <?php else: ?>
        <a href="<?php echo e(route('tasks.own')); ?>" class="hover:text-primary transition-colors font-ui">Task Saya</a>
        <?php endif; ?>
        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
        </svg>
        <span class="font-mono"><?php echo e($task->task_number); ?></span>
    </nav>

    
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap mb-1">
                
                <?php
                    $statusStyle = match($task->status->value) {
                        'terjadwal'  => 'background:var(--color-info-bg); color:var(--color-info); border-color:var(--color-info-border)',
                        'in_progress'=> 'background:var(--color-warning-bg); color:var(--color-warning); border-color:var(--color-warning-border)',
                        'selesai'    => 'background:var(--color-success-bg); color:var(--color-success); border-color:var(--color-success-border)',
                        'dibatalkan' => 'background:var(--color-error-bg); color:var(--color-error); border-color:var(--color-error-border)',
                        default      => 'background:var(--color-surface-muted); color:var(--color-text-muted); border-color:var(--color-border)',
                    };
                ?>
                <span class="text-xs font-semibold px-2 py-0.5 rounded-full border font-ui" style="<?php echo e($statusStyle); ?>">
                    <?php echo e($task->status->label()); ?>

                </span>
                <span class="text-xs font-semibold px-2 py-0.5 rounded-full border font-ui <?php echo e($task->task_type->cardClasses()); ?>">
                    <?php echo e($task->task_type->label()); ?>

                </span>
                <span class="font-mono text-xs text-text-muted"><?php echo e($task->task_number); ?></span>
                <?php if($task->isOverSla()): ?>
                <span class="text-xs font-semibold px-2 py-0.5 rounded-full border font-ui"
                      style="background:var(--color-error-bg); color:var(--color-error); border-color:var(--color-error-border)">
                    Melewati SLA
                </span>
                <?php endif; ?>
            </div>
            <h1 class="text-lg sm:text-xl font-bold text-text-main font-ui"><?php echo e($task->title); ?></h1>
        </div>
        <?php
            $lat = $task->customer?->customerAddress?->latitude ?? $task->pop?->latitude;
            $lng = $task->customer?->customerAddress?->longitude ?? $task->pop?->longitude;
        ?>
        <div class="flex items-center gap-1.5 shrink-0 flex-wrap">
            <?php if($lat && $lng): ?>
            <a href="https://www.google.com/maps/search/?api=1&query=<?php echo e($lat); ?>,<?php echo e($lng); ?>" target="_blank"
               class="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-1.5 border border-border rounded bg-surface hover:bg-surface-muted text-primary transition-colors shadow-sm font-ui cursor-pointer">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                Lokasi Maps
            </a>
            <?php endif; ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('edit', $task)): ?>
            <a href="<?php echo e(route('tasks.edit', $task)); ?>"
               class="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-1.5 border border-border rounded bg-surface hover:bg-surface-muted text-text-secondary transition-colors font-ui cursor-pointer">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                Edit
            </a>
            <?php endif; ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('cancel', $task)): ?>
            <button x-data @click="$dispatch('open-modal', 'cancel-task')"
                    class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1.5 border rounded transition-colors font-ui cursor-pointer"
                    style="border-color:var(--color-error-border); color:var(--color-error); background:var(--color-error-bg)">
                Batalkan
            </button>
            <?php endif; ?>
        </div>
    </div>

    
    <div class="bg-surface border border-border rounded-lg overflow-hidden shadow-sm">
        <div class="grid grid-cols-2 sm:grid-cols-5 divide-y sm:divide-y-0 sm:divide-x divide-border">
            
            <div class="p-3 flex flex-col justify-between">
                <p class="text-[10px] font-semibold uppercase tracking-widest text-text-muted mb-0.5 font-ui">Tipe Task</p>
                <p class="text-xs font-semibold text-text-main font-ui"><?php echo e($task->task_type->label()); ?></p>
            </div>
            
            <div class="p-3 flex flex-col justify-between">
                <p class="text-[10px] font-semibold uppercase tracking-widest text-text-muted mb-0.5 font-ui">Jadwal</p>
                <div>
                    <p class="text-xs font-semibold font-mono text-text-main"><?php echo e($task->scheduled_at?->format('H:i') ?? '—'); ?></p>
                    <p class="text-[10px] text-text-muted font-ui"><?php echo e($task->scheduled_at?->translatedFormat('d M Y') ?? 'Belum dijadwalkan'); ?></p>
                </div>
            </div>
            
            <div class="p-3 flex flex-col justify-between">
                <p class="text-[10px] font-semibold uppercase tracking-widest text-text-muted mb-0.5 font-ui">Target SLA</p>
                <div>
                    <p class="text-xs font-semibold font-mono <?php echo e($task->isOverSla() ? '' : 'text-text-main'); ?>"
                       style="<?php echo e($task->isOverSla() ? 'color:var(--color-error)' : ''); ?>">
                        <?php echo e($task->sla_minutes); ?> Menit
                    </p>
                    <?php if($task->actualDurationMinutes() !== null): ?>
                    <p class="text-[10px] text-text-muted font-mono">Aktual: <?php echo e($task->actualDurationMinutes()); ?> Mnt</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="p-3 flex flex-col justify-between">
                <p class="text-[10px] font-semibold uppercase tracking-widest text-text-muted mb-0.5 font-ui">POP / Cabang</p>
                <p class="text-xs font-semibold text-text-main truncate font-ui"><?php echo e($task->pop?->name ?? '—'); ?></p>
            </div>
            
            <div class="p-3 flex flex-col justify-between col-span-2 sm:col-span-1">
                <p class="text-[10px] font-semibold uppercase tracking-widest text-text-muted mb-0.5 font-ui">Foto Bukti</p>
                <div>
                    <p class="text-xs font-semibold font-mono text-text-main"><?php echo e($task->evidences->count()); ?> Foto</p>
                    <p class="text-[10px] text-text-muted font-ui">Terupload</p>
                </div>
            </div>
        </div>
    </div>

    
    <div class="bg-surface sm:border border-border sm:rounded-lg sm:shadow-sm overflow-hidden">
        <div class="grid grid-cols-1 md:grid-cols-12 divide-y md:divide-y-0 md:divide-x divide-border">
            
            
            <div class="md:col-span-7 p-4 sm:p-5 space-y-4">
                
                
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-widest text-text-muted mb-2 font-ui">Informasi Task</p>
                    <div class="space-y-0.5 text-xs">
                        <div class="flex flex-col sm:flex-row sm:items-start py-2 border-b border-border gap-1 sm:gap-4">
                            <span class="text-text-muted sm:w-36 shrink-0 font-ui">FOP / Koordinator</span>
                            <span class="text-text-main font-medium flex-1 font-ui"><?php echo e($task->fop?->name ?? '—'); ?></span>
                        </div>
                        
                        <div class="flex flex-col sm:flex-row sm:items-start py-2 border-b border-border gap-1 sm:gap-4">
                            <span class="text-text-muted sm:w-36 shrink-0 font-ui">Pelanggan & Kontak</span>
                            <div class="text-text-main font-medium flex-1 font-ui">
                                <?php if($task->customer): ?>
                                <div>
                                    <a href="<?php echo e(route('customers.show', $task->customer)); ?>"
                                       class="hover:underline font-semibold" style="color:var(--color-primary)">
                                        <?php echo e($task->customer->full_name); ?>

                                    </a>
                                    <span class="font-mono text-xs text-text-muted ml-1"><?php echo e($task->customer->display_id); ?></span>
                                </div>
                                <?php if($task->customer->primary_phone): ?>
                                <div class="flex items-center gap-1.5 mt-1 text-[11px]">
                                    <span class="text-text-muted font-mono"><?php echo e($task->customer->primary_phone); ?></span>
                                    <a href="https://wa.me/<?php echo e(preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $task->customer->primary_phone))); ?>" target="_blank"
                                       class="text-emerald-600 dark:text-emerald-400 hover:underline inline-flex items-center gap-0.5 font-semibold cursor-pointer">
                                        <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.768-.001 1.298.409 2.522 1.189 3.518l-.756 2.766 2.831-.744a5.748 5.748 0 002.504.588h.002c3.18 0 5.767-2.586 5.768-5.766 0-1.541-.6-2.99-1.691-4.08-1.091-1.09-2.539-1.69-4.079-1.648zm0 10.153a4.398 4.398 0 01-2.241-.614l-.16-.095-1.666.438.444-1.624-.105-.167a4.394 4.394 0 01-.67-2.326c.001-2.426 1.975-4.4 4.402-4.4 1.177 0 2.283.458 3.115 1.29a4.382 4.382 0 011.29 3.117c-.001 2.426-1.975 4.4-4.409 4.4z"/></svg>
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
                        <div class="flex flex-col sm:flex-row sm:items-start py-2 border-b border-border gap-1 sm:gap-4">
                            <span class="text-text-muted sm:w-36 shrink-0 font-bold text-amber-700 dark:text-amber-400 font-ui">Issue / Keluhan</span>
                            <span class="text-text-main font-semibold leading-relaxed bg-amber-50/60 dark:bg-amber-900/20 border border-amber-200/60 dark:border-amber-800/30 rounded px-2 py-1 flex-1 font-ui"><?php echo e($task->description); ?></span>
                        </div>
                        <?php endif; ?>

                        <?php if($task->customer || $task->pop): ?>
                        <div class="flex flex-col sm:flex-row sm:items-start py-2 border-b border-border gap-1 sm:gap-4">
                            <span class="text-text-muted sm:w-36 shrink-0 font-ui">Alamat & Lokasi</span>
                            <div class="text-text-secondary leading-relaxed flex-1 font-ui">
                                <div>
                                    <?php if($task->customer): ?>
                                        <?php echo e($task->customer->clean_address); ?>

                                    <?php else: ?>
                                        <?php echo e($task->pop?->address ?? '—'); ?> (<?php echo e($task->pop?->name); ?>)
                                    <?php endif; ?>
                                </div>
                                <?php if($lat && $lng): ?>
                                <div class="flex items-center gap-2 mt-1.5 pt-1.5 border-t border-border/50 text-[11px] flex-wrap">
                                    <span class="font-mono text-text-main">Lat: <strong class="text-primary"><?php echo e($lat); ?></strong> | Lng: <strong class="text-primary"><?php echo e($lng); ?></strong></span>
                                    <a href="https://www.google.com/maps/search/?api=1&query=<?php echo e($lat); ?>,<?php echo e($lng); ?>" target="_blank"
                                       class="inline-flex items-center gap-1 font-bold text-primary hover:underline cursor-pointer">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        Maps →
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if($task->pending_reason): ?>
                        <div class="flex flex-col sm:flex-row sm:items-start py-2 border-b border-border gap-1 sm:gap-4">
                            <span class="text-text-muted sm:w-36 shrink-0 font-ui">Alasan Pending</span>
                            <span class="font-semibold flex-1 text-warning font-ui"><?php echo e($task->pending_reason); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                
                <div class="pt-4 border-t border-border">
                    <p class="text-[10px] font-semibold uppercase tracking-widest text-text-muted mb-2 font-ui">Briefing Detail Teknis</p>
                    
                    <?php if($task->task_type === \App\Enums\TaskType::SURVEY): ?>
                    
                    <div class="space-y-0.5 text-xs">
                        <div class="flex flex-col sm:flex-row sm:items-start py-2 border-b border-border gap-1 sm:gap-4">
                            <span class="text-text-muted sm:w-36 shrink-0 font-ui">Status SLA Berjalan</span>
                            <div class="text-text-main text-xs font-medium flex-1">
                                <?php if($task->started_at && !$task->completed_at): ?>
                                    <?php
                                        $elapsed = (int) $task->started_at->diffInMinutes(now());
                                        $remaining = $task->sla_minutes - $elapsed;
                                    ?>
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-bold font-mono <?php echo e($remaining < 0 ? 'text-error' : 'text-primary'); ?>">
                                            <?php echo e($elapsed); ?> menit berjalan
                                        </span>
                                        <span class="text-[10px] text-text-muted font-mono">(<?php echo e($remaining >= 0 ? "Sisa {$remaining} mnt" : "Over SLA " . abs($remaining) . " mnt"); ?>)</span>
                                    </div>
                                <?php elseif($task->status->value === 'terjadwal'): ?>
                                    <span class="text-warning font-ui">Menunggu teknisi klik Mulai Survey (Target SLA: <?php echo e($task->sla_minutes); ?> menit)</span>
                                <?php elseif($task->completed_at): ?>
                                    <span class="text-success font-ui">Selesai dikerjakan dalam <?php echo e($task->actualDurationMinutes()); ?> menit</span>
                                <?php else: ?>
                                    <span class="text-text-muted font-ui"><?php echo e($task->status->label()); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="flex flex-col sm:flex-row sm:items-start py-2 border-b border-border gap-1 sm:gap-4">
                            <span class="text-text-muted sm:w-36 shrink-0 font-ui">Rencana Paket</span>
                            <span class="text-text-main font-semibold flex-1 font-ui">
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
                    <div class="space-y-3">
                        <div>
                            <span class="block text-[9px] font-semibold text-text-muted uppercase mb-1.5 font-ui">Hasil Survey Sebelumnya</span>
                            <?php if($survey): ?>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 text-xs">
                                <div class="border-b sm:border-b-0 sm:border-r border-border pb-1.5 sm:pb-0 sm:pr-2">
                                    <span class="block text-[9px] text-text-muted font-bold uppercase font-ui">ODP Tujuan & Port</span>
                                    <span class="font-bold font-mono text-text-main text-xs mt-0.5 block"><?php echo e($survey->nearest_odp ?: '-'); ?></span>
                                </div>
                                <div class="border-b sm:border-b-0 sm:border-r border-border py-1.5 sm:py-0 sm:px-2">
                                    <span class="block text-[9px] text-text-muted font-bold uppercase font-ui">Estimasi Dropcore</span>
                                    <span class="font-bold font-mono text-text-main text-xs mt-0.5 block"><?php echo e($survey->cable_estimation_meter ? $survey->cable_estimation_meter . ' Meter' : '-'); ?></span>
                                </div>
                                <div class="py-1.5 sm:py-0 sm:pl-2">
                                    <span class="block text-[9px] text-text-muted font-bold uppercase font-ui">Alat Khusus</span>
                                    <span class="font-semibold text-text-main text-xs mt-0.5 block font-ui"><?php echo e($survey->required_tools ?: 'Standar'); ?></span>
                                </div>
                            </div>
                            <?php if($survey->requested_installation_date): ?>
                            
                            <p class="text-[11px] mt-1.5 font-ui font-semibold" style="color:var(--color-info, #0284c7)">
                                Pelanggan meminta dipasang: <?php echo e(\App\Support\IndonesianDate::date($survey->requested_installation_date)); ?>

                            </p>
                            <?php endif; ?>
                            <?php if($survey->survey_note): ?>
                            <p class="text-[11px] text-text-secondary mt-1.5 italic font-ui">"<?php echo e($survey->survey_note); ?>"</p>
                            <?php endif; ?>
                            <?php else: ?>
                            <p class="text-[11px] text-warning font-ui">Data hasil survey sebelumnya belum tercatat di sistem.</p>
                            <?php endif; ?>
                        </div>

                        <?php
                            $estimasiMaterial = $task->customer
                                ? \App\Models\TaskMaterial::where('customer_id', $task->customer->id)->estimasi()->orderBy('id')->get()
                                : collect();
                        ?>
                        <?php if($estimasiMaterial->isNotEmpty()): ?>
                        
                        <div class="pt-3 border-t border-border">
                            <span class="block text-[9px] font-semibold text-text-muted uppercase mb-1.5 font-ui">Estimasi Kebutuhan Alat</span>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1 text-xs">
                                <?php $__currentLoopData = $estimasiMaterial; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $material): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <div class="flex justify-between border-b border-border py-1">
                                    <span class="text-text-secondary font-ui"><?php echo e($material->item_name); ?></span>
                                    <span class="font-mono font-bold text-text-main"><?php echo e(rtrim(rtrim(number_format($material->qty, 2, ',', '.'), '0'), ',')); ?> <?php echo e($material->unit); ?></span>
                                </div>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-3 border-t border-border text-xs">
                            <div>
                                <span class="block text-[9px] text-text-muted font-bold uppercase font-ui">Paket yang Diaktifkan</span>
                                <span class="text-xs font-bold text-primary block mt-0.5 font-ui"><?php echo e($service?->internetPackage?->name ?? $service?->package_name_snapshot ?? '-'); ?></span>
                                <?php if($service?->monthly_price): ?>
                                <span class="text-[10px] text-text-muted font-mono block mt-0.5">Rp <?php echo e(number_format($service->monthly_price, 0, ',', '.')); ?> / bulan</span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <span class="block text-[9px] text-text-muted font-bold uppercase font-ui">Alokasi Perangkat / ONT</span>
                                <?php if($device): ?>
                                    <span class="text-xs font-bold text-text-main block mt-0.5 font-ui"><?php echo e($device->brand); ?> <?php echo e($device->model); ?></span>
                                    <span class="text-[10px] font-mono text-text-muted block mt-0.5 font-ui">SN: <?php echo e($device->serial_number ?: 'Belum diinput'); ?></span>
                                <?php else: ?>
                                    <span class="text-xs text-warning font-medium block mt-0.5 font-ui font-ui">Perangkat ONT akan dicatat saat laporan pemasangan selesai.</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <?php elseif($task->task_type === \App\Enums\TaskType::MAINTENANCE): ?>
                    <?php
                        $tech = $task->customer?->customerTechnicalDetail;
                        $device = $task->customer?->customerDevice;
                    ?>
                    <div class="space-y-3">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 text-xs">
                            <div class="border-b sm:border-b-0 sm:border-r border-border pb-1.5 sm:pb-0 sm:pr-2">
                                <span class="block text-[9px] text-text-muted font-bold uppercase font-ui">ODP & Port Terhubung</span>
                                <span class="font-bold font-mono text-text-main text-xs mt-0.5 block">
                                    <?php echo e($device?->odp ?? $tech?->odp_number ?: '-'); ?> 
                                    <?php if($device?->odp_port || $tech?->odp_port): ?>
                                        (Port <?php echo e($device?->odp_port ?? $tech?->odp_port); ?>)
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="border-b sm:border-b-0 sm:border-r border-border py-1.5 sm:py-0 sm:px-2">
                                <span class="block text-[9px] text-text-muted font-bold uppercase font-ui">OLT & Port OLT</span>
                                <span class="font-bold font-mono text-text-main text-xs mt-0.5 block">
                                    <?php echo e($tech?->olt_number ?: '-'); ?>

                                    <?php if($tech?->olt_port): ?> Port <?php echo e($tech->olt_port); ?> <?php endif; ?>
                                </span>
                            </div>
                            <div class="py-1.5 sm:py-0 sm:pl-2">
                                <span class="block text-[9px] text-text-muted font-bold uppercase font-ui">Redaman RX Power</span>
                                <span class="font-bold font-mono text-text-main text-xs mt-0.5 block">
                                    <?php echo e($device?->signal_rx_power ?? $tech?->initial_attenuation ? ($device?->signal_rx_power ?? $tech?->initial_attenuation) . ' dBm' : '-'); ?>

                                </span>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-3 border-t border-border text-xs">
                            <div>
                                <span class="block text-[9px] text-text-muted font-bold uppercase font-ui">Perangkat Terpasang</span>
                                <span class="font-bold text-text-main text-xs mt-0.5 block font-ui">
                                    <?php echo e($device?->brand ?? 'Modem'); ?> <?php echo e($device?->model); ?>

                                </span>
                                <span class="text-[10px] font-mono text-text-muted mt-0.5 block">
                                    SN: <?php echo e($device?->serial_number ?? $tech?->router_or_ont_serial ?: '-'); ?>

                                </span>
                            </div>
                            <div>
                                <span class="block text-[9px] text-text-muted font-bold uppercase font-ui">PPPoE User / IP Address</span>
                                <span class="font-bold font-mono text-text-main text-xs mt-0.5 block"><?php echo e($device?->pppoe_username ?: '-'); ?></span>
                                <span class="text-[10px] font-mono text-text-muted mt-0.5 block">IP: <?php echo e($device?->ip_address ?? $tech?->ip_address ?: '-'); ?></span>
                            </div>
                        </div>
                    </div>

                    <?php elseif($task->task_type === \App\Enums\TaskType::AMBIL_MODEM): ?>
                    <?php
                        $device = $task->customer?->customerDevice;
                    ?>
                    <div class="space-y-3">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                            <div>
                                <span class="block text-[9px] text-text-muted font-bold uppercase font-ui font-ui">Alasan Deaktivasi</span>
                                <span class="text-xs font-bold text-text-main mt-0.5 block font-ui"><?php echo e($task->description ?: 'Pengambilan Modem'); ?></span>
                            </div>
                            <div>
                                <span class="block text-[9px] text-text-muted font-bold uppercase font-ui">Janji Temu</span>
                                <span class="text-xs font-bold text-text-main font-mono mt-0.5 block"><?php echo e($task->scheduled_at?->translatedFormat('l, d M Y — H:i') ?: 'Segera'); ?> WIB</span>
                            </div>
                        </div>

                        <div class="pt-3 border-t border-border space-y-2">
                            <span class="block text-[9px] text-text-main font-bold uppercase font-ui">Aset ISP yang Wajib Ditarik</span>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                                <div class="bg-background p-2.5 rounded border border-border">
                                    <span class="block text-[9px] text-text-muted font-bold uppercase font-ui">ONT / Modem</span>
                                    <span class="font-bold text-text-main text-xs mt-0.5 block font-ui"><?php echo e($device?->brand ?: 'Modem ONT'); ?> <?php echo e($device?->model); ?></span>
                                    <p class="text-[11px] text-primary font-mono mt-1 font-bold">SN: <?php echo e($device?->serial_number ?: 'PERIKSA FISIK'); ?></p>
                                </div>
                                <div class="bg-background p-2.5 rounded border border-border">
                                    <span class="block text-[9px] text-text-muted font-bold uppercase mb-1 font-ui font-ui">Kelengkapan</span>
                                    <ul class="list-disc list-inside space-y-0.5 text-text-secondary text-[10px] font-ui">
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
                    <div class="space-y-3">
                        <div class="text-xs">
                            <span class="block text-[9px] text-text-muted font-bold uppercase font-ui">Rincian Request</span>
                            <span class="text-xs font-bold text-text-main block mt-0.5 font-ui"><?php echo e($task->description ?: $task->title); ?></span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-3 border-t border-border text-xs">
                            <div>
                                <span class="block text-[9px] text-text-muted font-bold uppercase font-ui">SSID WiFi Eksisting</span>
                                <span class="text-xs font-bold font-mono text-text-main block mt-0.5"><?php echo e($device?->wifi_ssid ?? $tech?->ssid ?: 'Standard / Default'); ?></span>
                            </div>
                            <div>
                                <span class="block text-[9px] text-text-muted font-bold uppercase font-ui">IP Gateway Akses</span>
                                <span class="text-xs font-bold font-mono text-text-main block mt-0.5"><?php echo e($device?->ip_address ?? $tech?->ip_address ?: '192.168.1.1'); ?></span>
                            </div>
                        </div>
                    </div>

                    <?php elseif(in_array($task->task_type, [\App\Enums\TaskType::OREQ, \App\Enums\TaskType::INFR])): ?>
                    <div class="space-y-3 text-xs">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <span class="block text-[9px] text-text-muted font-bold uppercase font-ui">POP Pembina</span>
                                <span class="text-xs font-bold text-text-main mt-0.5 block font-ui"><?php echo e($task->pop?->name ?? 'Pusat'); ?></span>
                            </div>
                            <div>
                                <span class="block text-[9px] text-text-muted font-bold uppercase font-ui">Target / Lokasi</span>
                                <span class="text-xs text-text-main font-semibold mt-0.5 block font-ui"><?php echo e($task->pop?->address ?: 'Infrastruktur POP'); ?></span>
                            </div>
                        </div>
                        <div class="pt-3 border-t border-border">
                            <span class="block text-[9px] text-text-muted font-bold uppercase font-ui font-ui">Instruksi Pekerjaan</span>
                            <span class="text-xs font-semibold text-text-main leading-relaxed mt-0.5 block font-ui"><?php echo e($task->description ?: $task->title); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                
                <?php
                    $workToolRows = app(\App\Services\TaskWorkToolService::class)->displayRowsForTask($task);
                ?>
                <?php if($workToolRows->isNotEmpty()): ?>
                <div class="pt-4 border-t border-border">
                    <p class="text-[10px] font-semibold uppercase tracking-widest text-text-muted font-ui mb-2">Alat Kerja Yang Perlu Dibawa</p>
                    <div class="flex flex-wrap gap-1.5">
                        <?php $__currentLoopData = $workToolRows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold px-2 py-1 rounded border border-border bg-surface-muted text-text-main font-ui">
                            <svg class="h-3 w-3 shrink-0 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                            <?php echo e($row->tool_name); ?><?php if($row->note): ?><span class="font-normal text-text-muted"> · <?php echo e($row->note); ?></span><?php endif; ?>
                        </span>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                </div>
                <?php endif; ?>

                
                <?php if(!in_array($task->task_type->value, [\App\Enums\TaskType::SURVEY->value, \App\Enums\TaskType::PEMASANGAN->value])): ?>
                <div class="pt-4 border-t border-border"
                     x-data="evidenceSection(<?php echo e($task->id); ?>, <?php echo e($task->canComplete() ? 'true' : 'false'); ?>, <?php echo e($task->evidences->count()); ?>)">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-[10px] font-semibold uppercase tracking-widest text-text-muted font-ui">Foto Bukti</p>
                        <span class="text-[10px] font-semibold font-mono text-text-secondary bg-background px-1.5 py-0.5 rounded border border-border" x-text="`${evidenceCount} foto`"></span>
                    </div>
                    
                    
                    <?php if($task->evidences->count() > 0): ?>
                    <div class="grid grid-cols-3 gap-2 mb-3">
                        <?php $__currentLoopData = $task->evidences; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $evidence): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="relative group rounded-md overflow-hidden aspect-square bg-surface-muted border border-border">
                            <img src="<?php echo e(asset('storage/' . $evidence->file_path)); ?>"
                                 alt="<?php echo e($evidence->caption ?? 'Bukti'); ?>"
                                 class="h-full w-full object-cover">
                            <?php if($evidence->caption): ?>
                            <div class="absolute bottom-0 left-0 right-0 px-1.5 py-0.5 text-[9px] text-white truncate"
                                 style="background: rgba(15,23,42,0.7)">
                                <?php echo e($evidence->caption); ?>

                            </div>
                            <?php endif; ?>
                            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('edit', $task)): ?>
                            <button @click="deleteEvidence(<?php echo e($evidence->id); ?>)"
                                    class="absolute top-1 right-1 h-5 w-5 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity text-white cursor-pointer"
                                    style="background:var(--color-error)">
                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                    <?php else: ?>
                    <p class="text-xs text-text-muted mb-3 font-ui">Belum ada foto bukti.</p>
                    <?php endif; ?>

                    
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('uploadEvidence', $task)): ?>
                    <?php if(in_array($task->status->value, ['in_progress', 'pending'])): ?>
                    <label class="block cursor-pointer">
                        <input type="file" accept="image/*" capture="environment" class="hidden" @change="uploadEvidence($event.target)" />
                        <div class="flex items-center justify-center gap-1.5 text-xs font-medium py-2 border border-dashed rounded transition-colors"
                             style="border-color:var(--color-primary-border); color:var(--color-primary)"
                             onmouseover="this.style.background='var(--color-primary-soft)'"
                             onmouseout="this.style.background=''">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            Upload Foto Bukti
                        </div>
                    </label>
                    <p x-show="uploadError" x-text="uploadError" class="text-[11px] mt-1.5 font-ui" style="color:var(--color-error)"></p>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
            </div>
            
            
            <div class="md:col-span-5 p-4 sm:p-5 space-y-4 bg-slate-50/50 dark:bg-slate-800/50">
                
                
                <?php if($task->status->value === 'selesai' && $task->started_at && $task->completed_at): ?>
                <?php
                    $showStartedAt   = $task->started_at;
                    $showCompletedAt = $task->completed_at;
                    $showActualMin   = (int) $showStartedAt->diffInMinutes($showCompletedAt);
                    $showHours       = intdiv($showActualMin, 60);
                    $showRemMins     = $showActualMin % 60;
                    $showDuration    = $showHours > 0
                        ? "{$showHours} jam {$showRemMins} menit"
                        : "{$showActualMin} menit";
                    $showOverSla     = $showActualMin > $task->sla_minutes;
                    $showTypeLabel   = $task->task_type->value === \App\Enums\TaskType::PEMASANGAN->value ? 'Pemasangan' : 'Survey';
                ?>
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-[10px] font-semibold uppercase tracking-widest text-text-muted font-ui">
                            Waktu <?php echo e($showTypeLabel); ?>

                        </p>
                        <span class="text-[9px] font-semibold px-2 py-0.5 rounded-full border font-mono"
                              style="<?php echo e($showOverSla
                                  ? 'background:var(--color-error-bg); color:var(--color-error); border-color:var(--color-error-border)'
                                  : 'background:var(--color-success-bg); color:var(--color-success); border-color:var(--color-success-border)'); ?>">
                            <?php echo e($showOverSla ? 'Over SLA' : 'Dalam SLA'); ?>

                        </span>
                    </div>
                    <div class="bg-surface border border-border rounded p-3 space-y-2 shadow-xs">
                        <div class="flex items-center justify-between text-[11px]">
                            <div>
                                <p class="text-[9px] font-semibold uppercase tracking-widest text-text-muted mb-0.5 font-ui font-ui">Mulai</p>
                                <p class="font-mono font-semibold text-text-main"><?php echo e($showStartedAt->format('H:i')); ?></p>
                                <p class="text-[9px] text-text-muted font-ui"><?php echo e($showStartedAt->translatedFormat('d M Y')); ?></p>
                            </div>
                            <svg class="h-3.5 w-3.5 text-text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.25 8.25L21 12m0 0l-3.75 3.75M21 12H3" />
                            </svg>
                            <div class="text-right">
                                <p class="text-[9px] font-semibold uppercase tracking-widest text-text-muted mb-0.5 font-ui">Selesai</p>
                                <p class="font-mono font-semibold text-text-main"><?php echo e($showCompletedAt->format('H:i')); ?></p>
                                <p class="text-[9px] text-text-muted font-ui"><?php echo e($showCompletedAt->translatedFormat('d M Y')); ?></p>
                            </div>
                        </div>
                        <div class="pt-2 border-t border-border flex items-center justify-between text-[11px]">
                            <div>
                                <p class="text-[9px] font-semibold uppercase tracking-widest text-text-muted font-ui">Durasi Aktual</p>
                                <p class="font-mono font-semibold" style="color:<?php echo e($showOverSla ? 'var(--color-error)' : 'var(--color-success)'); ?>">
                                    <?php echo e($showDuration); ?>

                                </p>
                            </div>
                            <div class="text-right">
                                <p class="text-[9px] font-semibold uppercase tracking-widest text-text-muted font-ui font-ui">Target SLA</p>
                                <p class="font-mono font-semibold text-text-secondary"><?php echo e($task->sla_minutes); ?> menit</p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-widest text-text-muted mb-2 font-ui">Tim Teknisi</p>
                    <?php if($task->teamMembers->count() > 0): ?>
                    <div class="space-y-1.5">
                        <?php $__currentLoopData = $task->teamMembers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $member): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="flex items-center gap-2 bg-surface border border-border rounded px-2.5 py-1.5 w-full shadow-xs">
                            <div class="h-6.5 w-6.5 rounded-full bg-primary-soft flex items-center justify-center text-[10px] font-bold shrink-0"
                                 style="color:var(--color-primary)">
                                <?php echo e(strtoupper(substr($member->user?->name ?? '?', 0, 2))); ?>

                            </div>
                            <div class="flex-1 min-w-0 pr-3">
                                <p class="text-xs font-semibold text-text-main truncate font-ui"><?php echo e($member->user?->name ?? 'User dihapus'); ?></p>
                                <p class="text-[9px] text-text-muted capitalize font-ui"><?php echo e($member->role_in_task); ?></p>
                            </div>
                        </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                    <?php else: ?>
                    <p class="text-xs text-text-muted font-ui">Belum ada anggota tim.</p>
                    <?php endif; ?>
                </div>

                
                <?php if(auth()->user()->hasRole(['owner', 'admin', 'fop'])): ?>
                <div class="pt-4 border-t border-border">
                    <p class="text-[10px] font-semibold uppercase tracking-widest text-text-muted mb-2 font-ui font-ui">Riwayat Status (Audit Log)</p>
                    <?php if($task->auditLogs && $task->auditLogs->count() > 0): ?>
                    <div class="relative border-l border-border ml-2 space-y-3">
                        <?php $__currentLoopData = $task->auditLogs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $log): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="relative pl-3.5">
                            
                            <div class="absolute -left-1 top-1.5 h-2 w-2 rounded-full bg-border border border-surface"></div>
                            <div class="mb-0.5 flex items-center justify-between gap-2">
                                <p class="text-xs font-semibold capitalize text-text-main font-ui">
                                    <?php echo e(str_replace('_', ' ', $log->action)); ?>

                                </p>
                                <span class="text-[9px] text-text-muted font-mono shrink-0"><?php echo e($log->created_at->format('d M Y, H:i')); ?></span>
                            </div>
                            <p class="text-[10px] text-text-muted font-ui">Oleh: <span class="font-medium text-text-secondary"><?php echo e($log->user?->name ?? 'System'); ?></span></p>
                            
                            <?php if($log->action === 'cancelled' && isset($log->new_values['cancel_reason'])): ?>
                            <div class="mt-1 p-1.5 bg-error-bg/20 border border-error-border rounded">
                                <p class="text-[9px] text-error font-medium font-ui">Alasan: <?php echo e($log->new_values['cancel_reason']); ?></p>
                            </div>
                            <?php elseif($log->action === 'rejected' && isset($log->new_values['reject_reason'])): ?>
                            <div class="mt-1 p-1.5 bg-error-bg/20 border border-error-border rounded">
                                <p class="text-[9px] text-error font-medium font-ui">Alasan: <?php echo e($log->new_values['reject_reason']); ?></p>
                            </div>
                            <?php elseif($log->action === 'completed' && isset($log->new_values['status'])): ?>
                            <div class="mt-0.5 text-[9px] text-success font-medium font-ui">Task ditandai selesai oleh teknisi.</div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                    <?php else: ?>
                    <p class="text-xs text-text-muted font-ui">Belum ada riwayat aktivitas.</p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
            </div>
            
        </div>
    </div>

    
    <?php if(in_array($task->status->value, ['terjadwal', 'in_progress', 'pending'])): ?>
    <div class="flex flex-wrap items-center justify-end gap-2.5 pt-1.5 font-ui">
        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('statusReschedule', $task)): ?>
        <button type="button" x-data @click="$dispatch('open-modal', 'reschedule-task-<?php echo e($task->id); ?>')"
                class="inline-flex items-center gap-1.5 text-xs font-semibold px-3.5 py-2 rounded border bg-white dark:bg-slate-800 transition-colors hover:bg-warning/5 cursor-pointer"
                style="border-color:var(--color-warning-border); color:var(--color-warning)">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Pending
        </button>
        <?php endif; ?>
        <?php if($task->status->value === 'terjadwal'): ?>
            <?php if($task->scheduled_at && !$task->scheduled_at->startOfDay()->isFuture()): ?>
            <?php if($task->task_type->value === \App\Enums\TaskType::SURVEY->value): ?>
                <?php if($task->customer_id && auth()->user()->hasPermission('customers.detail.survey.update') && $task->teamMembers->pluck('user_id')->contains(auth()->id())): ?>
                <form action="<?php echo e(route('customers.survey.start', $task->customer_id)); ?>" method="POST">
                    <?php echo csrf_field(); ?>
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 text-xs font-semibold px-4 py-2 rounded text-white transition-colors cursor-pointer"
                            style="background:var(--color-warning)">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                        </svg>
                        Mulai Survey
                    </button>
                </form>
                <?php endif; ?>
            <?php elseif($task->task_type->value === \App\Enums\TaskType::PEMASANGAN->value): ?>
                <?php if($task->customer_id && auth()->user()->hasPermission('customers.detail.installation.update') && $task->teamMembers->pluck('user_id')->contains(auth()->id())): ?>
                <form action="<?php echo e(route('customers.installation.start', $task->customer_id)); ?>" method="POST">
                    <?php echo csrf_field(); ?>
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 text-xs font-semibold px-4 py-2 rounded text-white transition-colors cursor-pointer"
                            style="background:var(--color-warning)">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                        </svg>
                        Mulai Pemasangan
                    </button>
                </form>
                <?php endif; ?>
            <?php else: ?>
                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('statusStart', $task)): ?>
                <form action="<?php echo e(route('tasks.start', $task)); ?>" method="POST">
                    <?php echo csrf_field(); ?>
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 text-xs font-semibold px-4 py-2 rounded text-white transition-colors cursor-pointer"
                            style="background:var(--color-warning)">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                        </svg>
                        <?php echo e($task->task_type->value === \App\Enums\TaskType::MAINTENANCE->value ? 'Mulai Maintenance' : 'Mulai Task'); ?>

                    </button>
                </form>
                <?php endif; ?>
            <?php endif; ?>
            <?php else: ?>
            <span class="text-xs text-text-muted px-3.5 py-2 border border-border rounded bg-surface">
                Dijadwalkan <?php echo e($task->scheduled_at?->translatedFormat('l, d M Y')); ?>

            </span>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('statusComplete', $task)): ?>
        <?php if(in_array($task->status->value, ['in_progress', 'pending'])): ?>
            <?php
                $reportUrl = match(true) {
                    $task->task_type->value === \App\Enums\TaskType::SURVEY->value => route('customers.survey.report', $task->customer_id),
                    $task->task_type->value === \App\Enums\TaskType::PEMASANGAN->value => route('customers.installation.report', $task->customer_id),
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.task.report-choice-dialog','data' => ['task' => $task,'reportUrl' => $reportUrl]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('task.report-choice-dialog'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['task' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($task),'report-url' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($reportUrl)]); ?>
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <?php echo e($reportLabel); ?>

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
                        class="inline-flex items-center gap-1.5 text-xs font-semibold px-4 py-2 rounded text-white transition-colors cursor-pointer"
                        style="background:var(--color-success)">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Lanjutkan Laporan
                </a>
            <?php endif; ?>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    
    <?php if(in_array($task->status->value, ['pending', 'terjadwal'])): ?>
    <?php if(auth()->user()->can('fopReject', $task) || auth()->user()->can('fopPending', $task)): ?>
    <div class="bg-surface border border-border rounded-lg p-4 flex flex-wrap gap-3 items-center justify-between shadow-sm">
        <div>
            <h4 class="text-xs font-semibold text-text-main mb-0.5 font-ui">Manajemen Task (FOP)</h4>
            <p class="text-[11px] text-text-muted font-ui">Kelola task sebelum mulai dikerjakan oleh teknisi.</p>
        </div>
        <div class="flex items-center gap-2">
            <?php if($task->status->value === 'pending'): ?>
                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('fopReject', $task)): ?>
                <button x-data @click="$dispatch('open-modal', 'fop-reject-task-pending')"
                        class="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 border bg-white dark:bg-slate-800 transition-colors hover:bg-error/5 cursor-pointer font-ui"
                        style="border-color:var(--color-error-border); color:var(--color-error)">
                    Reject Task
                </button>
                <?php endif; ?>
            <?php endif; ?>

            <?php if($task->status->value === 'terjadwal'): ?>
                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('fopPending', $task)): ?>
                <button x-data @click="$dispatch('open-modal', 'fop-pending-task')"
                        class="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 border bg-white dark:bg-slate-800 transition-colors hover:bg-warning/5 cursor-pointer font-ui"
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
    <div class="bg-surface border border-border rounded-lg p-4 flex flex-wrap gap-3 items-center justify-between shadow-sm">
        <div>
            <h4 class="text-xs font-semibold text-text-main mb-0.5 font-ui font-ui">Approve Pemasangan Lewat Verifikasi Admin</h4>
            <p class="text-[11px] text-text-muted font-ui">Aktivasi pelanggan (CID + tagihan awal) hanya bisa diproses di halaman Verifikasi Admin, bukan dari sini.</p>
        </div>
        <?php if($task->customer_id): ?>
        <a href="<?php echo e(route('customers.verification.admin', $task->customer_id)); ?>"
           class="inline-flex items-center gap-1.5 text-xs font-semibold px-4 py-2 rounded text-white transition-colors cursor-pointer font-ui"
           style="background:var(--color-primary)">
            Buka Verifikasi Admin
        </a>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="bg-surface border border-border rounded-lg p-4 flex flex-wrap gap-3 items-center justify-between shadow-sm">
        <div>
            <h4 class="text-xs font-semibold text-text-main mb-0.5 font-ui font-ui">Review Hasil & Tandai Selesai (Khusus FOP)</h4>
            <p class="text-[11px] text-text-muted font-ui">Task ini telah diselesaikan oleh teknisi dan menunggu persetujuan Anda.</p>
        </div>
        <div class="flex items-center gap-2">
            <button x-data @click="$dispatch('open-modal', 'reject-task')"
                    class="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 border bg-white dark:bg-slate-800 transition-colors cursor-pointer font-ui"
                    style="border-color:var(--color-error-border); color:var(--color-error)">
                Reject
            </button>
            <form action="<?php echo e(route('tasks.review', $task)); ?>" method="POST">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="approve">
                <button type="submit"
                        class="inline-flex items-center gap-1.5 text-xs font-semibold px-4 py-2 rounded text-white transition-colors cursor-pointer font-ui"
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
    <p class="text-xs text-text-secondary mb-3 font-ui font-ui">
        Task ini akan dikembalikan ke status <span class="font-semibold text-text-main">In Progress</span>. 
        Teknisi harus memperbaiki laporan berdasarkan alasan reject.
    </p>
    <form id="form-reject-task" action="<?php echo e(route('tasks.review', $task)); ?>" method="POST">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="action" value="reject">
        <div class="space-y-1.5 font-ui">
            <label class="block text-[11px] font-semibold uppercase tracking-wider text-text-muted font-ui">Alasan Reject <span class="text-error">*</span></label>
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
    <p class="text-xs text-text-secondary mb-3 font-ui font-ui">
        Task <span class="font-mono font-semibold"><?php echo e($task->task_number); ?></span> akan dibatalkan.
        Tindakan ini tidak dapat dibatalkan.
    </p>
    <form id="form-cancel-task" action="<?php echo e(route('tasks.cancel', $task)); ?>" method="POST">
        <?php echo csrf_field(); ?>
        <div class="space-y-1.5 font-ui">
            <label class="block text-[11px] font-semibold uppercase tracking-wider text-text-muted font-ui">Alasan Pembatalan <span class="text-error">*</span></label>
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
    'evidence_count' => $task->evidences->count(),
    'can_complete' => $task->canComplete(),
    'evidence_url' => route('tasks.evidences.store', $task),
    'submit_url_survey' => route('customers.survey.store', $task),
    'submit_url_install' => route('customers.installation.store', $task),
    'current_package_id' => $task->customer?->customerService?->internet_package_id,
];
?>

<?php $__env->startPush('scripts'); ?>
<script>
function evidenceSection(taskId, initialCanComplete, initialCount) {
    return {
        taskId,
        canComplete:   initialCanComplete,
        evidenceCount: initialCount,
        uploadError:   null,

        async uploadEvidence(input) {
            const file = input.files[0];
            if (!file) return;
            this.uploadError = null;
            const form = new FormData();
            form.append('photo', file);
            form.append('_token', document.querySelector('meta[name="csrf-token"]').content);
            const res  = await fetch(`/tasks/${this.taskId}/evidences`, {
                method: 'POST', body: form, headers: { 'Accept': 'application/json' },
            });
            const data = await res.json();
            if (data.success) {
                this.evidenceCount = data.evidence_count;
                this.canComplete   = data.can_complete;
                window.location.reload();
            } else {
                this.uploadError = 'Gagal upload. Coba lagi.';
            }
            input.value = '';
        },

        deleteEvidence(id) {
            window.Confirm(
                'Hapus Foto Bukti',
                'Apakah Anda yakin ingin menghapus foto bukti ini?',
                'error',
                async () => {
                    const res  = await fetch(`/tasks/${this.taskId}/evidences/${id}`, {
                        method: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                    });
                    const data = await res.json();
                    if (data.success) { this.evidenceCount = data.evidence_count; window.location.reload(); }
                }
            );
        },
    };
}
</script>
<?php $__env->stopPush(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/tasks/show.blade.php ENDPATH**/ ?>