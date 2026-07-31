<?php $__env->startSection('title', 'Antrean Survey - Whusnet Operasional'); ?>
<?php $__env->startSection('page_title', 'Antrean Survey Lapangan'); ?>

<?php $__env->startSection('content'); ?>
<!-- Top Action Bar -->
<div class="flex justify-between items-center mb-6">
    <h3 class="text-text-main text-sm font-semibold uppercase tracking-wider">Antrean Survey Pelanggan</h3>
</div>

<!-- Filter & Search Panel -->
<div class="bg-surface border border-border rounded-lg p-6 mb-6">
    <form action="<?php echo e(route('surveys.queue')); ?>" method="GET" class="flex flex-col sm:flex-row gap-4 items-end">
        <!-- Search -->
        <div class="flex-1 w-full">
            <label for="search" class="block text-xs font-semibold text-text-muted mb-2">CARI PELANGGAN</label>
            <input type="text" name="search" id="search" value="<?php echo e(request('search')); ?>" placeholder="Cari nama, No. HP, atau ID Lama..." class="w-full font-sans text-sm px-3 py-2 border border-border rounded-md bg-background text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25">
        </div>

        <!-- Action Buttons -->
        <div class="flex gap-2 w-full sm:w-auto">
            <button type="submit" class="flex-1 sm:flex-none text-sm font-semibold py-2 px-6 rounded-md text-white transition-colors cursor-pointer focus:outline-none focus:ring-2 focus:ring-primary/25" style="background:var(--color-primary)">
                Cari
            </button>
            <a href="<?php echo e(route('surveys.queue')); ?>" class="flex-1 sm:flex-none bg-surface-muted hover:bg-border text-text-secondary text-sm font-semibold py-2 px-4 rounded-md transition-colors cursor-pointer text-center focus:outline-none border border-border">
                Reset
            </a>
        </div>
    </form>
</div>

<!-- Table Content -->
<div class="bg-surface border border-border rounded-lg overflow-hidden">
    <div class="border-b border-border px-6 py-3 flex items-center justify-between" style="background:var(--color-primary-soft)">
        <span class="text-sm font-bold uppercase tracking-wider" style="color:var(--color-primary)">Daftar Antrean Survey</span>
    </div>

    <!-- Table Container -->
    <div class="overflow-x-hidden sm:overflow-x-auto bg-surface">
        <table class="w-full border-collapse text-left text-sm text-text-main">
            <thead class="hidden sm:table-header-group">
                <tr class="bg-surface-muted border-b border-border text-text-muted font-semibold text-xs">
                    <th class="px-6 py-3.5 w-12 text-center">NO</th>
                    <th class="px-6 py-3.5">ID</th>
                    <th class="px-6 py-3.5">NAMA</th>
                    <th class="px-6 py-3.5">HP</th>
                    <th class="px-6 py-3.5">DESA</th>
                    <th class="px-6 py-3.5 text-center">STATUS</th>
                    <th class="px-6 py-3.5">INSERTED AT</th>
                    <th class="px-6 py-3.5 text-center">SISA SLA</th>
                    <th class="px-6 py-3.5 text-right">ACTION</th>
                </tr>
            </thead>
            <tbody class="block sm:table-row-group divide-y sm:divide-y-0 divide-border">
                <?php $__empty_1 = true; $__currentLoopData = $customers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $customer): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <?php
                    $survey = $customer->latestSurvey;
                ?>
                <tr class="hover:bg-surface-muted transition-colors flex flex-col sm:table-row p-4 sm:p-0 border-b sm:border-0 border-border">
                    <td class="hidden sm:table-cell px-6 py-3.5 text-center text-text-muted font-mono"><?php echo e($loop->iteration); ?></td>
                    
                    <td class="flex justify-between items-center sm:table-cell px-0 py-1 sm:px-6 sm:py-3.5">
                        <span class="sm:hidden text-[10px] font-bold text-text-muted uppercase">ID</span>
                        <span class="whitespace-nowrap font-mono text-text-main font-semibold sm:font-normal"><?php echo e($customer->display_id); ?></span>
                    </td>
                    
                    <td class="flex justify-between items-center sm:table-cell px-0 py-1 sm:px-6 sm:py-3.5">
                        <span class="sm:hidden text-[10px] font-bold text-text-muted uppercase">NAMA</span>
                        <span class="font-medium text-text-main"><?php echo e($customer->full_name); ?></span>
                    </td>
                    
                    <td class="flex justify-between items-center sm:table-cell px-0 py-1 sm:px-6 sm:py-3.5">
                        <span class="sm:hidden text-[10px] font-bold text-text-muted uppercase">HP</span>
                        <span class="font-mono text-text-secondary"><?php echo e($customer->primary_phone); ?></span>
                    </td>
                    
                    <td class="flex justify-between items-center sm:table-cell px-0 py-1 sm:px-6 sm:py-3.5">
                        <span class="sm:hidden text-[10px] font-bold text-text-muted uppercase">DESA</span>
                        <span class="font-medium text-text-secondary"><?php echo e($customer->village->name ?? '-'); ?></span>
                    </td>
                    
                    <td class="flex justify-between items-center sm:table-cell px-0 py-1 sm:px-6 sm:py-3.5 sm:text-center">
                        <span class="sm:hidden text-[10px] font-bold text-text-muted uppercase">STATUS</span>
                        <div>
                            <?php if($customer->status === 'waiting_survey'): ?>
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-bold tracking-wide uppercase border" style="background:var(--color-warning-bg); color:var(--color-warning); border-color:var(--color-warning-border)">
                                    <span class="w-1.5 h-1.5 rounded-full" style="background:var(--color-warning)"></span> Menunggu Survey
                                </span>
                            <?php elseif($customer->status === 'survey_in_progress'): ?>
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-bold tracking-wide uppercase border" style="background:var(--color-info-bg); color:var(--color-info); border-color:var(--color-info-border)">
                                    <span class="w-1.5 h-1.5 rounded-full animate-pulse" style="background:var(--color-info)"></span> Proses Survey
                                </span>
                            <?php endif; ?>
                        </div>
                    </td>
                    
                    <td class="flex justify-between items-center sm:table-cell px-0 py-1 sm:px-6 sm:py-3.5">
                        <span class="sm:hidden text-[10px] font-bold text-text-muted uppercase">INSERTED AT</span>
                        <span class="font-mono text-xs text-text-secondary"><?php echo e($customer->created_at->format('Y-m-d H:i:s')); ?></span>
                    </td>
                    
                    <td class="flex justify-between items-center sm:table-cell px-0 py-1 sm:px-6 sm:py-3.5 sm:text-center">
                        <span class="sm:hidden text-[10px] font-bold text-text-muted uppercase">SISA SLA</span>
                        <?php if(in_array($customer->status, ['waiting_survey', 'survey_in_progress'])): ?>
                            <?php if (isset($component)) { $__componentOriginalb8d3d89751f3d81017aa8a59bd985fb5 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb8d3d89751f3d81017aa8a59bd985fb5 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.countdown-timer','data' => ['deadline' => ''.e($customer->created_at->addDay()->toIso8601String()).'','totalSeconds' => 86400,'label' => 'Sisa Waktu Survey','compact' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('countdown-timer'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['deadline' => ''.e($customer->created_at->addDay()->toIso8601String()).'','total-seconds' => 86400,'label' => 'Sisa Waktu Survey','compact' => true]); ?>
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
                        <?php else: ?>
                            <span class="text-text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    
                    <td class="flex justify-end items-center sm:table-cell px-0 pt-3 sm:px-6 sm:py-3.5 mt-2 sm:mt-0 border-t sm:border-0 border-border border-dashed sm:text-right whitespace-nowrap">
                        <div class="flex items-center w-full sm:w-auto justify-end gap-2">
                            <a href="<?php echo e(route('customers.verification.admin', $customer)); ?>" class="text-text-muted hover:text-primary transition-colors p-1" title="Detail">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                            </a>
                            
                            <?php if($customer->status === 'waiting_survey'): ?>
                                <form action="<?php echo e(route('customers.survey.start', $customer)); ?>" method="POST" onsubmit="event.preventDefault(); window.confirmAction('Mulai proses survey untuk pelanggan ini?', this);" class="flex-1 sm:flex-none">
                                    <?php echo csrf_field(); ?>
                                    <button type="submit" class="w-full sm:w-auto text-[11px] font-bold uppercase tracking-wider py-1.5 px-3 rounded shadow-sm transition-colors cursor-pointer text-white" style="background:var(--color-warning)">
                                        Mulai Survey
                                    </button>
                                </form>
                            <?php elseif($customer->status === 'survey_in_progress'): ?>
                                <a href="<?php echo e(route('customers.survey.report', $customer)); ?>" class="flex-1 sm:flex-none text-center w-full sm:w-auto text-[11px] font-bold uppercase tracking-wider py-1.5 px-3 rounded shadow-sm transition-colors cursor-pointer inline-block text-white" style="background:var(--color-success)">
                                    Lapor Data
                                </a>
                            <?php endif; ?>

                            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('customers.detail.survey.reject')): ?>
                                <button type="button"
                                        onclick="openCancelSurveyModal('<?php echo e(route('customers.survey.cancel', $customer)); ?>', '<?php echo e(addslashes($customer->full_name)); ?>')"
                                        class="flex-1 sm:flex-none text-[11px] font-bold uppercase tracking-wider py-1.5 px-3 rounded shadow-sm transition-colors cursor-pointer text-white"
                                        style="background:var(--color-error)">
                                    Batalkan
                                </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr class="block sm:table-row">
                    <td colspan="9" class="px-6 py-8 text-center text-text-muted">
                        <div class="flex flex-col items-center justify-center gap-2">
                            <svg class="w-8 h-8 text-border" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" /></svg>
                            <span class="text-sm font-medium">Tidak ada antrean survey saat ini.</span>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <?php if($customers->hasPages()): ?>
        <div class="border-t border-border px-6 py-4 bg-surface-muted">
            <?php echo e($customers->links()); ?>

        </div>
    <?php endif; ?>
</div>

<?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('customers.detail.survey.reject')): ?>
<div id="cancel-survey-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
    <div class="bg-surface border border-border rounded-lg shadow-lg w-full max-w-md p-5">
        <h4 class="text-sm font-bold text-text-main mb-1">Batalkan Survey — Tidak Layak Pasang</h4>
        <p class="text-xs text-text-muted mb-4">
            Survey <strong id="cancel-survey-modal-name"></strong> akan diubah statusnya menjadi <strong>ditolak</strong>
            dan tidak bisa lanjut ke tahap pemasangan. Tindakan ini tidak bisa dibatalkan.
        </p>
        <form id="cancel-survey-modal-form" method="POST">
            <?php echo csrf_field(); ?>
            <label class="block text-xs font-semibold text-text-secondary mb-1">Alasan <span class="text-error">*</span></label>
            <textarea name="reason" rows="3" required class="w-full text-xs border border-border rounded-md px-3 py-2 mb-4" placeholder="Contoh: Alamat tidak ditemukan, lokasi di luar jangkauan ODP, pelanggan menolak, dll."></textarea>
            <div class="flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('cancel-survey-modal').classList.add('hidden')" class="btn-secondary text-xs px-3 py-1.5">Batal</button>
                <button type="submit" class="text-xs px-3 py-1.5 rounded-md font-semibold text-white" style="background:var(--color-error);">Ya, Batalkan Survey</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openCancelSurveyModal(actionUrl, customerName) {
        document.getElementById('cancel-survey-modal-form').setAttribute('action', actionUrl);
        document.getElementById('cancel-survey-modal-name').textContent = customerName;
        document.getElementById('cancel-survey-modal').classList.remove('hidden');
    }
</script>
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/surveys/queue.blade.php ENDPATH**/ ?>