
<?php
    $installation = $installation ?? $customer->latestInstallation;
?>
<td class="px-4 py-3.5 text-center" id="customer-status-cell-<?php echo e($customer->id); ?>">
    <?php echo $__env->make('verifications.partials.queue-status-badge', ['customer' => $customer], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</td>
<td class="px-4 py-3.5" id="customer-live-cell-<?php echo e($customer->id); ?>">
    <?php echo $__env->make('verifications.partials.queue-timer', ['customer' => $customer, 'installation' => $installation, 'idPrefix' => ''], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</td>
<td class="px-4 py-3.5 text-right whitespace-nowrap" id="customer-action-cell-<?php echo e($customer->id); ?>">
    <?php echo $__env->make('verifications.partials.queue-actions', ['customer' => $customer, 'layout' => 'row'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</td>
<?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/verifications/partials/queue-status-cells.blade.php ENDPATH**/ ?>