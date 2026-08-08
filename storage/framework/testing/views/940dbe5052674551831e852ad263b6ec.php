<?php $__env->startSection('title', 'Dashboard Analytics NOC — Ticket Service Desk'); ?>
<?php $__env->startSection('page_title', 'Dashboard Analytics NOC'); ?>

<?php $__env->startSection('content'); ?>
<div class="space-y-6 max-w-7xl mx-auto pb-12" x-data="nocDashboardHandler()">

    
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-surface border border-border rounded-xl p-5 shadow-xs">
        <div>
            <div class="flex items-center gap-2">
                <h1 class="text-xl font-black text-text-main tracking-tight">Dashboard Analytics NOC</h1>
                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                    NOC COMMAND CENTER
                </span>
            </div>
            <p class="text-xs text-text-muted mt-1 font-medium">Monitoring realtime antrean, analisa tren issue daerah, dan performa penanganan tiket NOC.</p>
        </div>
        <div class="flex items-center gap-3">
            <button type="button" id="noc-dashboard-refresh-btn" onclick="window.nocDashboardRefresh && window.nocDashboardRefresh()"
                    class="px-3 py-1.5 rounded-lg border border-border bg-slate-50 dark:bg-slate-800 text-xs font-semibold text-text-main hover:bg-slate-100 dark:hover:bg-slate-700 flex items-center gap-1.5 transition-colors cursor-pointer shadow-2xs">
                <svg class="h-3.5 w-3.5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                <span>Refresh Data</span>
            </button>
        </div>
    </div>

    
    <div class="bg-surface border border-border rounded-xl p-4 shadow-xs space-y-4">
        <form method="GET" action="<?php echo e(route('noc.dashboard')); ?>" id="noc-dashboard-filter-form" class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-border/60 pb-3">
                <span class="text-xs font-bold uppercase tracking-wider text-text-muted flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-sky-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                    </svg>
                    Filter & Rentang Analisa
                </span>
                
                
                <div class="flex flex-wrap items-center gap-1.5">
                    <?php
                        $presets = [
                            'month_to_date' => '1 Bulan Berjalan',
                            '7_days' => '7 Hari Terakhir',
                            '30_days' => '30 Hari Terakhir',
                            'this_month' => 'Bulan Ini',
                            'last_month' => 'Bulan Lalu',
                            'all_time' => 'Semua Waktu',
                            'custom' => 'Custom Range',
                        ];
                        $activePreset = $filters['date_preset'] ?? 'month_to_date';
                    ?>
                    <?php $__currentLoopData = $presets; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <button type="button" 
                                onclick="selectPreset('<?php echo e($key); ?>')"
                                class="px-2.5 py-1 text-[11px] font-semibold rounded-md transition-colors cursor-pointer <?php echo e($activePreset === $key ? 'bg-sky-600 text-white shadow-xs' : 'bg-slate-100 dark:bg-slate-800 text-text-muted hover:bg-slate-200 dark:hover:bg-slate-700'); ?>">
                            <?php echo e($label); ?>

                        </button>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    <input type="hidden" name="date_preset" id="input_date_preset" value="<?php echo e($activePreset); ?>">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">
                
                <div>
                    <label class="block text-[11px] font-bold text-text-muted uppercase mb-1">POP / Cabang</label>
                    <select name="pop_id" class="w-full text-xs font-semibold bg-background border border-border rounded-lg px-3 py-2 text-text-main focus:ring-2 focus:ring-sky-500 focus:outline-none">
                        <option value="">Semua POP Cabang</option>
                        <?php $__currentLoopData = $allowedPops; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pop): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($pop->id); ?>" <?php echo e((string)$filters['pop_id'] === (string)$pop->id ? 'selected' : ''); ?>>
                                <?php echo e($pop->name); ?> (<?php echo e($pop->code); ?>)
                            </option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>

                
                <div>
                    <label class="block text-[11px] font-bold text-text-muted uppercase mb-1">Dari Tanggal</label>
                    <input type="date" name="date_from" value="<?php echo e($filters['date_from']); ?>" 
                           class="w-full text-xs font-medium bg-background border border-border rounded-lg px-3 py-2 text-text-main focus:ring-2 focus:ring-sky-500 focus:outline-none">
                </div>

                
                <div>
                    <label class="block text-[11px] font-bold text-text-muted uppercase mb-1">Sampai Tanggal</label>
                    <input type="date" name="date_to" value="<?php echo e($filters['date_to']); ?>" 
                           class="w-full text-xs font-medium bg-background border border-border rounded-lg px-3 py-2 text-text-main focus:ring-2 focus:ring-sky-500 focus:outline-none">
                </div>

                
                <div class="flex items-center gap-2">
                    <button type="submit" class="flex-1 bg-sky-600 hover:bg-sky-700 text-white text-xs font-bold px-4 py-2 rounded-lg transition-colors shadow-xs flex items-center justify-center gap-1.5 cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        Terapkan Filter
                    </button>
                    <?php if($filters['pop_id'] || $filters['date_preset'] !== 'month_to_date' || $filters['date_from'] || $filters['date_to']): ?>
                        <a href="<?php echo e(route('noc.dashboard')); ?>" class="px-3 py-2 bg-slate-100 dark:bg-slate-800 text-xs font-semibold text-text-muted hover:text-text-main rounded-lg transition-colors cursor-pointer">
                            Reset
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    
    <div id="stat-cards-container" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3.5">
        
        <div class="bg-surface border border-border rounded-xl p-4 shadow-xs relative overflow-hidden group hover:border-sky-500/50 transition-all">
            <div class="flex items-center justify-between text-text-muted">
                <span class="text-[10px] font-extrabold uppercase tracking-wider">Total Tiket</span>
                <div class="w-7 h-7 rounded-lg bg-sky-500/10 text-sky-600 dark:text-sky-400 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                </div>
            </div>
            <p class="text-2xl font-black mt-2 text-text-main font-mono"><?php echo e(number_format($stats['total_ticket'])); ?></p>
            <p class="text-[10px] text-text-muted mt-1">Pada periode terpilih</p>
            <div class="h-1 w-full bg-sky-500/20 rounded-full mt-3 overflow-hidden">
                <div class="h-full bg-sky-500 rounded-full w-full"></div>
            </div>
        </div>

        
        <div class="bg-surface border border-border rounded-xl p-4 shadow-xs relative overflow-hidden group hover:border-emerald-500/50 transition-all">
            <div class="flex items-center justify-between text-text-muted">
                <span class="text-[10px] font-extrabold uppercase tracking-wider">Tiket Selesai</span>
                <div class="w-7 h-7 rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
            <p class="text-2xl font-black mt-2 text-emerald-600 dark:text-emerald-400 font-mono"><?php echo e(number_format($stats['ticket_selesai'])); ?></p>
            <p class="text-[10px] text-text-muted mt-1">
                <?php echo e($stats['total_ticket'] > 0 ? round(($stats['ticket_selesai'] / $stats['total_ticket']) * 100, 1) : 0); ?>% dari total tiket
            </p>
            <div class="h-1 w-full bg-emerald-500/20 rounded-full mt-3 overflow-hidden">
                <div class="h-full bg-emerald-500 rounded-full" style="width: <?php echo e($stats['total_ticket'] > 0 ? ($stats['ticket_selesai'] / $stats['total_ticket']) * 100 : 0); ?>%"></div>
            </div>
        </div>

        
        <div class="bg-surface border border-border rounded-xl p-4 shadow-xs relative overflow-hidden group hover:border-indigo-500/50 transition-all">
            <div class="flex items-center justify-between text-text-muted">
                <span class="text-[10px] font-extrabold uppercase tracking-wider">Assign FOP</span>
                <div class="w-7 h-7 rounded-lg bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                </div>
            </div>
            <p class="text-2xl font-black mt-2 text-indigo-600 dark:text-indigo-400 font-mono"><?php echo e(number_format($stats['ticket_assign_fop'])); ?></p>
            <p class="text-[10px] text-text-muted mt-1">
                <?php echo e($stats['total_ticket'] > 0 ? round(($stats['ticket_assign_fop'] / $stats['total_ticket']) * 100, 1) : 0); ?>% eskalasi lapangan
            </p>
            <div class="h-1 w-full bg-indigo-500/20 rounded-full mt-3 overflow-hidden">
                <div class="h-full bg-indigo-500 rounded-full" style="width: <?php echo e($stats['total_ticket'] > 0 ? ($stats['ticket_assign_fop'] / $stats['total_ticket']) * 100 : 0); ?>%"></div>
            </div>
        </div>

        
        <div class="bg-surface border border-border rounded-xl p-4 shadow-xs relative overflow-hidden group hover:border-rose-500/50 transition-all">
            <div class="flex items-center justify-between text-text-muted">
                <span class="text-[10px] font-extrabold uppercase tracking-wider">Dibatalkan</span>
                <div class="w-7 h-7 rounded-lg bg-rose-500/10 text-rose-600 dark:text-rose-400 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
            <p class="text-2xl font-black mt-2 text-rose-600 dark:text-rose-400 font-mono"><?php echo e(number_format($stats['ticket_dibatalkan'])); ?></p>
            <p class="text-[10px] text-text-muted mt-1">
                <?php echo e($stats['total_ticket'] > 0 ? round(($stats['ticket_dibatalkan'] / $stats['total_ticket']) * 100, 1) : 0); ?>% dibatalkan
            </p>
            <div class="h-1 w-full bg-rose-500/20 rounded-full mt-3 overflow-hidden">
                <div class="h-full bg-rose-500 rounded-full" style="width: <?php echo e($stats['total_ticket'] > 0 ? ($stats['ticket_dibatalkan'] / $stats['total_ticket']) * 100 : 0); ?>%"></div>
            </div>
        </div>

        
        <div class="bg-surface border border-border rounded-xl p-4 shadow-xs relative overflow-hidden group hover:border-amber-500/50 transition-all col-span-2 sm:col-span-1">
            <div class="flex items-center justify-between text-text-muted">
                <span class="text-[10px] font-extrabold uppercase tracking-wider">Avg. Durasi NOC</span>
                <div class="w-7 h-7 rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
            <p class="text-xl font-black mt-2 text-amber-600 dark:text-amber-400 font-mono truncate"><?php echo e($stats['avg_duration_label']); ?></p>
            <p class="text-[10px] text-text-muted mt-1">Durasi di meja ticketing</p>
            <div class="h-1 w-full bg-amber-500/20 rounded-full mt-3 overflow-hidden">
                <div class="h-full bg-amber-500 rounded-full w-3/4"></div>
            </div>
        </div>
    </div>

    
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        
        <div id="region-stats-container" class="bg-surface border border-border rounded-xl overflow-hidden shadow-xs flex flex-col">
            <div class="px-5 py-3.5 border-b border-border bg-slate-50/50 dark:bg-slate-900/40 flex items-center justify-between">
                <h2 class="text-xs font-black uppercase tracking-wider text-text-main flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-sky-500"></span>
                    Statistik Tiket per Daerah (POP / Kec)
                </h2>
                <span class="text-[10px] font-bold text-text-muted bg-slate-200/60 dark:bg-slate-800 px-2 py-0.5 rounded">Top <?php echo e(count($regionStats)); ?></span>
            </div>
            <div class="p-5 space-y-3.5 flex-1">
                <?php $__empty_1 = true; $__currentLoopData = $regionStats; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $regionName => $data): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <div>
                        <div class="flex items-center justify-between text-xs mb-1 font-semibold">
                            <span class="text-text-main truncate max-w-[200px]"><?php echo e($regionName); ?></span>
                            <div class="flex items-center gap-2 shrink-0">
                                <span class="text-text-muted text-[11px] font-mono"><?php echo e($data['percentage']); ?>%</span>
                                <span class="font-mono font-bold text-sky-600 dark:text-sky-400 bg-sky-500/10 px-2 py-0.5 rounded text-[11px]"><?php echo e($data['count']); ?> Tiket</span>
                            </div>
                        </div>
                        <div class="h-2 w-full bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden">
                            <div class="h-full bg-gradient-to-r from-sky-500 to-indigo-500 rounded-full transition-all duration-500" style="width: <?php echo e($data['percentage']); ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <div class="py-8 text-center text-xs text-text-muted">Tidak ada data tiket untuk periode terpilih.</div>
                <?php endif; ?>
            </div>
        </div>

        
        <div id="issue-stats-container" class="bg-surface border border-border rounded-xl overflow-hidden shadow-xs flex flex-col">
            <div class="px-5 py-3.5 border-b border-border bg-slate-50/50 dark:bg-slate-900/40 flex items-center justify-between">
                <h2 class="text-xs font-black uppercase tracking-wider text-text-main flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                    Statistik Tiket per Kategori Issue
                </h2>
                <span class="text-[10px] font-bold text-text-muted bg-slate-200/60 dark:bg-slate-800 px-2 py-0.5 rounded">Top <?php echo e(count($issueStats)); ?></span>
            </div>
            <div class="p-5 space-y-3.5 flex-1">
                <?php $__empty_1 = true; $__currentLoopData = $issueStats; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $issueName => $data): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <div>
                        <div class="flex items-center justify-between text-xs mb-1 font-semibold">
                            <span class="text-text-main truncate max-w-[200px]"><?php echo e($issueName); ?></span>
                            <div class="flex items-center gap-2 shrink-0">
                                <span class="text-text-muted text-[11px] font-mono"><?php echo e($data['percentage']); ?>%</span>
                                <span class="font-mono font-bold text-amber-600 dark:text-amber-400 bg-amber-500/10 px-2 py-0.5 rounded text-[11px]"><?php echo e($data['count']); ?> Tiket</span>
                            </div>
                        </div>
                        <div class="h-2 w-full bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden">
                            <div class="h-full bg-gradient-to-r from-amber-500 to-rose-500 rounded-full transition-all duration-500" style="width: <?php echo e($data['percentage']); ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <div class="py-8 text-center text-xs text-text-muted">Tidak ada data issue untuk periode terpilih.</div>
                <?php endif; ?>
            </div>
        </div>

        
        <div id="trend-matrix-container" class="bg-surface border border-border rounded-xl overflow-hidden shadow-xs lg:col-span-2">
            <div class="px-5 py-3.5 border-b border-border bg-slate-50/50 dark:bg-slate-900/40 flex items-center justify-between">
                <div>
                    <h2 class="text-xs font-black uppercase tracking-wider text-text-main flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-rose-500 animate-ping"></span>
                        Tren Matriks: Daerah dengan Issue Terbanyak (Hotspot Trend)
                    </h2>
                    <p class="text-[11px] text-text-muted mt-0.5">Pemetaan konsentrasi keluhan untuk deteksi dini daerah bermasalah.</p>
                </div>
                <span class="text-[10px] font-bold text-rose-600 dark:text-rose-400 bg-rose-500/10 px-2 py-1 rounded border border-rose-500/20">
                    HEATMAP MATRIKS
                </span>
            </div>

            <div class="p-4 overflow-x-auto">
                <?php if(count($trendMatrix['regions']) > 0 && count($trendMatrix['issues']) > 0): ?>
                    <table class="w-full text-xs text-left border-collapse">
                        <thead>
                            <tr class="border-b border-border">
                                <th class="p-2.5 font-extrabold text-text-muted uppercase text-[10px] bg-slate-50/50 dark:bg-slate-900/50 rounded-tl-lg">Daerah / POP</th>
                                <?php $__currentLoopData = $trendMatrix['issues']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $issueName): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <th class="p-2.5 font-extrabold text-text-muted uppercase text-[10px] text-center max-w-[120px] truncate" title="<?php echo e($issueName); ?>">
                                        <?php echo e($issueName); ?>

                                    </th>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/60">
                            <?php $__currentLoopData = $trendMatrix['regions']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $regionName): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-900/30 transition-colors">
                                    <td class="p-2.5 font-bold text-text-main truncate max-w-[180px]" title="<?php echo e($regionName); ?>">
                                        <?php echo e($regionName); ?>

                                    </td>
                                    <?php $__currentLoopData = $trendMatrix['issues']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $issueName): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <?php
                                            $cnt = $trendMatrix['matrix'][$regionName][$issueName] ?? 0;
                                            $maxC = max(1, $trendMatrix['maxCount']);
                                            $ratio = $cnt / $maxC;
                                        ?>
                                        <td class="p-2 text-center">
                                            <?php if($cnt > 0): ?>
                                                <span class="inline-block px-2.5 py-1 rounded-md font-mono font-bold text-xs transition-transform hover:scale-105 <?php echo e($ratio >= 0.7 ? 'bg-rose-500 text-white shadow-xs' : ($ratio >= 0.35 ? 'bg-amber-500/20 text-amber-700 dark:text-amber-300 border border-amber-500/30' : 'bg-slate-100 dark:bg-slate-800 text-text-main')); ?>">
                                                    <?php echo e($cnt); ?>

                                                </span>
                                            <?php else: ?>
                                                <span class="text-text-muted/40 font-mono text-[11px]">-</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="py-8 text-center text-xs text-text-muted">Tidak cukup data untuk menampilkan matriks tren issue daerah.</div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        
        <div id="active-tickets-container" class="bg-surface border border-border rounded-xl overflow-hidden shadow-xs lg:col-span-2">
            <div class="px-5 py-3.5 border-b border-border bg-slate-50/50 dark:bg-slate-900/40 flex items-center justify-between">
                <div>
                    <h2 class="text-xs font-black uppercase tracking-wider text-text-main flex items-center gap-2">
                        <svg class="w-4 h-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Tiket Aktif NOC (Paling Lama Menunggu di Atas)
                    </h2>
                    <p class="text-[11px] text-text-muted mt-0.5">Urutan berdasarkan prioritas aging (tiket yang paling lama diproses NOC di posisi teratas).</p>
                </div>
                <span class="text-[10px] font-bold text-amber-600 dark:text-amber-400 bg-amber-500/10 px-2.5 py-1 rounded-full border border-amber-500/20">
                    <?php echo e(count($activeTickets)); ?> Tiket Aktif
                </span>
            </div>

            <div class="divide-y divide-border max-h-[520px] overflow-y-auto">
                <?php $__empty_1 = true; $__currentLoopData = $activeTickets; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ticket): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <?php
                        $waitingHours = $ticket->created_at->diffInHours(now());
                        $agingBadgeClass = 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20';
                        if ($waitingHours >= 24) {
                            $agingBadgeClass = 'bg-rose-500/15 text-rose-600 dark:text-rose-400 border-rose-500/30 animate-pulse font-extrabold';
                        } elseif ($waitingHours >= 8) {
                            $agingBadgeClass = 'bg-amber-500/15 text-amber-600 dark:text-amber-400 border-amber-500/30 font-bold';
                        }
                    ?>
                    <div class="p-4 flex items-center justify-between gap-3 hover:bg-slate-50/80 dark:hover:bg-slate-900/40 transition-colors cursor-pointer"
                         onclick="window.dispatchEvent(new CustomEvent('open-ticket-drawer', { detail: { ticketId: <?php echo e($ticket->id); ?> } }))">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 text-xs flex-wrap">
                                <span class="font-mono font-bold text-sky-600 dark:text-sky-400">#<?php echo e($ticket->ticket_number); ?></span>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded border <?php echo e($ticket->statusBadgeClasses()); ?>"><?php echo e($ticket->statusLabel()); ?></span>
                                <?php if($ticket->priority): ?>
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded border bg-slate-100 dark:bg-slate-800 text-text-muted"><?php echo e($ticket->priority->value); ?></span>
                                <?php endif; ?>
                                <span class="text-[10px] font-medium text-text-muted">POP: <?php echo e($ticket->pop->name ?? '—'); ?></span>
                            </div>
                            <p class="text-xs font-extrabold text-text-main truncate mt-1">
                                <?php echo e($ticket->customer->full_name ?? $ticket->customer_name ?? '—'); ?>

                                <?php if($ticket->customer?->cid): ?>
                                    <span class="font-mono text-text-muted text-[11px] font-normal">(<?php echo e($ticket->customer->cid); ?>)</span>
                                <?php endif; ?>
                            </p>
                            <p class="text-[11px] text-text-muted mt-0.5 truncate">
                                <span class="font-semibold text-text-secondary"><?php echo e($ticket->issueCategory?->name ?? 'Issue'); ?>:</span>
                                <?php echo e(\Illuminate\Support\Str::limit($ticket->detail_keluhan, 75)); ?>

                            </p>
                        </div>

                        <div class="shrink-0 text-right flex flex-col items-end gap-1">
                            <span class="text-[10px] px-2.5 py-1 rounded-md border font-mono <?php echo e($agingBadgeClass); ?>">
                                Menunggu <?php echo e($ticket->created_at->diffForHumans(null, true)); ?>

                            </span>
                            <span class="text-[10px] text-text-muted">Oleh: <?php echo e($ticket->creator->name ?? '—'); ?></span>
                        </div>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <div class="p-12 text-center text-xs text-text-muted flex flex-col items-center justify-center gap-2">
                        <svg class="w-8 h-8 text-emerald-500/50" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Tidak ada tiket aktif yang menggantung di NOC.</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        
        <div id="activity-feed-container" class="bg-surface border border-border rounded-xl overflow-hidden shadow-xs flex flex-col">
            <div class="px-5 py-3.5 border-b border-border bg-slate-50/50 dark:bg-slate-900/40 flex items-center justify-between">
                <h2 class="text-xs font-black uppercase tracking-wider text-text-main flex items-center gap-2">
                    <svg class="w-4 h-4 text-sky-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                    Aktivitas Terbaru
                </h2>
                <span class="text-[10px] font-bold text-text-muted bg-slate-200/60 dark:bg-slate-800 px-2 py-0.5 rounded">Log Live</span>
            </div>
            <ul class="divide-y divide-border max-h-[520px] overflow-y-auto p-2">
                <?php $__empty_1 = true; $__currentLoopData = $activityFeed; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $history): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <li class="p-3 text-xs hover:bg-slate-50/60 dark:hover:bg-slate-900/30 rounded-lg transition-colors">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-mono font-extrabold text-sky-600 dark:text-sky-400">#<?php echo e($history->ticket->ticket_number ?? '—'); ?></span>
                            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded border <?php echo e($history->action->badgeClasses()); ?>"><?php echo e($history->action->label()); ?></span>
                        </div>
                        <p class="text-text-muted mt-1 text-[11px]">
                            Oleh <span class="font-semibold text-text-main"><?php echo e($history->actor->name ?? 'Sistem'); ?></span>
                        </p>
                        <p class="text-[10px] text-text-muted/80 mt-0.5 font-mono">
                            <?php echo e(\App\Support\IndonesianDate::dateTime($history->happened_at)); ?>

                        </p>
                    </li>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <li class="p-8 text-center text-xs text-text-muted">Belum ada aktivitas tercatat.</li>
                <?php endif; ?>
            </ul>
        </div>

    </div>

</div>


<?php echo $__env->make('tickets.partials.detail-drawer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
    function selectPreset(preset) {
        document.getElementById('input_date_preset').value = preset;
        document.getElementById('noc-dashboard-filter-form').submit();
    }

    function nocDashboardHandler() {
        return {
            init() {
                if (window.initNocDashboardEcho) {
                    window.initNocDashboardEcho();
                }
            }
        };
    }

    (function () {
        const allowedPopIds = <?php echo json_encode($allowedPopIds ?? [], 15, 512) ?>;
        let refreshing = false;

        async function refetchAndSwap() {
            if (refreshing) return;
            refreshing = true;
            const btn = document.getElementById('noc-dashboard-refresh-btn');
            btn?.classList.add('opacity-50');
            try {
                const res = await fetch(window.location.href, { headers: { 'Accept': 'text/html' } });
                const html = await res.text();
                const doc = new DOMParser().parseFromString(html, 'text/html');

                ['stat-cards-container', 'region-stats-container', 'issue-stats-container', 'trend-matrix-container', 'active-tickets-container', 'activity-feed-container'].forEach(id => {
                    const el = document.getElementById(id);
                    const newEl = doc.getElementById(id);
                    if (el && newEl) el.innerHTML = newEl.innerHTML;
                });
            } catch (e) {
                // Silently handle error
            } finally {
                refreshing = false;
                btn?.classList.remove('opacity-50');
            }
        }

        window.nocDashboardRefresh = refetchAndSwap;

        window.initNocDashboardEcho = function () {
            let attempts = 0;
            const setupEcho = () => {
                if (typeof window.Echo === 'undefined' || !window.Echo) {
                    attempts++;
                    if (attempts < 20) setTimeout(setupEcho, 100);
                    return;
                }
                allowedPopIds.forEach(popId => {
                    window.Echo.private(`tickets.${popId}`).listen('.TicketQueueUpdated', () => refetchAndSwap());
                });
            };
            setupEcho();
        };
    })();
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/noc/dashboard.blade.php ENDPATH**/ ?>