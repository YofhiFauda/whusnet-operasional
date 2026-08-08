<?php $__env->startSection('title', 'Perangkat & Pemasangan - Whusnet Operasional'); ?>
<?php $__env->startSection('page_title', 'Perangkat & Pemasangan'); ?>

<?php $__env->startSection('content'); ?>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <h1 class="text-xl font-bold text-text-main tracking-tight">Perangkat & Pemasangan: <?php echo e($customer->full_name); ?></h1>
</div>

<div class="space-y-6">
    
    <?php if(auth()->user()->hasPermission('customers.detail.installation.view')): ?>
    <div class="bg-surface border border-border rounded-lg p-6">
        <?php echo $__env->make('customers.tabs._installation', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    </div>
    <?php endif; ?>

    <?php if(auth()->user()->hasPermission('customers.detail.devices.view')): ?>
    <div class="bg-surface border border-border rounded-lg p-6">
        <?php echo $__env->make('customers.tabs._device', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    </div>
    <?php endif; ?>
</div>

<script>
    function openInstallationModal() {
        const modal = document.getElementById('installation-modal');
        if (modal) modal.classList.remove('hidden');
    }

    function closeInstallationModal() {
        const modal = document.getElementById('installation-modal');
        if (modal) modal.classList.add('hidden');
    }

    function openTestReportModal() {
        const modal = document.getElementById('test-report-modal');
        if (modal) modal.classList.remove('hidden');
    }

    function closeTestReportModal() {
        const modal = document.getElementById('test-report-modal');
        if (modal) modal.classList.add('hidden');
    }

    function openDeviceModal() {
        const modal = document.getElementById('device-modal');
        if (modal) modal.classList.remove('hidden');
    }

    function closeDeviceModal() {
        const modal = document.getElementById('device-modal');
        if (modal) modal.classList.add('hidden');
    }
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/customers/fieldwork.blade.php ENDPATH**/ ?>