
<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'popTree'  => collect(),
    'selected' => [],
    'name'     => 'pop_ids[]',
    'id'       => 'pop-tree',
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
    'popTree'  => collect(),
    'selected' => [],
    'name'     => 'pop_ids[]',
    'id'       => 'pop-tree',
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $selectedIds = collect($selected)->map(fn($v) => (int) $v)->all();
?>

<div
    id="<?php echo e($id); ?>"
    x-data="popTreePicker(<?php echo e(json_encode($selectedIds)); ?>)"
    class="rounded-md border border-border bg-surface overflow-hidden"
>
    
    <div class="flex items-center justify-between gap-3 border-b border-border bg-surface-muted px-3 py-2">
        <div class="flex items-center gap-3">
            <span class="text-xs font-semibold text-text-muted uppercase tracking-wide">
                POP / Cabang
            </span>
            <span
                class="data-text inline-flex items-center rounded-full bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary"
                x-text="selectedCount() + ' dipilih'"
            ></span>
        </div>
        <div class="flex items-center gap-2 text-xs">
            <button type="button" @click="selectAll()"
                    class="text-primary hover:text-primary-hover font-medium transition-colors">
                Pilih Semua
            </button>
            <span class="text-text-disabled">|</span>
            <button type="button" @click="deselectAll()"
                    class="text-text-muted hover:text-text-secondary font-medium transition-colors">
                Hapus Semua
            </button>
        </div>
    </div>

    
    <div x-show="allSelected()" x-cloak
         class="mx-3 mt-2 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-800/60 dark:bg-amber-950/30 dark:text-amber-300">
        ⚠ Semua POP yang ada saat ini kecentang — POP <strong>BARU</strong> nanti TIDAK otomatis ikut kesini.
        Kalau maksudnya akses penuh permanen (termasuk cabang yang belum dibuat), pilih <strong>"Seluruh POP"</strong>
        di atas, bukan menyeleksi manual semuanya di sini.
    </div>

    
    <div class="border-b border-border px-3 py-2">
        <div class="relative">
            <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 0 5 11a6 6 0 0 0 12 0z"/>
            </svg>
            <input
                type="text"
                x-model="search"
                placeholder="Cari POP..."
                class="w-full rounded-md border border-border bg-surface pl-7 pr-3 py-1.5 text-sm text-text-main placeholder:text-text-muted focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary-border"
                autocomplete="off"
            >
        </div>
    </div>

    
    <div class="max-h-72 overflow-y-auto divide-y divide-border/50">

        <?php $__empty_1 = true; $__currentLoopData = $popTree; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $root): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <div
            x-show="isVisible(<?php echo e($root->id); ?>, <?php echo e(json_encode($root->name)); ?>, <?php echo e(json_encode($root->children->pluck('name')->merge($root->children->flatMap(fn($c) => $c->children->pluck('name')))->all())); ?>)"
            x-data="{ open: true }"
        >
            
            <div class="flex items-center gap-2 px-3 py-2.5 hover:bg-surface-muted/60 transition-colors group">

                
                <?php if($root->children->count()): ?>
                <button
                    type="button"
                    @click="open = !open"
                    class="flex-shrink-0 text-text-muted hover:text-text-main transition-colors"
                    :aria-label="open ? 'Tutup' : 'Buka'"
                >
                    <svg class="h-4 w-4 transition-transform" :class="open ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </button>
                <?php else: ?>
                <span class="flex-shrink-0 w-4"></span>
                <?php endif; ?>

                
                <label class="flex flex-1 items-start gap-2.5 cursor-pointer min-w-0">
                    <input
                        type="checkbox"
                        name="<?php echo e($name); ?>"
                        value="<?php echo e($root->id); ?>"
                        <?php if(in_array($root->id, $selectedIds)): echo 'checked'; endif; ?>
                        x-model="selected"
                        @change="onParentChange($event, <?php echo e($root->id); ?>, [<?php echo e($root->children->pluck('id')->implode(', ')); ?><?php echo e($root->children->count() ? ', ' : ''); ?><?php echo e($root->children->flatMap(fn($c) => $c->children->pluck('id'))->implode(', ')); ?>])"
                        class="mt-0.5 h-4 w-4 flex-shrink-0 rounded border-border text-primary focus:ring-2 focus:ring-primary-border transition-colors"
                    >
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold text-text-main leading-tight truncate">
                            <?php echo e($root->name); ?>

                        </span>
                        <span class="block text-xs text-text-muted">
                            <span class="data-text"><?php echo e($root->code); ?></span>
                            <?php if($root->type): ?>
                                · <?php echo e(ucfirst(str_replace('_', ' ', $root->type))); ?>

                            <?php endif; ?>
                            <?php if($root->district): ?>
                                · <?php echo e($root->district); ?>

                            <?php endif; ?>
                            <?php if($root->children->count()): ?>
                                · <span class="text-primary"><?php echo e($root->children->count()); ?> sub-POP</span>
                            <?php endif; ?>
                        </span>
                    </span>
                </label>
            </div>

            
            <?php if($root->children->count()): ?>
            <div x-show="open" x-collapse class="border-t border-border/40">
                <?php $__currentLoopData = $root->children; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $child): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div
                    x-show="isVisible(<?php echo e($child->id); ?>, <?php echo e(json_encode($child->name)); ?>, <?php echo e(json_encode($child->children->pluck('name')->all())); ?>)"
                    x-data="{ childOpen: true }"
                >
                    <div class="flex items-center gap-2 pl-8 pr-3 py-2 hover:bg-surface-muted/40 transition-colors">

                        <?php if($child->children->count()): ?>
                        <button
                            type="button"
                            @click="childOpen = !childOpen"
                            class="flex-shrink-0 text-text-muted hover:text-text-main transition-colors"
                        >
                            <svg class="h-3.5 w-3.5 transition-transform" :class="childOpen ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>
                        <?php else: ?>
                        <span class="flex-shrink-0 w-3.5"></span>
                        <?php endif; ?>

                        <label class="flex flex-1 items-start gap-2.5 cursor-pointer min-w-0">
                            <input
                                type="checkbox"
                                name="<?php echo e($name); ?>"
                                value="<?php echo e($child->id); ?>"
                                <?php if(in_array($child->id, $selectedIds)): echo 'checked'; endif; ?>
                                x-model="selected"
                                @change="onChildChange($event, <?php echo e($root->id); ?>, [<?php echo e($root->children->pluck('id')->implode(', ')); ?>], <?php echo e($child->id); ?>, [<?php echo e($child->children->pluck('id')->implode(', ')); ?>])"
                                class="mt-0.5 h-4 w-4 flex-shrink-0 rounded border-border text-primary focus:ring-2 focus:ring-primary-border transition-colors"
                            >
                            <span class="min-w-0">
                                <span class="block text-sm font-medium text-text-secondary leading-tight truncate">
                                    <?php echo e($child->name); ?>

                                </span>
                                <span class="block text-xs text-text-muted">
                                    <span class="data-text"><?php echo e($child->code); ?></span>
                                    <?php if($child->district): ?>
                                        · <?php echo e($child->district); ?>

                                    <?php endif; ?>
                                    <?php if($child->children->count()): ?>
                                        · <span class="text-primary"><?php echo e($child->children->count()); ?> mini-POP</span>
                                    <?php endif; ?>
                                </span>
                            </span>
                        </label>
                    </div>

                    
                    <?php if($child->children->count()): ?>
                    <div x-show="childOpen" x-collapse>
                        <?php $__currentLoopData = $child->children; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $mini): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div
                            x-show="isVisible(<?php echo e($mini->id); ?>, <?php echo e(json_encode($mini->name)); ?>, [])"
                            class="flex items-center gap-2 pl-16 pr-3 py-2 hover:bg-surface-muted/30 transition-colors"
                        >
                            <span class="flex-shrink-0 w-3.5"></span>
                            <label class="flex flex-1 items-start gap-2.5 cursor-pointer min-w-0">
                                <input
                                    type="checkbox"
                                    name="<?php echo e($name); ?>"
                                    value="<?php echo e($mini->id); ?>"
                                    <?php if(in_array($mini->id, $selectedIds)): echo 'checked'; endif; ?>
                                    x-model="selected"
                                    @change="onGrandchildChange($event, <?php echo e($root->id); ?>, [<?php echo e($root->children->pluck('id')->implode(', ')); ?>], <?php echo e($child->id); ?>, [<?php echo e($child->children->pluck('id')->implode(', ')); ?>])"
                                    class="mt-0.5 h-4 w-4 flex-shrink-0 rounded border-border text-primary focus:ring-2 focus:ring-primary-border transition-colors"
                                >
                                <span class="min-w-0">
                                    <span class="block text-xs font-medium text-text-muted leading-tight truncate">
                                        <?php echo e($mini->name); ?>

                                    </span>
                                    <span class="data-text text-xs text-text-disabled"><?php echo e($mini->code); ?></span>
                                </span>
                            </label>
                        </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
            <?php endif; ?>
        </div>

        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <div class="px-4 py-10 text-center text-sm text-text-muted">
            <svg class="mx-auto mb-2 h-8 w-8 text-text-disabled" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
            </svg>
            Belum ada data POP/Cabang.
        </div>
        <?php endif; ?>

    </div>

    
    <div
        x-show="search.length > 0 && visibleCount() === 0"
        class="px-4 py-6 text-center text-sm text-text-muted border-t border-border"
    >
        Tidak ada POP yang cocok dengan "<span class="font-medium text-text-secondary" x-text="search"></span>".
    </div>

</div>

<script>
function popTreePicker(initialSelected) {
    return {
        selected: initialSelected.map(String),
        search: '',
        totalCount: 0,

        init() {
            // Total checkbox di tree ini (semua level — root/child/grandchild)
            // — dihitung sekali dari DOM yang udah di-render Blade, dipakai
            // `allSelected()` buat deteksi "user nyentang literally semuanya".
            this.totalCount = this.$el.querySelectorAll('input[type="checkbox"]').length;
        },

        selectedCount() {
            return this.selected.length;
        },

        // Lihat komentar peringatan di markup atas — true kalau SEMUA
        // checkbox (semua level) kecentang, bukan cuma root/Cabang doang.
        allSelected() {
            return this.totalCount > 0 && this.selected.length === this.totalCount;
        },

        // Apakah POP node terlihat berdasarkan search
        isVisible(id, name, childNames) {
            if (!this.search.trim()) return true;
            const q = this.search.toLowerCase();
            if (name.toLowerCase().includes(q)) return true;
            return (childNames || []).some(n => n.toLowerCase().includes(q));
        },

        visibleCount() {
            // Hitung root node yang visible
            let count = 0;
            document.querySelectorAll('[x-data*="popTreePicker"] > div.divide-y > div').forEach(el => {
                if (el.style.display !== 'none') count++;
            });
            return count;
        },

        // Pilih parent → centang semua child dan grandchild
        onParentChange(event, parentId, allDescendantIds) {
            const checked = event.target.checked;
            allDescendantIds.forEach(id => {
                const strId = String(id);
                if (checked && !this.selected.includes(strId)) {
                    this.selected.push(strId);
                } else if (!checked) {
                    this.selected = this.selected.filter(s => s !== strId);
                }
            });
        },

        // Pilih child → centang semua grandchild-nya, dan update indeterminate parent
        onChildChange(event, parentId, siblingIds, childId, grandchildIds) {
            const checked = event.target.checked;
            grandchildIds.forEach(id => {
                const strId = String(id);
                if (checked && !this.selected.includes(strId)) {
                    this.selected.push(strId);
                } else if (!checked) {
                    this.selected = this.selected.filter(s => s !== strId);
                }
            });
            this.syncParentState(parentId, siblingIds);
        },

        // Pilih grandchild → update indeterminate parent dan child
        onGrandchildChange(event, parentId, parentSiblingIds, childId, childSiblingIds) {
            this.syncParentState(childId, childSiblingIds);
            this.syncParentState(parentId, parentSiblingIds);
        },

        // Update state checkbox parent berdasarkan anak yang diceklis
        syncParentState(parentId, childIds) {
            const parentEl = document.querySelector(`input[name][value="${parentId}"]`);
            if (!parentEl) return;

            if (childIds.length === 0) return;

            const checkedCount = childIds.filter(id => this.selected.includes(String(id))).length;
            const strParent = String(parentId);

            if (checkedCount === childIds.length) {
                // Semua anak diceklis → parent checked
                if (!this.selected.includes(strParent)) this.selected.push(strParent);
                parentEl.indeterminate = false;
            } else if (checkedCount > 0) {
                // Sebagian anak diceklis → parent indeterminate
                this.selected = this.selected.filter(s => s !== strParent);
                parentEl.indeterminate = true;
            } else {
                // Tidak ada anak → parent unchecked
                this.selected = this.selected.filter(s => s !== strParent);
                parentEl.indeterminate = false;
            }
        },

        selectAll() {
            const checkboxes = document.querySelectorAll('#<?php echo e($id); ?> input[type="checkbox"]');
            this.selected = [];
            checkboxes.forEach(cb => {
                this.selected.push(cb.value);
                cb.indeterminate = false;
            });
        },

        deselectAll() {
            this.selected = [];
            document.querySelectorAll('#<?php echo e($id); ?> input[type="checkbox"]').forEach(cb => {
                cb.indeterminate = false;
            });
        },
    };
}
</script>
<?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/components/ui/pop-tree-picker.blade.php ENDPATH**/ ?>