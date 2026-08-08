<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'disabled' => false,
    'error' => false,
]));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter(([
    'disabled' => false,
    'error' => false,
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $baseClasses = 'flex h-9 w-full items-center justify-between rounded-md border bg-surface px-3 py-2 text-sm font-ui shadow-sm focus:outline-none focus:ring-2 disabled:cursor-not-allowed disabled:opacity-50 disabled:bg-surface-muted';
    $stateClasses = $error 
        ? 'border-error text-error focus:ring-error focus:border-error' 
        : 'border-border focus:border-primary focus:ring-primary-border text-text-main';
    $classes = $baseClasses . ' ' . $stateClasses;
?>

<select <?php echo e($disabled ? 'disabled' : ''); ?> <?php echo $attributes->merge(['class' => $classes]); ?>>
    <?php echo e($slot); ?>

</select>
<?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/components/ui/select.blade.php ENDPATH**/ ?>