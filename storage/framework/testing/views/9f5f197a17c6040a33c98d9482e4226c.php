<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'disabled' => false,
    'error' => false,
    'rows' => 3,
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
    'rows' => 3,
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $baseClasses = 'flex min-h-[60px] w-full rounded-md border bg-surface px-3 py-2 text-sm font-ui shadow-sm placeholder:text-text-muted focus-visible:outline-none focus-visible:ring-2 disabled:cursor-not-allowed disabled:opacity-50 disabled:bg-surface-muted';
    $stateClasses = $error 
        ? 'border-error text-error focus-visible:ring-error focus-visible:border-error' 
        : 'border-border focus-visible:border-primary focus-visible:ring-primary-border text-text-main';
    $classes = $baseClasses . ' ' . $stateClasses;
?>

<textarea rows="<?php echo e($rows); ?>" <?php echo e($disabled ? 'disabled' : ''); ?> <?php echo $attributes->merge(['class' => $classes]); ?>><?php echo e($slot); ?></textarea>
<?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/components/ui/textarea.blade.php ENDPATH**/ ?>