<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'padding' => 'default', // compact, default, comfortable
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
    'padding' => 'default', // compact, default, comfortable
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $paddingClasses = match($padding) {
        'compact' => 'p-2 sm:p-3',
        'comfortable' => 'p-4 sm:p-5',
        default => 'p-3 sm:p-4',
    };
?>

<div <?php echo e($attributes->merge(['class' => "bg-surface border border-border rounded-md shadow-none {$paddingClasses}"])); ?>>
    <?php if(isset($header)): ?>
        <div class="mb-4">
            <?php echo e($header); ?>

        </div>
    <?php endif; ?>
    
    <?php echo e($slot); ?>

    
    <?php if(isset($footer)): ?>
        <div class="mt-4 pt-4 border-t border-border">
            <?php echo e($footer); ?>

        </div>
    <?php endif; ?>
</div>
<?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/components/ui/card.blade.php ENDPATH**/ ?>