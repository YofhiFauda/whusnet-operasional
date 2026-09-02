<?php
    $pageTitle = match ($statusGroup) {
        'survey' => 'Survey Pelanggan',
        'verification' => 'Verifikasi Pelanggan',
        default => 'List Pelanggan',
    };
?>

<?php $__env->startSection('title', $pageTitle . ' - Whusnet Operasional'); ?>
<?php $__env->startSection('page_title', $pageTitle); ?>
<?php $__env->startSection('breadcrumb_parent', 'Pelanggan'); ?>
<?php $__env->startSection('breadcrumb_parent_url', '/customers'); ?>

<?php $__env->startSection('content'); ?>
<?php echo $__env->make('customers.partials._list_styles', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('customers.partials._list_header', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('customers.partials._list_stats', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('customers.partials._list_filters', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>




<div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200/80 dark:border-slate-800/80 shadow-sm overflow-hidden mb-6">
    <?php if(!empty($statusGroup)): ?>
    
    <div class="border-b border-slate-200 dark:border-slate-800 px-6 py-3 flex items-center justify-between bg-slate-50/50 dark:bg-slate-900/30">
        <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">
            <?php if($statusGroup === 'survey'): ?> Daftar Survey Pelanggan
            <?php elseif($statusGroup === 'verification'): ?> Daftar Verifikasi Pelanggan
            <?php endif; ?>
        </span>
        <a href="<?php echo e(route('customers.index')); ?>" class="text-xs text-sky-600 dark:text-sky-400 hover:underline">Lihat Semua Pelanggan</a>
    </div>
    <?php endif; ?>

    <?php echo $__env->make('customers.partials._list_table', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <?php echo $__env->make('customers.partials._list_pagination', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</div>

<?php echo $__env->make('customers.partials._quick_hub_modal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>


<?php $__env->startSection('scripts'); ?>
<?php echo $__env->make('customers.partials._list_scripts', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/customers/index.blade.php ENDPATH**/ ?>