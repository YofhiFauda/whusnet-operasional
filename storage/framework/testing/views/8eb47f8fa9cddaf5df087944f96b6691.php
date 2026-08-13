<?php $__env->startSection('title', 'Worklist Saya - Whusnet Operasional'); ?>
<?php $__env->startSection('page_title', 'Worklist Saya'); ?>

<?php $__env->startSection('content'); ?>
    <?php echo $__env->make('partials.collector-realtime', ['channels' => ['App.Models.User.' . auth()->id()], 'audiens' => 'kolektor'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<?php
    $totalTunggakan = $invoices->getCollection()->sum('remaining_amount');
    $jumlahPelanggan = $invoices->getCollection()->pluck('customer_id')->unique()->count();
?>

<div x-data="{ 
    visitModalOpen: false, 
    selectedCustomer: { id: null, name: '', cid: '' } 
}" 
@open-visit-modal.window="visitModalOpen = true; selectedCustomer = $event.detail"
class="space-y-6">

    
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 mb-1 font-medium">
                <span>Operasional</span>
                <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span>Billing & Tagihan</span>
                <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span class="text-sky-600 dark:text-sky-400 font-semibold">Worklist Saya</span>
            </div>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-slate-100 tracking-tight">Worklist Kolektor</h1>
            <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400 mt-0.5">
                Daftar pelanggan yang perlu didatangi dalam jendela tagih (<?php echo e($dueWindowDays); ?> hari sebelum jatuh tempo).
            </p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700/80 text-slate-600 dark:text-slate-300 text-xs font-semibold">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                Rute Hari Ini
            </span>
        </div>
    </div>

    
    <?php if($errors->any()): ?>
        <?php if (isset($component)) { $__componentOriginal746de018ded8594083eb43be3f1332e1 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal746de018ded8594083eb43be3f1332e1 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.alert','data' => ['variant' => 'error','title' => 'Setoran Gagal','class' => 'rounded-2xl shadow-xs']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.alert'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'error','title' => 'Setoran Gagal','class' => 'rounded-2xl shadow-xs']); ?>
            <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div><?php echo e($error); ?></div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
         <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal746de018ded8594083eb43be3f1332e1)): ?>
<?php $attributes = $__attributesOriginal746de018ded8594083eb43be3f1332e1; ?>
<?php unset($__attributesOriginal746de018ded8594083eb43be3f1332e1); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal746de018ded8594083eb43be3f1332e1)): ?>
<?php $component = $__componentOriginal746de018ded8594083eb43be3f1332e1; ?>
<?php unset($__componentOriginal746de018ded8594083eb43be3f1332e1); ?>
<?php endif; ?>
    <?php endif; ?>

    <?php if(session('success')): ?>
        <?php if (isset($component)) { $__componentOriginal746de018ded8594083eb43be3f1332e1 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal746de018ded8594083eb43be3f1332e1 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ui.alert','data' => ['variant' => 'success','class' => 'rounded-2xl shadow-xs']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ui.alert'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['variant' => 'success','class' => 'rounded-2xl shadow-xs']); ?><?php echo e(session('success')); ?> <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal746de018ded8594083eb43be3f1332e1)): ?>
<?php $attributes = $__attributesOriginal746de018ded8594083eb43be3f1332e1; ?>
<?php unset($__attributesOriginal746de018ded8594083eb43be3f1332e1); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal746de018ded8594083eb43be3f1332e1)): ?>
<?php $component = $__componentOriginal746de018ded8594083eb43be3f1332e1; ?>
<?php unset($__componentOriginal746de018ded8594083eb43be3f1332e1); ?>
<?php endif; ?>
    <?php endif; ?>

    
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
        
        <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-4 shadow-xs relative overflow-hidden transition-all hover:border-violet-300 dark:hover:border-violet-600/50">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Pelanggan</span>
                <div class="w-9 h-9 rounded-xl bg-violet-50 dark:bg-violet-500/10 text-violet-600 dark:text-violet-400 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                </div>
            </div>
            <div class="mt-2 flex items-baseline gap-2 min-w-0">
                <span class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-slate-100 font-mono truncate"><?php echo e(number_format($jumlahPelanggan, 0, ',', '.')); ?></span>
                <span class="text-xs text-slate-500 dark:text-slate-400 font-medium shrink-0">Orang</span>
            </div>
            <div class="mt-1 text-[11px] text-slate-400 dark:text-slate-500 font-medium">
                Target penagihan rute
            </div>
        </div>

        
        <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-4 shadow-xs relative overflow-hidden transition-all hover:border-sky-300 dark:hover:border-sky-600/50">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">Jumlah Tagihan</span>
                <div class="w-9 h-9 rounded-xl bg-sky-50 dark:bg-sky-500/10 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
            </div>
            <div class="mt-2 flex items-baseline gap-2 min-w-0">
                <span class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-slate-100 font-mono truncate"><?php echo e(number_format($invoices->total(), 0, ',', '.')); ?></span>
                <span class="text-xs text-slate-500 dark:text-slate-400 font-medium shrink-0">Lembar</span>
            </div>
            <div class="mt-1 text-[11px] text-slate-400 dark:text-slate-500 font-medium">
                Invoice aktif rute ini
            </div>
        </div>

        
        <div class="bg-white dark:bg-slate-800/90 border border-amber-200/80 dark:border-amber-500/30 rounded-2xl p-4 shadow-xs relative overflow-hidden transition-all hover:border-amber-400 dark:hover:border-amber-500/60">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400">Nilai di Halaman Ini</span>
                <div class="w-9 h-9 rounded-xl bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
            <div class="mt-2 min-w-0">
                <span class="text-lg sm:text-2xl font-bold text-amber-700 dark:text-amber-400 font-mono truncate block">Rp <?php echo e(number_format((float) $totalTunggakan, 0, ',', '.')); ?></span>
            </div>
            <div class="mt-1 text-[11px] text-slate-400 dark:text-slate-500 font-medium">
                Total sisa tagihan tertera
            </div>
        </div>
    </div>

    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        
        <div class="lg:col-span-2 bg-gradient-to-br from-slate-900 to-slate-800 text-white rounded-2xl p-4 sm:p-5 shadow-sm relative overflow-hidden flex flex-col justify-between">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 shrink-0"></span>
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-300 truncate">Saldo Belum Disetor (Kas di Tangan)</span>
                    </div>
                    <div class="font-bold text-xl sm:text-3xl text-white font-mono mt-2 tracking-tight truncate">
                        Rp <?php echo e(number_format($balance, 0, ',', '.')); ?>

                    </div>
                    <div class="text-xs text-slate-300 mt-1 flex flex-wrap items-center gap-2">
                        <span class="px-2 py-0.5 rounded-full bg-slate-700/80 text-emerald-300 text-[11px] font-semibold border border-slate-600">
                            <?php echo e($unsettledCount); ?> transaksi
                        </span>
                        <span>belum diserahkan ke admin</span>
                    </div>
                </div>

                <?php if($canDeposit && $balance > 0): ?>
                    <form action="<?php echo e(route('collector-worklist.deposit')); ?>" method="POST" class="shrink-0 w-full sm:w-auto"
                          data-confirm="Setorkan seluruh saldo Rp<?php echo e(number_format($balance, 0, ',', '.')); ?> (<?php echo e($unsettledCount); ?> pembayaran) ke admin? Saldo Anda jadi nol dan menunggu verifikasi.">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="idempotency_key" value="<?php echo e('deposit-'.auth()->id().'-'.now()->timestamp.'-'.\Illuminate\Support\Str::random(8)); ?>">
                        <button type="submit" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-xs sm:text-sm px-5 py-3 rounded-xl transition-all shadow-md active:scale-95 cursor-pointer">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                            <span>Setor ke Admin</span>
                        </button>
                    </form>
                <?php endif; ?>
            </div>

            <?php if($pendingDeposits->isNotEmpty()): ?>
                <div class="mt-4 pt-4 border-t border-slate-700/80 space-y-2">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Riwayat Pending Setoran</div>
                    <?php $__currentLoopData = $pendingDeposits; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $deposit): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="flex flex-wrap items-center justify-between gap-2 text-xs bg-slate-800/80 p-2.5 rounded-xl border border-slate-700/60">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="font-mono text-slate-300 font-semibold truncate"><?php echo e($deposit->deposit_number); ?></span>
                            </div>
                            <?php if($deposit->status === \App\Enums\DepositStatus::MENUNGGU_VERIFIKASI): ?>
                                <span class="px-2.5 py-1 rounded-lg bg-amber-500/20 text-amber-300 border border-amber-500/30 text-[11px] font-semibold flex items-center gap-1.5 shrink-0">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-ping shrink-0"></span>
                                    Menunggu Verifikasi Admin
                                </span>
                            <?php else: ?>
                                <span class="px-2.5 py-1 rounded-lg bg-rose-500/20 text-rose-300 border border-rose-500/30 text-[11px] font-semibold shrink-0">
                                    Kurang setor Rp <?php echo e(number_format($deposit->outstandingShortfall(), 0, ',', '.')); ?>

                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            <?php endif; ?>
        </div>

        
        <div class="rounded-2xl p-4 sm:p-5 border shadow-xs flex flex-col justify-between transition-all <?php echo e($outstandingShortfall > 0 ? 'bg-rose-50/80 dark:bg-rose-500/10 border-rose-200 dark:border-rose-500/30' : 'bg-white dark:bg-slate-800/90 border-slate-200/80 dark:border-slate-700/80'); ?>">
            <div class="min-w-0">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider <?php echo e($outstandingShortfall > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-slate-400 dark:text-slate-500'); ?>">Kurang Setor</span>
                    <div class="w-8 h-8 rounded-xl <?php echo e($outstandingShortfall > 0 ? 'bg-rose-100 dark:bg-rose-500/20 text-rose-600 dark:text-rose-400' : 'bg-slate-100 dark:bg-slate-700 text-slate-400'); ?> flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </div>
                </div>
                <div class="font-bold text-xl sm:text-3xl mt-2 font-mono truncate <?php echo e($outstandingShortfall > 0 ? 'text-rose-700 dark:text-rose-400' : 'text-slate-900 dark:text-slate-100'); ?>">
                    Rp <?php echo e(number_format($outstandingShortfall, 0, ',', '.')); ?>

                </div>
            </div>
            <p class="text-xs <?php echo e($outstandingShortfall > 0 ? 'text-rose-600 dark:text-rose-400/90' : 'text-slate-400 dark:text-slate-500'); ?> mt-3">
                Nominal ini tidak otomatis nol saat Anda menyetor. Lunasi lewat setoran berikutnya.
            </p>
        </div>
    </div>

    
    <?php if($todayVisits->isNotEmpty()): ?>
        <div x-data="{ expanded: false }" class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl p-3.5 shadow-xs transition-all">
            <div class="flex items-center justify-between gap-2">
                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-sky-500 animate-pulse"></span>
                    <span>Kunjungan Hari Ini (<?php echo e($todayVisits->count()); ?>)</span>
                </div>
                
                <?php if($todayVisits->count() > 3): ?>
                    <button type="button" @click="expanded = !expanded" class="text-xs font-semibold text-sky-600 dark:text-sky-400 hover:underline flex items-center gap-1 cursor-pointer">
                        <span x-text="expanded ? 'Sembunyikan' : 'Lihat Semua (<?php echo e($todayVisits->count()); ?>)'"></span>
                        <svg class="w-3.5 h-3.5 transition-transform duration-200" :class="expanded ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                <?php endif; ?>
            </div>

            
            <div x-show="!expanded" class="mt-2 flex items-center gap-2 overflow-x-auto pb-1 custom-scrollbar">
                <?php $__currentLoopData = $todayVisits; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $visit): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700/80 text-xs shrink-0 shadow-2xs">
                        <span class="font-semibold text-slate-800 dark:text-slate-200 whitespace-nowrap"><?php echo e($visit->customer->full_name ?? '-'); ?></span>
                        <span class="px-1.5 py-0.5 rounded-lg text-[10px] font-bold shrink-0 <?php echo e($visit->result === \App\Enums\VisitResult::BAYAR ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400' : 'bg-slate-200/70 text-slate-700 dark:bg-slate-700 dark:text-slate-300'); ?>">
                            <?php echo e($visit->result->label()); ?><?php if($visit->promised_date): ?> (<?php echo e($visit->promised_date->format('d/m')); ?>)<?php endif; ?>
                        </span>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>

            
            <div x-show="expanded" x-cloak class="mt-2.5 flex flex-wrap gap-2 pt-2 border-t border-slate-100 dark:border-slate-700/60">
                <?php $__currentLoopData = $todayVisits; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $visit): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200/80 dark:border-slate-700/80 text-xs shadow-2xs">
                        <span class="font-semibold text-slate-800 dark:text-slate-200"><?php echo e($visit->customer->full_name ?? '-'); ?></span>
                        <span class="px-1.5 py-0.5 rounded-lg text-[10px] font-bold <?php echo e($visit->result === \App\Enums\VisitResult::BAYAR ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400' : 'bg-slate-200/70 text-slate-700 dark:bg-slate-700 dark:text-slate-300'); ?>">
                            <?php echo e($visit->result->label()); ?><?php if($visit->promised_date): ?> (janji <?php echo e($visit->promised_date->format('d/m/Y')); ?>)<?php endif; ?>
                        </span>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        </div>
    <?php endif; ?>

    <div id="batch-alert" class="hidden text-sm rounded-2xl p-4 shadow-xs"></div>

    
    <?php if($canPay): ?>
        <?php echo $__env->make('partials.collector-pay-table', [
            'invoices' => $invoices,
            'canLogVisit' => $canLogVisit,
            'emptyMessage' => 'Tidak ada pelanggan yang perlu didatangi saat ini.',
        ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

        <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-800/40 px-4 py-2.5 rounded-xl border border-slate-200/60 dark:border-slate-700/60">
            <svg class="w-4 h-4 text-sky-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>Pelanggan mencicil? Ubah nominalnya — sisa tagihan tetap muncul di daftar sampai lunas. Kelebihan bayar dikembalikan tunai di tempat.</span>
        </div>

        <?php $__env->startPush('scripts'); ?>
            <?php echo $__env->make('partials.collector-pay-script', [
                'storeUrl' => route('collector-worklist.pay'),
                'keyPrefix' => 'worklist-' . auth()->id(),
                'colspan' => 9,
                'emptyMessage' => 'Tidak ada pelanggan yang perlu didatangi saat ini.',
            ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php $__env->stopPush(); ?>
    <?php else: ?>
        
        <div class="bg-white dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/80 rounded-2xl overflow-hidden shadow-xs">
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left text-sm text-slate-700 dark:text-slate-200 min-w-0 sm:min-w-full">
                    <thead class="hidden sm:table-header-group">
                        <tr class="bg-slate-50/80 dark:bg-slate-800/60 border-b border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400 font-semibold text-xs uppercase tracking-wider">
                            <th class="px-6 py-4">Pelanggan</th>
                            <th class="px-6 py-4">No. Tagihan</th>
                            <th class="px-6 py-4">Jatuh Tempo</th>
                            <th class="px-6 py-4 text-right">Sisa Tagihan</th>
                        </tr>
                    </thead>
                    <tbody class="block sm:table-row-group divide-y-0 sm:divide-y p-3 sm:p-0 space-y-3 sm:space-y-0 divide-slate-100 dark:divide-slate-700/50">
                        <?php $__empty_1 = true; $__currentLoopData = $invoices; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $invoice): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <tr class="block sm:table-row bg-white dark:bg-slate-800/90 sm:bg-transparent rounded-2xl sm:rounded-none border border-slate-200/80 dark:border-slate-700/80 sm:border-x-0 sm:border-t-0 sm:border-b p-4 sm:p-0 shadow-xs sm:shadow-none hover:bg-slate-50/80 dark:hover:bg-slate-700/30 transition-colors">
                                <td class="block sm:table-cell px-0 sm:px-6 py-1 sm:py-4">
                                    <div class="font-bold text-slate-900 dark:text-slate-100"><?php echo e($invoice->customer->full_name ?? '-'); ?></div>
                                    <div class="text-[10px] text-slate-400 dark:text-slate-500 font-mono"><?php echo e($invoice->customer->cid ?? $invoice->customer->customer_code ?? '-'); ?></div>
                                </td>
                                <td class="block sm:table-cell px-0 sm:px-6 py-1 sm:py-4 font-mono text-xs">
                                    <span class="sm:hidden text-[10px] font-bold text-slate-400 uppercase tracking-wider block">No. Tagihan:</span>
                                    <?php echo e($invoice->invoice_number); ?>

                                </td>
                                <td class="block sm:table-cell px-0 sm:px-6 py-1 sm:py-4 text-xs">
                                    <span class="sm:hidden text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Jatuh Tempo:</span>
                                    <?php echo e($invoice->due_date?->format('d/m/Y') ?? '-'); ?>

                                </td>
                                <td class="block sm:table-cell px-0 sm:px-6 py-1 sm:py-4 sm:text-right font-mono font-bold text-amber-600 dark:text-amber-400">
                                    <span class="sm:hidden text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Sisa Tagihan:</span>
                                    Rp <?php echo e(number_format((float) $invoice->remaining_amount, 0, ',', '.')); ?>

                                </td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr>
                                <td colspan="4" class="px-6 py-12 text-center text-sm text-slate-500 dark:text-slate-400">Tidak ada pelanggan yang perlu didatangi saat ini.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-700">
                <?php echo e($invoices->links()); ?>

            </div>
        </div>
    <?php endif; ?>

    
    <?php if($canLogVisit): ?>
        <div x-show="visitModalOpen" 
             x-cloak 
             x-on:keydown.escape.window="visitModalOpen = false" 
             x-effect="document.body.classList.toggle('overflow-hidden', visitModalOpen)"
             class="fixed inset-0 z-[80] overflow-y-auto"
             style="display: none;">
             
            <!-- Backdrop Overlay -->
            <div x-show="visitModalOpen" 
                 x-transition.opacity 
                 @click="visitModalOpen = false" 
                 class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity">
            </div>

            <!-- Responsive Dialog Panel Container -->
            <div class="flex min-h-full items-end sm:items-center justify-center p-0 sm:p-4 text-left">
                <div x-show="visitModalOpen"
                     x-transition:enter="ease-out duration-300"
                     x-transition:enter-start="translate-y-full sm:translate-y-0 sm:scale-95 opacity-0"
                     x-transition:enter-end="translate-y-0 sm:scale-100 opacity-100"
                     x-transition:leave="ease-in duration-200"
                     x-transition:leave-start="translate-y-0 sm:scale-100 opacity-100"
                     x-transition:leave-end="translate-y-full sm:translate-y-0 sm:scale-95 opacity-0"
                     @click.stop
                     class="relative w-full sm:max-w-md bg-white dark:bg-slate-900 rounded-t-3xl sm:rounded-2xl border border-slate-200 dark:border-slate-800 shadow-2xl overflow-hidden p-5 sm:p-6 space-y-4 max-h-[90vh] overflow-y-auto">
                     
                    <!-- Mobile Drag Indicator Handle -->
                    <div class="sm:hidden flex justify-center pb-1">
                        <div class="w-12 h-1.5 rounded-full bg-slate-300 dark:bg-slate-700"></div>
                    </div>

                    <!-- Dialog Header -->
                    <div class="flex items-start justify-between">
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-sky-600 dark:text-sky-400">Catat Kunjungan Tanpa Hasil</span>
                            <h3 class="text-base font-bold text-slate-900 dark:text-slate-100 mt-0.5" x-text="selectedCustomer.name"></h3>
                            <div class="text-xs text-slate-400 font-mono" x-text="selectedCustomer.cid"></div>
                        </div>
                        <button type="button" @click="visitModalOpen = false" class="p-1.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-xl transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <!-- Form -->
                    <form action="<?php echo e(route('collector-worklist.visits.store')); ?>" method="POST" class="space-y-4" data-confirm="Simpan catatan kunjungan ini?">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="customer_id" :value="selectedCustomer.id">

                        <div class="space-y-1">
                            <label class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Hasil Kunjungan</label>
                            <select name="result" required class="w-full text-xs sm:text-sm px-3.5 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                                <?php $__currentLoopData = \App\Enums\VisitResult::manualValues(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($value); ?>"><?php echo e(\App\Enums\VisitResult::from($value)->label()); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        </div>

                        <div class="space-y-1">
                            <label class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                Tgl Janji Bayar <span class="font-normal text-slate-400 lowercase">(opsional)</span>
                            </label>
                            <input type="date" name="promised_date" min="<?php echo e(now()->format('Y-m-d')); ?>" class="w-full text-xs sm:text-sm px-3.5 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500">
                        </div>

                        <div class="space-y-1">
                            <label class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Catatan Kunjungan</label>
                            <textarea name="note" rows="3" maxlength="1000" placeholder="mis. Rumah kosong, janji bayar tanggal 15" class="w-full text-xs sm:text-sm px-3.5 py-2.5 border border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500"></textarea>
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-2">
                            <button type="button" @click="visitModalOpen = false" class="px-4 py-2.5 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl transition-all">
                                Batal
                            </button>
                            <button type="submit" class="inline-flex items-center gap-2 px-5 py-2.5 bg-slate-900 hover:bg-slate-800 dark:bg-slate-700 dark:hover:bg-slate-600 text-white font-semibold text-xs rounded-xl shadow-xs transition-all active:scale-95 cursor-pointer">
                                <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                <span>Simpan Kunjungan</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php $__env->stopSection(); ?>



<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/collector-worklist/index.blade.php ENDPATH**/ ?>