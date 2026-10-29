<?php $__env->startSection('title', 'Dashboard - Whusnet Operasional'); ?>
<?php $__env->startSection('page_title', 'Operational Command Center'); ?>
<?php $__env->startSection('meta_description', 'Dashboard Operational Command Center ISP Whusnet - Monitoring Pelanggan, Finansial, dan Jaringan secara Real-time'); ?>

<?php $__env->startSection('content'); ?>
<?php
    $currency = fn ($value) => 'Rp ' . number_format((float) $value, 0, ',', '.');
    $percent = fn ($value, $total) => number_format(((int) $value / max(1, (int) $total)) * 100, 1) . '%';
    $statusLabels = [
        'draft' => 'Draft',
        'perlu_dilengkapi' => 'Perlu Dilengkapi',
        'lengkap' => 'Lengkap',
        'siap_billing' => 'Siap Billing',
        'belum_dibayar' => 'Belum Dibayar',
        'sebagian' => 'Dibayar Sebagian',
        'lunas' => 'Lunas',
        'batal' => 'Batal',
        'tak_tertagih' => 'Tak Tertagih',
    ];

    $pulse = $stats['operational_pulse'] ?? [
        'finance' => ['status' => 'good', 'label' => 'Sehat'],
        'network' => ['status' => 'good', 'label' => 'Normal'],
        'field' => ['status' => 'good', 'label' => 'Lancar'],
        'warehouse' => ['status' => 'good', 'label' => 'Aman'],
        'overall' => ['status' => 'good', 'label' => 'Sistem Prima'],
    ];

    $pulseBadgeClass = function (string $status) {
        return match ($status) {
            'good' => 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-500/30',
            'warning' => 'bg-amber-500/10 text-amber-700 dark:text-amber-300 border-amber-500/30',
            'danger' => 'bg-rose-500/10 text-rose-700 dark:text-rose-300 border-rose-500/30',
            default => 'bg-slate-500/10 text-slate-700 dark:text-slate-300 border-slate-500/30',
        };
    };

    $pulseDotClass = function (string $status) {
        return match ($status) {
            'good' => 'bg-emerald-500',
            'warning' => 'bg-amber-500',
            'danger' => 'bg-rose-500 animate-pulse',
            default => 'bg-slate-400',
        };
    };
?>

<div x-data="{ activeTab: 'all' }" class="space-y-6">

    <!-- ── TOP BAR: FILTER & OPERATIONAL PULSE ────────────────────────── -->
    <div class="rounded-2xl border border-border bg-surface p-4 lg:p-5 shadow-xs">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 border-b border-border pb-4 mb-4">
            <div>
                <div class="flex items-center gap-2.5">
                    <span class="flex h-3 w-3 relative">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full <?php echo e($pulseDotClass($pulse['overall']['status'])); ?> opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3 <?php echo e($pulseDotClass($pulse['overall']['status'])); ?>"></span>
                    </span>
                    <h2 class="text-base font-bold text-text-main tracking-tight">Executive Operational Pulse</h2>
                    <span class="text-xs px-2.5 py-0.5 rounded-full font-semibold border <?php echo e($pulseBadgeClass($pulse['overall']['status'])); ?>">
                        <?php echo e($pulse['overall']['label']); ?>

                    </span>
                </div>
                <p class="text-xs text-text-muted mt-1">Status agregat seluruh divisi ISP Whusnet secara terpadu real-time.</p>
            </div>

            <!-- Health Telemetry Pills -->
            <div class="flex flex-wrap items-center gap-2">
                <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl border text-xs font-medium <?php echo e($pulseBadgeClass($pulse['finance']['status'])); ?>">
                    <span class="w-1.5 h-1.5 rounded-full <?php echo e($pulseDotClass($pulse['finance']['status'])); ?>"></span>
                    <span>Finansial: <strong><?php echo e($pulse['finance']['label']); ?></strong></span>
                </div>
                <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl border text-xs font-medium <?php echo e($pulseBadgeClass($pulse['network']['status'])); ?>">
                    <span class="w-1.5 h-1.5 rounded-full <?php echo e($pulseDotClass($pulse['network']['status'])); ?>"></span>
                    <span>Jaringan: <strong><?php echo e($pulse['network']['label']); ?></strong></span>
                </div>
                <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl border text-xs font-medium <?php echo e($pulseBadgeClass($pulse['field']['status'])); ?>">
                    <span class="w-1.5 h-1.5 rounded-full <?php echo e($pulseDotClass($pulse['field']['status'])); ?>"></span>
                    <span>Lapangan: <strong><?php echo e($pulse['field']['label']); ?></strong></span>
                </div>
                <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl border text-xs font-medium <?php echo e($pulseBadgeClass($pulse['warehouse']['status'])); ?>">
                    <span class="w-1.5 h-1.5 rounded-full <?php echo e($pulseDotClass($pulse['warehouse']['status'])); ?>"></span>
                    <span>Logistik: <strong><?php echo e($pulse['warehouse']['label']); ?></strong></span>
                </div>
            </div>
        </div>

        <!-- Filter Controls -->
        <form method="GET" action="<?php echo e(route('dashboard')); ?>" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">
            <div>
                <label for="pop_id" class="block text-xs font-semibold text-text-secondary mb-1">Filter Cabang / POP</label>
                <?php if (isset($component)) { $__componentOriginal231e2c645bf8af0c5c05a5dc5a94c862 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal231e2c645bf8af0c5c05a5dc5a94c862 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.select','data' => ['name' => 'pop_id','id' => 'pop_id','class' => 'w-full']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.select'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'pop_id','id' => 'pop_id','class' => 'w-full']); ?>
                    <option value="">Semua Cabang / POP dalam Scope</option>
                    <?php $__currentLoopData = $pops; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pop): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($pop->id); ?>" <?php if((string) $filters['pop_id'] === (string) $pop->id): echo 'selected'; endif; ?>>
                            <?php echo e($pop->name); ?>

                        </option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal231e2c645bf8af0c5c05a5dc5a94c862)): ?>
<?php $attributes = $__attributesOriginal231e2c645bf8af0c5c05a5dc5a94c862; ?>
<?php unset($__attributesOriginal231e2c645bf8af0c5c05a5dc5a94c862); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal231e2c645bf8af0c5c05a5dc5a94c862)): ?>
<?php $component = $__componentOriginal231e2c645bf8af0c5c05a5dc5a94c862; ?>
<?php unset($__componentOriginal231e2c645bf8af0c5c05a5dc5a94c862); ?>
<?php endif; ?>
            </div>

            <div>
                <label for="period_from" class="block text-xs font-semibold text-text-secondary mb-1">Periode Dari</label>
                <?php if (isset($component)) { $__componentOriginal65bd7e7dbd93cec773ad6501ce127e46 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal65bd7e7dbd93cec773ad6501ce127e46 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.input','data' => ['type' => 'month','name' => 'period_from','id' => 'period_from','value' => ''.e($filters['period_from']).'']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'month','name' => 'period_from','id' => 'period_from','value' => ''.e($filters['period_from']).'']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal65bd7e7dbd93cec773ad6501ce127e46)): ?>
<?php $attributes = $__attributesOriginal65bd7e7dbd93cec773ad6501ce127e46; ?>
<?php unset($__attributesOriginal65bd7e7dbd93cec773ad6501ce127e46); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal65bd7e7dbd93cec773ad6501ce127e46)): ?>
<?php $component = $__componentOriginal65bd7e7dbd93cec773ad6501ce127e46; ?>
<?php unset($__componentOriginal65bd7e7dbd93cec773ad6501ce127e46); ?>
<?php endif; ?>
            </div>

            <div>
                <label for="period_to" class="block text-xs font-semibold text-text-secondary mb-1">Periode Sampai</label>
                <?php if (isset($component)) { $__componentOriginal65bd7e7dbd93cec773ad6501ce127e46 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal65bd7e7dbd93cec773ad6501ce127e46 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.input','data' => ['type' => 'month','name' => 'period_to','id' => 'period_to','value' => ''.e($filters['period_to']).'']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.input'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'month','name' => 'period_to','id' => 'period_to','value' => ''.e($filters['period_to']).'']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal65bd7e7dbd93cec773ad6501ce127e46)): ?>
<?php $attributes = $__attributesOriginal65bd7e7dbd93cec773ad6501ce127e46; ?>
<?php unset($__attributesOriginal65bd7e7dbd93cec773ad6501ce127e46); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal65bd7e7dbd93cec773ad6501ce127e46)): ?>
<?php $component = $__componentOriginal65bd7e7dbd93cec773ad6501ce127e46; ?>
<?php unset($__componentOriginal65bd7e7dbd93cec773ad6501ce127e46); ?>
<?php endif; ?>
            </div>

            <div class="flex gap-2">
                <?php if (isset($component)) { $__componentOriginala8bb031a483a05f647cb99ed3a469847 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala8bb031a483a05f647cb99ed3a469847 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.button','data' => ['type' => 'submit','variant' => 'primary','class' => 'w-full justify-center']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'submit','variant' => 'primary','class' => 'w-full justify-center']); ?>
                    Terapkan Filter
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
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.button','data' => ['type' => 'button','variant' => 'secondary','tag' => 'a','href' => ''.e(route('dashboard')).'','class' => 'w-full justify-center text-center']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.button'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['type' => 'button','variant' => 'secondary','tag' => 'a','href' => ''.e(route('dashboard')).'','class' => 'w-full justify-center text-center']); ?>
                    Reset
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
            </div>
        </form>

        <!-- View Switcher Tabs -->
        <div class="mt-4 pt-3 border-t border-border flex items-center justify-between flex-wrap gap-2">
            <span class="text-xs font-semibold text-text-muted">Fokus Sudut Pandang:</span>
            <div class="flex flex-wrap gap-1.5 bg-surface-muted p-1 rounded-xl">
                <button type="button" @click="activeTab = 'all'" :class="activeTab === 'all' ? 'bg-surface text-sky-700 dark:text-sky-400 shadow-xs font-semibold' : 'text-slate-700 dark:text-slate-300 hover:text-text-main font-medium'" class="px-3 py-1.5 rounded-lg text-xs transition-all duration-150 cursor-pointer">
                    Semua Modul (360°)
                </button>
                <button type="button" @click="activeTab = 'finance'" :class="activeTab === 'finance' ? 'bg-surface text-sky-700 dark:text-sky-400 shadow-xs font-semibold' : 'text-slate-700 dark:text-slate-300 hover:text-text-main font-medium'" class="px-3 py-1.5 rounded-lg text-xs transition-all duration-150 cursor-pointer">
                    Keuangan & Kas
                </button>
                <button type="button" @click="activeTab = 'ops'" :class="activeTab === 'ops' ? 'bg-surface text-sky-700 dark:text-sky-400 shadow-xs font-semibold' : 'text-slate-700 dark:text-slate-300 hover:text-text-main font-medium'" class="px-3 py-1.5 rounded-lg text-xs transition-all duration-150 cursor-pointer">
                    Layanan & Jaringan
                </button>
                <button type="button" @click="activeTab = 'crm_warehouse'" :class="activeTab === 'crm_warehouse' ? 'bg-surface text-sky-700 dark:text-sky-400 shadow-xs font-semibold' : 'text-slate-700 dark:text-slate-300 hover:text-text-main font-medium'" class="px-3 py-1.5 rounded-lg text-xs transition-all duration-150 cursor-pointer">
                    Pelanggan & Logistik
                </button>
            </div>
        </div>
    </div>

    <!-- ── ROW 1: EXECUTIVE KPI TELEMETRY STRIP ────────────────────────── -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3.5">
        <!-- 1. Pelanggan Aktif -->
        <div class="rounded-2xl border border-border bg-surface p-4 shadow-xs hover:border-primary-border transition-all duration-150">
            <div class="flex items-center justify-between text-xs text-text-muted mb-1.5">
                <span class="font-semibold text-text-secondary">Pelanggan Aktif</span>
                <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
            </div>
            <p class="text-2xl font-bold font-mono text-text-main"><?php echo e(number_format($stats['active_customers'])); ?></p>
            <div class="mt-2 pt-2 border-t border-border/60 flex items-center justify-between text-[11px] text-text-muted">
                <span>Total: <strong class="text-text-secondary"><?php echo e(number_format($stats['total_customers'])); ?></strong></span>
                <span class="<?php echo e($stats['net_customer_growth'] >= 0 ? 'text-emerald-700 dark:text-emerald-300 font-semibold' : 'text-rose-700 dark:text-rose-300 font-semibold'); ?>">
                    <?php echo e($stats['net_customer_growth'] >= 0 ? '+' : ''); ?><?php echo e(number_format($stats['net_customer_growth'])); ?> Net
                </span>
            </div>
        </div>

        <!-- 2. Realisasi Kas Masuk -->
        <div class="rounded-2xl border border-border bg-surface p-4 shadow-xs hover:border-primary-border transition-all duration-150">
            <div class="flex items-center justify-between text-xs text-text-muted mb-1.5">
                <span class="font-semibold text-text-secondary">Realisasi Kas Masuk</span>
                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-emerald-500/10 text-emerald-700 dark:text-emerald-300">Valid</span>
            </div>
            <p class="text-xl font-bold font-mono text-emerald-700 dark:text-emerald-300 truncate"><?php echo e($currency($stats['total_payments_amount'])); ?></p>
            <div class="mt-2 pt-2 border-t border-border/60 flex items-center justify-between text-[11px] text-text-muted">
                <span>Omzet: <strong class="text-text-secondary font-mono"><?php echo e($currency($stats['total_invoices_amount'])); ?></strong></span>
                <span class="font-mono font-semibold text-text-main"><?php echo e($stats['collection_rate'] !== null ? $stats['collection_rate'].'%' : '-'); ?></span>
            </div>
        </div>

        <!-- 3. Total Piutang -->
        <div class="rounded-2xl border border-border bg-surface p-4 shadow-xs hover:border-primary-border transition-all duration-150">
            <div class="flex items-center justify-between text-xs text-text-muted mb-1.5">
                <span class="font-semibold text-text-secondary">Piutang Tagihan</span>
                <span class="h-2 w-2 rounded-full <?php echo e($stats['due_invoices_count'] > 0 ? 'bg-rose-500' : 'bg-emerald-500'); ?>"></span>
            </div>
            <p class="text-xl font-bold font-mono text-rose-700 dark:text-rose-300 truncate"><?php echo e($currency($stats['total_unpaid_amount'])); ?></p>
            <div class="mt-2 pt-2 border-t border-border/60 flex items-center justify-between text-[11px] text-text-muted">
                <span><?php echo e(number_format($stats['due_invoices_count'])); ?> tagihan</span>
                <span class="text-rose-700 dark:text-rose-300 font-semibold">Periode lalu</span>
            </div>
        </div>

        <!-- 4. Kas di Lapangan (Office + Collector) -->
        <div class="rounded-2xl border border-border bg-surface p-4 shadow-xs hover:border-primary-border transition-all duration-150">
            <div class="flex items-center justify-between text-xs text-text-muted mb-1.5">
                <span class="font-semibold text-text-secondary">Kas Mengendap</span>
                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-amber-500/10 text-amber-700 dark:text-amber-300">Fisik</span>
            </div>
            <?php
                $cashLapangan = ($stats['cash_at_office_amount'] ?? 0) + ($stats['cash_with_collector_amount'] ?? 0);
            ?>
            <p class="text-xl font-bold font-mono text-text-main truncate"><?php echo e($currency($cashLapangan)); ?></p>
            <div class="mt-2 pt-2 border-t border-border/60 flex items-center justify-between text-[11px] text-text-muted">
                <span>Kasir: <strong class="text-text-secondary font-mono"><?php echo e($currency($stats['cash_at_office_amount'] ?? 0)); ?></strong></span>
                <span>Kolektor: <strong class="text-text-secondary font-mono"><?php echo e($currency($stats['cash_with_collector_amount'] ?? 0)); ?></strong></span>
            </div>
        </div>

        <!-- 5. Gangguan & SLA -->
        <div class="rounded-2xl border border-border bg-surface p-4 shadow-xs hover:border-primary-border transition-all duration-150">
            <div class="flex items-center justify-between text-xs text-text-muted mb-1.5">
                <span class="font-semibold text-text-secondary">Tiket Gangguan</span>
                <span class="h-2 w-2 rounded-full <?php echo e(($stats['ticket_sla_breach_count'] ?? 0) > 0 ? 'bg-rose-500 animate-ping' : 'bg-sky-500'); ?>"></span>
            </div>
            <?php
                $activeTicketsCount = ($stats['ticket_bucket_masuk'] ?? 0) + ($stats['ticket_bucket_diproses'] ?? 0);
            ?>
            <p class="text-2xl font-bold font-mono text-text-main"><?php echo e(number_format($activeTicketsCount)); ?> <span class="text-xs font-normal text-text-muted">aktif</span></p>
            <div class="mt-2 pt-2 border-t border-border/60 flex items-center justify-between text-[11px]">
                <span class="text-text-muted">SLA OK: <strong class="text-emerald-700 dark:text-emerald-300"><?php echo e($stats['ticket_sla_compliance_rate'] ?? 100); ?>%</strong></span>
                <span class="<?php echo e(($stats['ticket_sla_breach_count'] ?? 0) > 0 ? 'text-rose-700 dark:text-rose-300 font-bold' : 'text-text-muted'); ?>">
                    <?php echo e(number_format($stats['ticket_sla_breach_count'] ?? 0)); ?> Breach
                </span>
            </div>
        </div>

        <!-- 6. Delivery Lapangan (PSB) -->
        <div class="rounded-2xl border border-border bg-surface p-4 shadow-xs hover:border-primary-border transition-all duration-150">
            <div class="flex items-center justify-between text-xs text-text-muted mb-1.5">
                <span class="font-semibold text-text-secondary">Delivery PSB</span>
                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-sky-500/10 text-sky-700 dark:text-sky-300">FOP</span>
            </div>
            <p class="text-2xl font-bold font-mono text-text-main"><?php echo e(number_format($stats['fop_psb_completed_count'] ?? 0)); ?> <span class="text-xs font-normal text-text-muted">pasang</span></p>
            <div class="mt-2 pt-2 border-t border-border/60 flex items-center justify-between text-[11px] text-text-muted">
                <span>Lead time: <strong class="text-text-secondary"><?php echo e(is_null($stats['fop_psb_avg_lead_time_days'] ?? null) ? '-' : $stats['fop_psb_avg_lead_time_days'].' hr'); ?></strong></span>
                <span class="<?php echo e((($stats['fop_overdue_psb_count'] ?? 0) + ($stats['fop_overdue_survey_count'] ?? 0)) > 0 ? 'text-rose-700 dark:text-rose-300 font-semibold' : 'text-text-muted'); ?>">
                    <?php echo e(number_format(($stats['fop_overdue_psb_count'] ?? 0) + ($stats['fop_overdue_survey_count'] ?? 0))); ?> Overdue
                </span>
            </div>
        </div>
    </div>

    <!-- ── ROW 2: KEUANGAN, ARUS KAS & ACTION CENTER ──────────────────── -->
    <div x-show="activeTab === 'all' || activeTab === 'finance'" x-transition class="grid grid-cols-1 xl:grid-cols-3 gap-6">

        <!-- KIRI: GRAFIK FINANSIAL (2 Kolom) -->
        <div class="xl:col-span-2 space-y-6">

            <!-- Chart 1: Tren Tagihan vs Realisasi Kas 6 Bulan -->
            <div class="rounded-2xl border border-border bg-surface p-5 shadow-xs">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-4">
                    <div>
                        <h3 class="text-sm font-bold text-text-main flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-sm bg-sky-500"></span>
                            Tren Bulanan: Tagihan Terbit vs Realisasi Kas Valid
                        </h3>
                        <p class="text-xs text-text-muted mt-0.5">Komparasi nilai billing terbit versus kas nyata masuk 6 bulan terakhir.</p>
                    </div>
                    <div class="flex items-center gap-3 text-xs">
                        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-sky-500"></span> Tagihan Terbit</span>
                        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded bg-emerald-500"></span> Kas Masuk</span>
                    </div>
                </div>

                <div class="h-68 relative">
                    <canvas id="chart-financial-trend"></canvas>
                </div>
            </div>

            <!-- Subgrid: Kanal Pembayaran & Aging Piutang -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                <!-- Chart 2: Komposisi Kanal Pembayaran -->
                <div class="rounded-2xl border border-border bg-surface p-5 shadow-xs">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-bold text-text-main flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-sm bg-indigo-500"></span>
                            Komposisi Kanal Pembayaran
                        </h3>
                        <span class="text-xs font-mono font-semibold text-text-muted"><?php echo e($filters['period_label']); ?></span>
                    </div>
                    <p class="text-xs text-text-muted mb-4">Porsi kas masuk berdasarkan metode transaksi.</p>

                    <div class="h-56 relative flex items-center justify-center">
                        <canvas id="chart-payment-channels"></canvas>
                    </div>

                    <div class="grid grid-cols-2 gap-2 mt-4 pt-3 border-t border-border text-xs">
                        <?php $__currentLoopData = $stats['payment_channels'] ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $method => $chan): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <div class="flex items-center justify-between p-1.5 rounded bg-surface-muted/50">
                                <span class="text-text-muted font-medium"><?php echo e($chan['label']); ?></span>
                                <span class="font-mono font-semibold text-text-main"><?php echo e($currency($chan['amount'])); ?></span>
                            </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                </div>

                <!-- Chart 3: Profil Aging Piutang -->
                <div class="rounded-2xl border border-border bg-surface p-5 shadow-xs">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-bold text-text-main flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-sm bg-rose-500"></span>
                            Profil Aging Piutang
                        </h3>
                        <span class="text-xs text-rose-600 dark:text-rose-400 font-semibold font-mono"><?php echo e(number_format($stats['due_invoices_count'])); ?> Belum Lunas</span>
                    </div>
                    <p class="text-xs text-text-muted mb-4">Distribusi tagihan belum dibayar berdasarkan umur jatuh tempo.</p>

                    <div class="h-56 relative">
                        <canvas id="chart-aging-receivables"></canvas>
                    </div>

                    <div class="grid grid-cols-2 gap-2 mt-4 pt-3 border-t border-border text-xs">
                        <div class="p-1.5 rounded bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 flex justify-between">
                            <span>Lancar:</span>
                            <span class="font-mono font-bold"><?php echo e($currency($stats['aging_receivables']['lancar']['amount'] ?? 0)); ?></span>
                        </div>
                        <div class="p-1.5 rounded bg-amber-500/10 text-amber-700 dark:text-amber-300 flex justify-between">
                            <span>1-30 Hari:</span>
                            <span class="font-mono font-bold"><?php echo e($currency($stats['aging_receivables']['days_1_30']['amount'] ?? 0)); ?></span>
                        </div>
                        <div class="p-1.5 rounded bg-orange-500/10 text-orange-700 dark:text-orange-300 flex justify-between">
                            <span>31-60 Hari:</span>
                            <span class="font-mono font-bold"><?php echo e($currency($stats['aging_receivables']['days_31_60']['amount'] ?? 0)); ?></span>
                        </div>
                        <div class="p-1.5 rounded bg-rose-500/10 text-rose-700 dark:text-rose-300 flex justify-between">
                            <span>>60 Hari (Macet):</span>
                            <span class="font-mono font-bold"><?php echo e($currency($stats['aging_receivables']['days_over_60']['amount'] ?? 0)); ?></span>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Posisi Kas & Pengawasan Kolektor -->
            <?php if($canViewCash): ?>
            <div class="rounded-2xl border border-border bg-surface p-5 shadow-xs">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-sm font-bold text-text-main">Posisi Keberadaan Uang Kas & Pengawasan Kolektor</h3>
                        <p class="text-xs text-text-muted mt-0.5">Uang fisik yang sedang berada di tangan kasir POP maupun tim penagih lapangan.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <?php if(($stats['cash_deposit_open_difference_count'] ?? 0) > 0): ?>
                            <?php if (isset($component)) { $__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.badge','data' => ['variant' => 'error']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'error']); ?><?php echo e($stats['cash_deposit_open_difference_count']); ?> selisih admin <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4)): ?>
<?php $attributes = $__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4; ?>
<?php unset($__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4)): ?>
<?php $component = $__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4; ?>
<?php unset($__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4); ?>
<?php endif; ?>
                        <?php endif; ?>
                        <?php if(($stats['collector_shortfall_count'] ?? 0) > 0): ?>
                            <?php if (isset($component)) { $__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.badge','data' => ['variant' => 'error']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'error']); ?><?php echo e($stats['collector_shortfall_count']); ?> kurang setor kolektor <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4)): ?>
<?php $attributes = $__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4; ?>
<?php unset($__attributesOriginalab7baa01105b3dfe1e0cf1dfc58879b4); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4)): ?>
<?php $component = $__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4; ?>
<?php unset($__componentOriginalab7baa01105b3dfe1e0cf1dfc58879b4); ?>
<?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
                    <div class="rounded-xl border border-border p-3.5 bg-surface-muted/30">
                        <p class="text-xs text-text-muted mb-1">Kas di Kasir Kantor POP</p>
                        <p class="text-lg font-bold font-mono text-text-main"><?php echo e($currency($stats['cash_at_office_amount'])); ?></p>
                        <p class="text-[11px] text-text-muted mt-1">Belum disetor ke bank/Owner</p>
                    </div>

                    <div class="rounded-xl border border-border p-3.5 bg-surface-muted/30">
                        <p class="text-xs text-text-muted mb-1">Uang di Tangan Kolektor</p>
                        <p class="text-lg font-bold font-mono text-text-main"><?php echo e($currency($stats['cash_with_collector_amount'])); ?></p>
                        <p class="text-[11px] text-text-muted mt-1">Uang tagihan di lapangan</p>
                    </div>

                    <div class="rounded-xl border border-border p-3.5 bg-surface-muted/30">
                        <p class="text-xs text-text-muted mb-1">Setoran Menunggu Verifikasi</p>
                        <p class="text-lg font-bold font-mono text-primary"><?php echo e($currency($stats['cash_deposit_pending_amount'])); ?></p>
                        <p class="text-[11px] text-text-muted mt-1"><?php echo e(number_format($stats['cash_deposit_pending_count'])); ?> sesi setoran diajukan</p>
                    </div>

                    <div class="rounded-xl border p-3.5 bg-surface-muted/30 <?php echo e(($stats['collector_shortfall_count'] ?? 0) > 0 ? 'border-rose-500/40' : 'border-border'); ?>">
                        <p class="text-xs text-text-muted mb-1">Kurang Setor Kolektor</p>
                        <p class="text-lg font-bold font-mono <?php echo e(($stats['collector_shortfall_count'] ?? 0) > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-text-main'); ?>">
                            <?php echo e($currency($stats['collector_shortfall_amount'] ?? 0)); ?>

                        </p>
                        <p class="text-[11px] text-text-muted mt-1"><?php echo e(number_format($stats['collector_shortfall_count'] ?? 0)); ?> kolektor ada selisih</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>

        <!-- KANAN: ACTION CENTER & KONTRIBUSI OMZET (1 Kolom) -->
        <div class="space-y-6">

            <!-- Action & Decision Center -->
            <div class="rounded-2xl border border-border bg-surface p-5 shadow-xs">
                <div class="flex items-center justify-between mb-3 border-b border-border pb-3">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span>
                        <h3 class="text-sm font-bold text-text-main">Action & Decision Center</h3>
                    </div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-text-muted">Prioritas</span>
                </div>

                <div class="space-y-3">
                    <!-- 1. Approval Setoran Kas Admin -->
                    <?php if($canViewCash): ?>
                    <div>
                        <div class="flex items-center justify-between text-xs font-semibold text-text-secondary mb-1.5">
                            <span>Verifikasi Setoran Kas</span>
                            <span class="text-xs text-sky-700 dark:text-sky-400 font-mono font-semibold"><?php echo e($pendingCashDeposits->count()); ?> Menunggu</span>
                        </div>
                        <div class="space-y-1.5">
                            <?php $__empty_1 = true; $__currentLoopData = $pendingCashDeposits; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $deposit): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                <a href="<?php echo e(route('cash-deposits.index')); ?>" class="flex items-center justify-between p-2.5 rounded-xl border border-border hover:border-primary-border hover:bg-surface-muted transition-all text-xs">
                                    <div>
                                        <p class="font-bold text-text-main"><?php echo e($deposit->pop?->name ?? 'Cabang'); ?></p>
                                        <p class="text-[11px] text-text-muted"><?php echo e($deposit->depositor?->name ?? '-'); ?> &middot; <?php echo e($deposit->submitted_at?->format('d/m H:i')); ?></p>
                                    </div>
                                    <div class="text-right">
                                        <span class="font-mono font-bold text-text-main block"><?php echo e($currency($deposit->declared_amount)); ?></span>
                                        <span class="text-[10px] text-sky-700 dark:text-sky-400 font-semibold">Periksa &rarr;</span>
                                    </div>
                                </a>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                <p class="text-xs text-text-muted py-2 text-center bg-surface-muted/30 rounded-xl">Semua setoran kas sudah terverifikasi.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- 2. Breach SLA Alert -->
                    <?php if($canViewTickets && ($stats['ticket_sla_breach_count'] ?? 0) > 0): ?>
                    <div class="p-3 rounded-xl border border-rose-500/30 bg-rose-500/5">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-rose-700 dark:text-rose-400 flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                <?php echo e($stats['ticket_sla_breach_count']); ?> Tiket Lewat Batas SLA
                            </span>
                            <?php if(auth()->user()->hasPermission('noc_worksheet.view')): ?>
                                <a href="<?php echo e(route('noc.worksheet')); ?>" class="text-[11px] font-bold text-rose-700 dark:text-rose-400 underline">Buka NOC</a>
                            <?php endif; ?>
                        </div>
                        <p class="text-[11px] text-slate-600 dark:text-slate-400 mt-1">Perlu eskalasi segera untuk memitigasi komplain pelanggan.</p>
                    </div>
                    <?php endif; ?>

                    <!-- 3. Stok Kritis Alert -->
                    <?php if($canViewWarehouse && ($stats['low_stock_count'] ?? 0) > 0): ?>
                    <div class="p-3 rounded-xl border border-amber-500/30 bg-amber-500/5">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-amber-700 dark:text-amber-400 flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                                <?php echo e($stats['low_stock_count']); ?> Barang di Bawah Stok Minimum
                            </span>
                            <a href="<?php echo e(route('warehouse.index')); ?>" class="text-[11px] font-bold text-amber-700 dark:text-amber-400 underline">Gudang</a>
                        </div>
                        <p class="text-[11px] text-slate-600 dark:text-slate-400 mt-1">Segera kirim pasokan atau buat pengajuan transfer antar POP.</p>
                    </div>
                    <?php endif; ?>

                    <!-- 4. Antrean Lapangan Overdue -->
                    <?php if($canViewFopDelivery && (($stats['fop_overdue_psb_count'] ?? 0) + ($stats['fop_overdue_survey_count'] ?? 0)) > 0): ?>
                    <div class="p-3 rounded-xl border border-border bg-surface-muted/40">
                        <div class="flex items-center justify-between text-xs font-bold text-text-main">
                            <span>Antrean Lapangan Melebihi Batas</span>
                            <a href="<?php echo e(route('fop.dashboard')); ?>" class="text-sky-700 dark:text-sky-400 font-semibold text-[11px]">Lihat FOP</a>
                        </div>
                        <p class="text-[11px] text-text-muted mt-1">
                            <?php echo e(number_format($stats['fop_overdue_survey_count'] ?? 0)); ?> Survey (>24 jam) &middot; <?php echo e(number_format($stats['fop_overdue_psb_count'] ?? 0)); ?> Pasang Baru (>3 hari)
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Kontribusi Omzet per Segmen -->
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('sales_omset_dashboard.view')): ?>
            <div class="rounded-2xl border border-border bg-surface p-5 shadow-xs">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-bold text-text-main">Omzet per Segmen Penjualan</h3>
                    <a href="<?php echo e(route('business-development.sales-omset.index')); ?>" class="text-xs text-sky-700 dark:text-sky-400 font-semibold hover:underline">Detail</a>
                </div>

                <div class="space-y-4">
                    <!-- Sales -->
                    <div>
                        <div class="flex justify-between text-xs font-semibold mb-1">
                            <span class="text-sky-700 dark:text-sky-400">Tim Sales</span>
                            <span class="font-mono text-text-main"><?php echo e($currency($stats['omset_sales_amount'] ?? 0)); ?></span>
                        </div>
                        <div class="h-2 rounded-full bg-surface-muted overflow-hidden">
                            <div class="h-full bg-sky-500 rounded-full" style="width: <?php echo e(min(100, max(5, (($stats['omset_sales_amount'] ?? 0) / max(1, $stats['total_invoices_amount'])) * 100))); ?>%"></div>
                        </div>
                        <p class="text-[11px] text-text-muted mt-1"><?php echo e(number_format($stats['omset_sales_count'] ?? 0)); ?> pelanggan aktif terdaftar</p>
                    </div>

                    <!-- Teknisi -->
                    <div>
                        <div class="flex justify-between text-xs font-semibold mb-1">
                            <span class="text-emerald-600 dark:text-emerald-400">Teknisi & PIC</span>
                            <span class="font-mono text-text-main"><?php echo e($currency($stats['omset_teknisi_amount'] ?? 0)); ?></span>
                        </div>
                        <div class="h-2 rounded-full bg-surface-muted overflow-hidden">
                            <div class="h-full bg-emerald-500 rounded-full" style="width: <?php echo e(min(100, max(5, (($stats['omset_teknisi_amount'] ?? 0) / max(1, $stats['total_invoices_amount'])) * 100))); ?>%"></div>
                        </div>
                        <p class="text-[11px] text-text-muted mt-1"><?php echo e(number_format($stats['omset_teknisi_count'] ?? 0)); ?> pelanggan aktif terdaftar</p>
                    </div>

                    <!-- Bisnis -->
                    <div>
                        <div class="flex justify-between text-xs font-semibold mb-1">
                            <span class="text-amber-600 dark:text-amber-400">Segmen Bisnis</span>
                            <span class="font-mono text-text-main"><?php echo e($currency($stats['omset_bisnis_amount'] ?? 0)); ?></span>
                        </div>
                        <div class="h-2 rounded-full bg-surface-muted overflow-hidden">
                            <div class="h-full bg-amber-500 rounded-full" style="width: <?php echo e(min(100, max(5, (($stats['omset_bisnis_amount'] ?? 0) / max(1, $stats['total_invoices_amount'])) * 100))); ?>%"></div>
                        </div>
                        <p class="text-[11px] text-text-muted mt-1"><?php echo e(number_format($stats['omset_bisnis_count'] ?? 0)); ?> pelanggan korporasi/bisnis</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Akses Cepat Modul Utama -->
            <div class="rounded-2xl border border-border bg-surface p-5 shadow-xs">
                <h3 class="text-sm font-bold text-text-main mb-3">Pintasan Navigasi Divisi</h3>
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <?php if(auth()->user()->hasPermission('customers.view')): ?>
                        <a href="<?php echo e(route('customers.index')); ?>" class="p-2.5 rounded-xl border border-border hover:border-primary-border hover:bg-surface-muted flex items-center justify-between text-text-secondary hover:text-primary transition-all">
                            <span>Pelanggan</span>
                            <span>&rarr;</span>
                        </a>
                    <?php endif; ?>
                    <?php if(auth()->user()->hasPermission('invoices.view')): ?>
                        <a href="<?php echo e(route('invoices.index')); ?>" class="p-2.5 rounded-xl border border-border hover:border-primary-border hover:bg-surface-muted flex items-center justify-between text-text-secondary hover:text-primary transition-all">
                            <span>Daftar Tagihan</span>
                            <span>&rarr;</span>
                        </a>
                    <?php endif; ?>
                    <?php if(auth()->user()->hasPermission('noc_dashboard.view')): ?>
                        <a href="<?php echo e(route('noc.dashboard')); ?>" class="p-2.5 rounded-xl border border-border hover:border-primary-border hover:bg-surface-muted flex items-center justify-between text-text-secondary hover:text-primary transition-all">
                            <span>Dashboard NOC</span>
                            <span>&rarr;</span>
                        </a>
                    <?php endif; ?>
                    <?php if(auth()->user()->hasPermission('task.view.all')): ?>
                        <a href="<?php echo e(route('fop.dashboard')); ?>" class="p-2.5 rounded-xl border border-border hover:border-primary-border hover:bg-surface-muted flex items-center justify-between text-text-secondary hover:text-primary transition-all">
                            <span>Dashboard FOP</span>
                            <span>&rarr;</span>
                        </a>
                    <?php endif; ?>
                    <?php if($canViewWarehouse): ?>
                        <a href="<?php echo e(route('warehouse.index')); ?>" class="p-2.5 rounded-xl border border-border hover:border-primary-border hover:bg-surface-muted flex items-center justify-between text-text-secondary hover:text-primary transition-all">
                            <span>Gudang</span>
                            <span>&rarr;</span>
                        </a>
                    <?php endif; ?>
                    <?php if(auth()->user()->hasPermission('collector_worksheet.view')): ?>
                        <a href="<?php echo e(route('collector-worksheet.index')); ?>" class="p-2.5 rounded-xl border border-border hover:border-primary-border hover:bg-surface-muted flex items-center justify-between text-text-secondary hover:text-primary transition-all">
                            <span>Worksheet Kolektor</span>
                            <span>&rarr;</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

        </div>

    </div>

    <!-- ── ROW 3: LAYANAN JARINGAN (NOC) & OPERASIONAL LAPANGAN (FOP) ──── -->
    <div x-show="activeTab === 'all' || activeTab === 'ops'" x-transition class="grid grid-cols-1 xl:grid-cols-2 gap-6">

        <!-- KIRI: FUNNEL AKUISISI & DELIVERY PASANG BARU -->
        <div class="rounded-2xl border border-border bg-surface p-5 shadow-xs space-y-5">
            <div class="flex items-center justify-between border-b border-border pb-3">
                <div>
                    <h3 class="text-sm font-bold text-text-main flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-sm bg-sky-500"></span>
                        Pipeline Konversi & Delivery Pasang Baru (PSB)
                    </h3>
                    <p class="text-xs text-text-muted mt-0.5">Alur proses dari calon pelanggan mendaftar hingga aktif dan siap ditagih.</p>
                </div>
                <?php if($canViewFopDelivery): ?>
                    <a href="<?php echo e(route('fop.dashboard')); ?>" class="text-xs font-semibold text-primary hover:underline">Ke Dashboard FOP</a>
                <?php endif; ?>
            </div>

            <!-- Stepper Funnel Stages -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                <div class="rounded-xl border border-border p-3 text-center bg-surface-muted/30">
                    <span class="text-[10px] uppercase font-bold text-text-muted block">1. Menunggu Survey</span>
                    <span class="text-xl font-bold font-mono text-text-main mt-1 block"><?php echo e(number_format($stats['funnel_waiting_survey'] ?? 0)); ?></span>
                    <?php if(($stats['fop_overdue_survey_count'] ?? 0) > 0): ?>
                        <span class="text-[10px] text-rose-600 dark:text-rose-400 font-semibold block mt-1"><?php echo e($stats['fop_overdue_survey_count']); ?> overdue</span>
                    <?php endif; ?>
                </div>
                <div class="rounded-xl border border-border p-3 text-center bg-surface-muted/30">
                    <span class="text-[10px] uppercase font-bold text-text-muted block">2. Menunggu ACC</span>
                    <span class="text-xl font-bold font-mono text-text-main mt-1 block"><?php echo e(number_format($stats['funnel_waiting_acc'] ?? 0)); ?></span>
                    <span class="text-[10px] text-text-muted block mt-1">Verifikasi Sales/Admin</span>
                </div>
                <div class="rounded-xl border border-border p-3 text-center bg-surface-muted/30">
                    <span class="text-[10px] uppercase font-bold text-text-muted block">3. Pasang Baru (PSB)</span>
                    <span class="text-xl font-bold font-mono text-primary mt-1 block"><?php echo e(number_format($stats['funnel_waiting_installation'] ?? 0)); ?></span>
                    <?php if(($stats['fop_overdue_psb_count'] ?? 0) > 0): ?>
                        <span class="text-[10px] text-rose-600 dark:text-rose-400 font-semibold block mt-1"><?php echo e($stats['fop_overdue_psb_count']); ?> overdue</span>
                    <?php endif; ?>
                </div>
                <div class="rounded-xl border border-border p-3 text-center bg-surface-muted/30">
                    <span class="text-[10px] uppercase font-bold text-text-muted block">4. Verifikasi Akhir</span>
                    <span class="text-xl font-bold font-mono text-text-main mt-1 block"><?php echo e(number_format($stats['funnel_verification_admin'] ?? 0)); ?></span>
                    <span class="text-[10px] text-text-muted block mt-1">Siap aktivasi</span>
                </div>
            </div>

            <!-- Metrik Delivery Hasil Lapangan -->
            <div class="grid grid-cols-3 gap-3 pt-2">
                <div class="p-3 rounded-xl border border-border bg-surface-muted/20">
                    <p class="text-[11px] text-text-muted">Survey Selesai</p>
                    <p class="text-lg font-bold font-mono text-text-main"><?php echo e(number_format($stats['fop_survey_completed_count'] ?? 0)); ?></p>
                    <p class="text-[10px] text-text-muted mt-0.5">Periode terpilih</p>
                </div>
                <div class="p-3 rounded-xl border border-border bg-surface-muted/20">
                    <p class="text-[11px] text-text-muted">PSB Selesai</p>
                    <p class="text-lg font-bold font-mono text-emerald-600 dark:text-emerald-400"><?php echo e(number_format($stats['fop_psb_completed_count'] ?? 0)); ?></p>
                    <p class="text-[10px] text-text-muted mt-0.5">Sudah online</p>
                </div>
                <div class="p-3 rounded-xl border border-border bg-surface-muted/20">
                    <p class="text-[11px] text-text-muted">Rata Lead Time</p>
                    <p class="text-lg font-bold font-mono text-text-main"><?php echo e(is_null($stats['fop_psb_avg_lead_time_days'] ?? null) ? '-' : $stats['fop_psb_avg_lead_time_days'].' Hari'); ?></p>
                    <p class="text-[10px] text-text-muted mt-0.5">Target &le; 3 hari</p>
                </div>
            </div>

            <div class="p-3 rounded-xl bg-surface-muted/40 text-xs text-text-muted flex items-center justify-between">
                <span>Pelanggan Gagal/Batal Periode Ini: <strong class="text-text-main"><?php echo e(number_format($stats['rejected_customers'] ?? 0)); ?></strong></span>
                <span>Pelanggan Terisolir: <strong class="text-amber-600 dark:text-amber-400"><?php echo e(number_format($stats['suspended_customers'] ?? 0)); ?></strong></span>
            </div>
        </div>

        <!-- KANAN: KUALITAS LAYANAN & GANGGUAN JARINGAN (TICKETING) -->
        <div class="rounded-2xl border border-border bg-surface p-5 shadow-xs space-y-5">
            <div class="flex items-center justify-between border-b border-border pb-3">
                <div>
                    <h3 class="text-sm font-bold text-text-main flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-sm bg-rose-500"></span>
                        Kualitas Layanan & Penanganan Gangguan (NOC)
                    </h3>
                    <p class="text-xs text-text-muted mt-0.5">Tingkat kepatuhan resolusi gangguan dan akar masalah dominan.</p>
                </div>
                <?php if(auth()->user()->hasPermission('noc_dashboard.view')): ?>
                    <a href="<?php echo e(route('noc.dashboard')); ?>" class="text-xs font-semibold text-primary hover:underline">Ke Dashboard NOC</a>
                <?php endif; ?>
            </div>

            <!-- Ticket Buckets Navigation Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                <div class="p-3 rounded-xl border border-border bg-surface-muted/30 text-center">
                    <span class="text-[10px] uppercase font-bold text-text-muted block">Masuk (Open)</span>
                    <span class="text-xl font-bold font-mono text-text-main mt-1 block"><?php echo e(number_format($stats['ticket_bucket_masuk'] ?? 0)); ?></span>
                </div>
                <div class="p-3 rounded-xl border border-border bg-surface-muted/30 text-center">
                    <span class="text-[10px] uppercase font-bold text-text-muted block">Diproses</span>
                    <span class="text-xl font-bold font-mono text-primary mt-1 block"><?php echo e(number_format($stats['ticket_bucket_diproses'] ?? 0)); ?></span>
                </div>
                <div class="p-3 rounded-xl border border-border bg-surface-muted/30 text-center">
                    <span class="text-[10px] uppercase font-bold text-text-muted block">Selesai</span>
                    <span class="text-xl font-bold font-mono text-emerald-600 dark:text-emerald-400 mt-1 block"><?php echo e(number_format($stats['ticket_bucket_selesai'] ?? 0)); ?></span>
                </div>
                <div class="p-3 rounded-xl border border-border bg-surface-muted/30 text-center">
                    <span class="text-[10px] uppercase font-bold text-text-muted block">Dibatalkan</span>
                    <span class="text-xl font-bold font-mono text-text-muted mt-1 block"><?php echo e(number_format($stats['ticket_bucket_dibatalkan'] ?? 0)); ?></span>
                </div>
            </div>

            <!-- Top 5 Kategori Gangguan Chart -->
            <div>
                <div class="flex items-center justify-between text-xs font-semibold text-text-secondary mb-2">
                    <span>Top 5 Kategori Gangguan (<?php echo e($filters['period_label']); ?>)</span>
                    <span class="text-[11px] text-text-muted">Akar masalah dominan</span>
                </div>
                <div class="h-44 relative">
                    <canvas id="chart-top-issues"></canvas>
                </div>
            </div>
        </div>

    </div>

    <!-- ── ROW 4: DATA PELANGGAN & LOGISTIK GUDANG ─────────────────────── -->
    <div x-show="activeTab === 'all' || activeTab === 'crm_warehouse'" x-transition class="grid grid-cols-1 xl:grid-cols-2 gap-6">

        <!-- KIRI: SEBARAN PELANGGAN & KELENGKAPAN DATA -->
        <div class="rounded-2xl border border-border bg-surface p-5 shadow-xs space-y-5">
            <div class="flex items-center justify-between border-b border-border pb-3">
                <div>
                    <h3 class="text-sm font-bold text-text-main flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-sm bg-sky-500"></span>
                        Sebaran Pelanggan Cabang & Kesiapan Billing
                    </h3>
                    <p class="text-xs text-text-muted mt-0.5">Distribusi populasi pelanggan per wilayah POP dan kelengkapan data master.</p>
                </div>
                <?php if(auth()->user()->hasPermission('customers.view')): ?>
                    <a href="<?php echo e(route('customers.index')); ?>" class="text-xs font-semibold text-primary hover:underline">Kelola Data</a>
                <?php endif; ?>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- Sebaran per Cabang Chart -->
                <div>
                    <h4 class="text-xs font-semibold text-text-secondary mb-2">Populasi per Cabang (POP)</h4>
                    <div class="h-52 relative">
                        <canvas id="chart-pop-distribution"></canvas>
                    </div>
                </div>

                <!-- Donut Kelengkapan Data -->
                <div>
                    <h4 class="text-xs font-semibold text-text-secondary mb-2">Status Kelengkapan Data</h4>
                    <div class="h-52 relative flex items-center justify-center">
                        <canvas id="chart-customer-completeness"></canvas>
                    </div>
                </div>
            </div>

            <!-- Tabel Singkat 5 Pelanggan Perlu Dilengkapi -->
            <div>
                <div class="flex items-center justify-between mb-2">
                    <h4 class="text-xs font-bold text-text-secondary">Antrean Pelanggan Perlu Dilengkapi</h4>
                    <?php if(auth()->user()->hasPermission('customers.view')): ?>
                        <a href="<?php echo e(route('customers.index', ['completeness_status' => 'perlu_dilengkapi'])); ?>" class="text-xs text-primary font-medium hover:underline">Lihat Semua</a>
                    <?php endif; ?>
                </div>

                <div class="overflow-x-auto rounded-xl border border-border">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-surface-muted/60 text-text-muted">
                            <tr>
                                <th class="p-2.5 font-semibold">ID / Nama</th>
                                <th class="p-2.5 font-semibold">Cabang</th>
                                <th class="p-2.5 font-semibold">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <?php $__empty_1 = true; $__currentLoopData = $incompleteCustomers->take(5); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cust): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                <tr class="hover:bg-surface-muted/30">
                                    <td class="p-2.5">
                                        <span class="font-mono font-bold text-text-main block"><?php echo e($cust->customer_code); ?></span>
                                        <span class="text-[11px] text-text-muted"><?php echo e($cust->full_name); ?></span>
                                    </td>
                                    <td class="p-2.5 text-text-secondary"><?php echo e($cust->pop?->name ?? '-'); ?></td>
                                    <td class="p-2.5">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-500/10 text-amber-600 dark:text-amber-400">
                                            <?php echo e($statusLabels[$cust->data_completeness_status] ?? $cust->data_completeness_status); ?>

                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                <tr>
                                    <td colspan="3" class="p-3 text-center text-text-muted">Semua data pelanggan lengkap!</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- KANAN: LOGISTIK GUDANG & PIUTANG TERTUA -->
        <div class="rounded-2xl border border-border bg-surface p-5 shadow-xs space-y-5">
            <div class="flex items-center justify-between border-b border-border pb-3">
                <div>
                    <h3 class="text-sm font-bold text-text-main flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-sm bg-amber-500"></span>
                        Logistik Gudang & Daftar Piutang Tertua
                    </h3>
                    <p class="text-xs text-text-muted mt-0.5">Kondisi persediaan barang di POP dan tagihan yang memerlukan penagihan intensif.</p>
                </div>
                <?php if($canViewWarehouse): ?>
                    <a href="<?php echo e(route('warehouse.index')); ?>" class="text-xs font-semibold text-primary hover:underline">Ke Gudang</a>
                <?php endif; ?>
            </div>

            <!-- Ringkasan Status Aset Gudang -->
            <?php if($canViewWarehouse): ?>
            <div class="grid grid-cols-3 gap-2.5">
                <div class="p-3 rounded-xl border border-border bg-surface-muted/30 text-center">
                    <span class="text-[10px] uppercase font-bold text-text-muted block">Transfer In-Transit</span>
                    <span class="text-lg font-bold font-mono text-primary mt-0.5 block"><?php echo e(number_format($stats['warehouse_asset_overview']['in_transit_count'] ?? 0)); ?></span>
                    <span class="text-[10px] text-text-muted block">Menuju cabang</span>
                </div>
                <div class="p-3 rounded-xl border border-border bg-surface-muted/30 text-center">
                    <span class="text-[10px] uppercase font-bold text-text-muted block">Custody Teknisi</span>
                    <span class="text-lg font-bold font-mono text-text-main mt-0.5 block"><?php echo e(number_format($stats['warehouse_asset_overview']['custody_count'] ?? 0)); ?></span>
                    <span class="text-[10px] text-text-muted block">Unit di lapangan</span>
                </div>
                <div class="p-3 rounded-xl border border-border bg-surface-muted/30 text-center">
                    <span class="text-[10px] uppercase font-bold text-text-muted block">Retur / Karantina</span>
                    <span class="text-lg font-bold font-mono text-rose-600 dark:text-rose-400 mt-0.5 block"><?php echo e(number_format($stats['warehouse_asset_overview']['damaged_returned_count'] ?? 0)); ?></span>
                    <span class="text-[10px] text-text-muted block">Perlu cek/verif</span>
                </div>
            </div>

            <!-- Tabel Alert Stok Kritis -->
            <div>
                <h4 class="text-xs font-bold text-text-secondary mb-2 flex items-center justify-between">
                    <span>Alert Persediaan Menipis di POP</span>
                    <span class="text-[11px] font-mono text-rose-600 dark:text-rose-400 font-semibold"><?php echo e($lowStockItems->count()); ?> Item Kritis</span>
                </h4>
                <div class="overflow-x-auto rounded-xl border border-border">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-surface-muted/60 text-text-muted">
                            <tr>
                                <th class="p-2.5 font-semibold">Cabang</th>
                                <th class="p-2.5 font-semibold">Nama Barang</th>
                                <th class="p-2.5 font-semibold text-right">Sisa Stok</th>
                                <th class="p-2.5 font-semibold text-right">Minimum</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <?php $__empty_1 = true; $__currentLoopData = $lowStockItems->take(5); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $bal): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                <tr class="hover:bg-surface-muted/30">
                                    <td class="p-2.5 text-text-muted"><?php echo e($bal->pop?->name ?? '-'); ?></td>
                                    <td class="p-2.5 font-medium text-text-main"><?php echo e($bal->item?->name ?? '-'); ?></td>
                                    <td class="p-2.5 text-right font-mono font-bold text-rose-600 dark:text-rose-400"><?php echo e(number_format($bal->qty, 0)); ?> <?php echo e($bal->item?->unit); ?></td>
                                    <td class="p-2.5 text-right font-mono text-text-muted"><?php echo e(number_format($bal->minimum_stock, 0)); ?> <?php echo e($bal->item?->unit); ?></td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                <tr>
                                    <td colspan="4" class="p-3 text-center text-text-muted">Semua stok di atas batas minimum.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- Top 5 Piutang Jatuh Tempo -->
            <div>
                <div class="flex items-center justify-between mb-2">
                    <h4 class="text-xs font-bold text-text-secondary">Daftar Tagihan Tertua Belum Lunas</h4>
                    <?php if(auth()->user()->hasPermission('invoices.view')): ?>
                        <a href="<?php echo e(route('invoices.index')); ?>" class="text-xs text-primary font-medium hover:underline">Buka Tagihan</a>
                    <?php endif; ?>
                </div>
                <div class="overflow-x-auto rounded-xl border border-border">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-surface-muted/60 text-text-muted">
                            <tr>
                                <th class="p-2.5 font-semibold">No Invoice / Pelanggan</th>
                                <th class="p-2.5 font-semibold">Periode</th>
                                <th class="p-2.5 font-semibold text-right">Sisa Tagihan</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            <?php $__empty_1 = true; $__currentLoopData = $dueInvoices->take(5); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $inv): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                <tr class="hover:bg-surface-muted/30">
                                    <td class="p-2.5">
                                        <span class="font-mono font-bold text-text-main block"><?php echo e($inv->invoice_number); ?></span>
                                        <span class="text-[11px] text-text-muted"><?php echo e($inv->customer?->full_name ?? '-'); ?> (<?php echo e($inv->pop?->name ?? '-'); ?>)</span>
                                    </td>
                                    <td class="p-2.5 font-mono text-rose-600 dark:text-rose-400 font-semibold"><?php echo e($inv->billing_period); ?></td>
                                    <td class="p-2.5 text-right font-mono font-bold text-text-main"><?php echo e($currency($inv->remaining_amount)); ?></td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                <tr>
                                    <td colspan="3" class="p-3 text-center text-text-muted">Tidak ada piutang jatuh tempo.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

</div>

<!-- Data JSON Payloads untuk Chart.js -->
<script type="application/json" id="data-financial-trend"><?php echo json_encode($stats['financial_trend'] ?? ['labels' => [], 'invoices' => [], 'payments' => []]); ?></script>
<script type="application/json" id="data-payment-channels"><?php echo json_encode($stats['payment_channels'] ?? []); ?></script>
<script type="application/json" id="data-aging-receivables"><?php echo json_encode($stats['aging_receivables'] ?? []); ?></script>
<script type="application/json" id="data-top-issues"><?php echo json_encode($topIssueCategories->map(fn($row) => ['name' => $row->issueCategory?->name ?? 'Lainnya', 'total' => (int) $row->total])); ?></script>
<script type="application/json" id="data-pop-distribution"><?php echo json_encode($customersByPopChartData ?? ['labels' => [], 'data' => []]); ?></script>
<script type="application/json" id="data-customer-completeness"><?php echo json_encode($stats['customer_completeness'] ?? ['draft' => 0, 'perlu_dilengkapi' => 0, 'lengkap' => 0, 'siap_billing' => 0]); ?></script>

<?php $__env->stopSection(); ?>

<?php $__env->startSection('scripts'); ?>
<?php echo app('Illuminate\Foundation\Vite')(['resources/js/charts.js']); ?>
<script>
(function () {
    const isDarkMode = document.documentElement.classList.contains('dark');
    const textColor = isDarkMode ? '#CBD5E1' : '#475569';
    const textMutedColor = isDarkMode ? '#94A3B8' : '#64748B';
    const gridColor = isDarkMode ? 'rgba(255, 255, 255, 0.06)' : 'rgba(15, 23, 42, 0.06)';

    function readJson(id) {
        const el = document.getElementById(id);
        if (!el) return null;
        try {
            return JSON.parse(el.textContent);
        } catch (e) {
            return null;
        }
    }

    function renderDashboardCharts() {
        if (typeof window.Chart === 'undefined') {
            return;
        }

        // ── 1. CHART: TREN FINANSIAL BULANAN ────────────────────────────
        const finTrendData = readJson('data-financial-trend');
        const finTrendCanvas = document.getElementById('chart-financial-trend');
        if (finTrendData && finTrendCanvas && finTrendData.labels.length > 0 && !finTrendCanvas._chartInstance) {
            finTrendCanvas._chartInstance = new window.Chart(finTrendCanvas, {
                type: 'bar',
                data: {
                    labels: finTrendData.labels,
                    datasets: [
                        {
                            label: 'Tagihan Terbit',
                            data: finTrendData.invoices,
                            backgroundColor: '#0284c7', // sky-600
                            borderRadius: 6,
                            maxBarThickness: 32,
                        },
                        {
                            label: 'Kas Masuk',
                            data: finTrendData.payments,
                            backgroundColor: '#10b981', // emerald-500
                            borderRadius: 6,
                            maxBarThickness: 32,
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function (ctx) {
                                    return ctx.dataset.label + ': Rp ' + new Intl.NumberFormat('id-ID').format(ctx.raw);
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { color: textMutedColor, font: { size: 11, family: 'Inter' } }
                        },
                        y: {
                            grid: { color: gridColor },
                            ticks: {
                                color: textMutedColor,
                                font: { size: 10, family: 'JetBrains Mono' },
                                callback: function (val) {
                                    return 'Rp ' + (val >= 1000000 ? (val / 1000000) + 'Jt' : (val / 1000) + 'Rb');
                                }
                            }
                        }
                    }
                }
            });
        }

        // ── 2. CHART: KANAL PEMBAYARAN (DONUT) ──────────────────────────
        const paymentChannelsData = readJson('data-payment-channels');
        const paymentChannelsCanvas = document.getElementById('chart-payment-channels');
        if (paymentChannelsData && paymentChannelsCanvas && !paymentChannelsCanvas._chartInstance) {
            const labels = [];
            const dataValues = [];
            const bgColors = ['#6366f1', '#0ea5e9', '#10b981', '#f59e0b', '#94a3b8'];

            Object.keys(paymentChannelsData).forEach(key => {
                const item = paymentChannelsData[key];
                labels.push(item.label);
                dataValues.push(item.amount);
            });

            paymentChannelsCanvas._chartInstance = new window.Chart(paymentChannelsCanvas, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: dataValues,
                        backgroundColor: bgColors,
                        borderWidth: isDarkMode ? 2 : 1,
                        borderColor: isDarkMode ? '#1e293b' : '#ffffff',
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { boxWidth: 10, color: textColor, font: { size: 11 } }
                        },
                        tooltip: {
                            callbacks: {
                                label: function (ctx) {
                                    return ctx.label + ': Rp ' + new Intl.NumberFormat('id-ID').format(ctx.raw);
                                }
                            }
                        }
                    },
                    cutout: '68%'
                }
            });
        }

        // ── 3. CHART: AGING PIUTANG (HORIZONTAL BAR) ───────────────────
        const agingData = readJson('data-aging-receivables');
        const agingCanvas = document.getElementById('chart-aging-receivables');
        if (agingData && agingCanvas && !agingCanvas._chartInstance) {
            agingCanvas._chartInstance = new window.Chart(agingCanvas, {
                type: 'bar',
                data: {
                    labels: ['Lancar', '1-30 Hari', '31-60 Hari', '>60 Hari'],
                    datasets: [{
                        data: [
                            agingData.lancar?.amount ?? 0,
                            agingData.days_1_30?.amount ?? 0,
                            agingData.days_31_60?.amount ?? 0,
                            agingData.days_over_60?.amount ?? 0,
                        ],
                        backgroundColor: ['#10b981', '#f59e0b', '#f97316', '#ef4444'],
                        borderRadius: 4,
                        maxBarThickness: 20,
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function (ctx) {
                                    return 'Rp ' + new Intl.NumberFormat('id-ID').format(ctx.raw);
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { color: gridColor },
                            ticks: {
                                color: textMutedColor,
                                font: { size: 10, family: 'JetBrains Mono' },
                                callback: function (val) {
                                    return 'Rp ' + (val >= 1000000 ? (val / 1000000) + 'Jt' : val);
                                }
                            }
                        },
                        y: {
                            grid: { display: false },
                            ticks: { color: textColor, font: { size: 11 } }
                        }
                    }
                }
            });
        }

        // ── 4. CHART: TOP KATEGORI GANGGUAN (HORIZONTAL BAR) ───────────
        const issuesData = readJson('data-top-issues');
        const issuesCanvas = document.getElementById('chart-top-issues');
        if (issuesData && issuesCanvas && issuesData.length > 0 && !issuesCanvas._chartInstance) {
            issuesCanvas._chartInstance = new window.Chart(issuesCanvas, {
                type: 'bar',
                data: {
                    labels: issuesData.map(i => i.name),
                    datasets: [{
                        data: issuesData.map(i => i.total),
                        backgroundColor: '#f43f5e',
                        borderRadius: 4,
                        maxBarThickness: 16,
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function (ctx) {
                                    return ctx.raw + ' Tiket';
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { color: gridColor },
                            ticks: { precision: 0, color: textMutedColor, font: { size: 10 } }
                        },
                        y: {
                            grid: { display: false },
                            ticks: { color: textColor, font: { size: 11 } }
                        }
                    }
                }
            });
        }

        // ── 5. CHART: SEBARAN PELANGGAN PER CABANG (HORIZONTAL BAR) ─────
        const popData = readJson('data-pop-distribution');
        const popCanvas = document.getElementById('chart-pop-distribution');
        if (popData && popCanvas && popData.labels.length > 0 && !popCanvas._chartInstance) {
            popCanvas._chartInstance = new window.Chart(popCanvas, {
                type: 'bar',
                data: {
                    labels: popData.labels,
                    datasets: [{
                        data: popData.data,
                        backgroundColor: '#0284c7',
                        borderRadius: 4,
                        maxBarThickness: 18,
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function (ctx) {
                                    return ctx.raw + ' Pelanggan';
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { color: gridColor },
                            ticks: { precision: 0, color: textMutedColor, font: { size: 10 } }
                        },
                        y: {
                            grid: { display: false },
                            ticks: { color: textColor, font: { size: 11 } }
                        }
                    }
                }
            });
        }

        // ── 6. CHART: KELENGKAPAN DATA MASTER (DONUT) ───────────────────
        const compData = readJson('data-customer-completeness');
        const compCanvas = document.getElementById('chart-customer-completeness');
        if (compData && compCanvas && !compCanvas._chartInstance) {
            compCanvas._chartInstance = new window.Chart(compCanvas, {
                type: 'doughnut',
                data: {
                    labels: ['Siap Billing', 'Lengkap', 'Perlu Dilengkapi', 'Draft'],
                    datasets: [{
                        data: [
                            compData.siap_billing ?? 0,
                            compData.lengkap ?? 0,
                            compData.perlu_dilengkapi ?? 0,
                            compData.draft ?? 0,
                        ],
                        backgroundColor: ['#10b981', '#0ea5e9', '#f59e0b', '#94a3b8'],
                        borderWidth: isDarkMode ? 2 : 1,
                        borderColor: isDarkMode ? '#1e293b' : '#ffffff',
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { boxWidth: 10, color: textColor, font: { size: 10 } }
                        }
                    },
                    cutout: '65%'
                }
            });
        }
    }

    if (window.Chart) {
        renderDashboardCharts();
    } else {
        window.addEventListener('chart:ready', renderDashboardCharts);
    }
    document.addEventListener('DOMContentLoaded', renderDashboardCharts);
})();
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/dashboard.blade.php ENDPATH**/ ?>