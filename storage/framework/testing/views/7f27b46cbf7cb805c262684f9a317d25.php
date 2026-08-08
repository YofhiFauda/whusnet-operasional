<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['id', 'label' => null, 'description' => null]));

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

foreach (array_filter((['id', 'label' => null, 'description' => null]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<div class="flex items-start gap-2">
    <div class="flex h-5 items-center">
        <input 
            type="checkbox" 
            id="<?php echo e($id); ?>"
            <?php echo e($attributes->merge(['class' => 'h-4 w-4 rounded border-border-strong text-primary focus:ring-primary bg-surface transition-colors duration-normal'])); ?>

        >
    </div>
    <?php if($label): ?>
        <div class="flex flex-col">
            <label for="<?php echo e($id); ?>" class="text-sm font-medium text-text-main cursor-pointer select-none">
                <?php echo e($label); ?>

            </label>
            <?php if($description): ?>
                <p class="text-xs text-text-muted mt-0.5"><?php echo e($description); ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
<?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/components/ui/checkbox.blade.php ENDPATH**/ ?>