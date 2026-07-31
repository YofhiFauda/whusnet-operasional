
<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'selectedCabang' => collect(),
    'selectedMini'   => collect(),
    'formId'         => 'filterForm',
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
    'selectedCabang' => collect(),
    'selectedMini'   => collect(),
    'formId'         => 'filterForm',
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<div
    x-data="popFilter({
        c: <?php echo \Illuminate\Support\Js::from($selectedCabang->map(fn ($x) => ['id' => (int) $x->id, 'name' => $x->name])->values())->toHtml() ?>,
        m: <?php echo \Illuminate\Support\Js::from($selectedMini->map(fn ($x) => ['id' => (int) $x->id, 'name' => $x->name, 'parent_id' => (int) ($x->parent_id ?? 0), 'parent' => optional($x->parent)->name])->values())->toHtml() ?>,
        formId: <?php echo \Illuminate\Support\Js::from($formId)->toHtml() ?>
    })"
    @click.outside="open = false"
    @keydown.escape="open = false"
    class="relative"
>
    <template x-for="c in selC" :key="'hc' + c.id"><input type="hidden" name="pop_id[]" :value="c.id"></template>
    <template x-for="m in selM" :key="'hm' + m.id"><input type="hidden" name="mini_pop_id[]" :value="m.id"></template>

    
    <button type="button" @click="toggle()"
        class="h-[38px] w-full inline-flex items-center gap-2 px-3 rounded-lg border border-border bg-surface
               text-xs text-text-main hover:border-primary/60 focus:outline-none focus:border-primary
               focus:ring-2 focus:ring-primary-border transition-colors">
        <svg class="h-3.5 w-3.5 text-text-muted flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
        </svg>
        <span class="truncate" x-text="label()"></span>
        <svg class="h-3.5 w-3.5 text-text-muted transition-transform ml-auto flex-shrink-0" :class="open ? 'rotate-180' : ''"
             fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    
    <div x-show="open" x-cloak
         x-transition:enter="transition ease-out duration-100"
         x-transition:enter-start="opacity-0 -translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         class="absolute z-40 mt-1 w-72 rounded-lg border border-border bg-surface shadow-lg overflow-hidden">

        
        <div class="flex border-b border-border text-xs font-medium">
            <button type="button" @click="tab = 'cabang'; searchC()"
                    class="flex-1 py-2.5 transition-colors"
                    :class="tab === 'cabang' ? 'text-primary border-b-2 border-primary bg-primary/5' : 'text-text-muted hover:text-text-main'">
                POP / Cabang <span x-show="selC.length" x-text="'(' + selC.length + ')'"></span>
            </button>
            <button type="button" @click="tab = 'mini'; searchM()"
                    class="flex-1 py-2.5 transition-colors"
                    :class="tab === 'mini' ? 'text-primary border-b-2 border-primary bg-primary/5' : 'text-text-muted hover:text-text-main'">
                Mini POP <span x-show="selM.length" x-text="'(' + selM.length + ')'"></span>
            </button>
        </div>

        
        <div x-show="tab === 'cabang'">
            <div class="p-2 border-b border-border">
                <div class="relative">
                    <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 0 5 11a6 6 0 0 0 12 0z"/>
                    </svg>
                    <input type="text" x-model="cq" @input.debounce.300ms="searchC()" @focus="searchC()"
                           placeholder="Cari cabang..." autocomplete="off"
                           class="w-full rounded-md border border-border bg-surface pl-7 pr-3 py-1.5 text-sm text-text-main placeholder:text-text-muted focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary-border">
                </div>
            </div>
            <ul class="max-h-56 overflow-y-auto text-sm">
                <template x-for="c in selC" :key="'sc' + c.id">
                    <li @click="toggleC(c)" class="flex items-center gap-2.5 px-3 py-2 cursor-pointer hover:bg-surface-muted transition-colors">
                        <input type="checkbox" checked class="h-4 w-4 rounded border-border text-primary pointer-events-none">
                        <span class="text-text-main" x-text="c.name"></span>
                    </li>
                </template>
                <template x-for="item in cResults" :key="'cr' + item.id">
                    <li x-show="!isCSel(item)" @click="toggleC(item)" class="flex items-center gap-2.5 px-3 py-2 cursor-pointer hover:bg-surface-muted transition-colors">
                        <input type="checkbox" class="h-4 w-4 rounded border-border text-primary pointer-events-none">
                        <span class="text-text-secondary" x-text="item.name"></span>
                    </li>
                </template>
                <li x-show="cResults.length === 0 && selC.length === 0" class="px-3 py-3 text-xs text-text-muted">Ketik untuk cari cabang…</li>
            </ul>
        </div>

        
        <div x-show="tab === 'mini'">
            <div x-show="selC.length === 0" class="px-3 py-6 text-center text-xs text-text-muted">
                Pilih <button type="button" @click="tab='cabang'" class="text-primary font-medium">Cabang</button> dulu untuk memfilter Mini POP.
            </div>

            <div x-show="selC.length > 0">
                <div class="p-2 border-b border-border">
                    <div class="relative">
                        <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 0 5 11a6 6 0 0 0 12 0z"/>
                        </svg>
                        <input type="text" x-model="mq" @input.debounce.300ms="searchM()" @focus="searchM()"
                               placeholder="Cari Mini POP..." autocomplete="off"
                               class="w-full rounded-md border border-border bg-surface pl-7 pr-3 py-1.5 text-sm text-text-main placeholder:text-text-muted focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary-border">
                    </div>
                </div>
                <ul class="max-h-56 overflow-y-auto text-sm">
                    <template x-for="m in selM" :key="'sm' + m.id">
                        <li @click="toggleM(m)" class="flex items-center gap-2.5 px-3 py-2 cursor-pointer hover:bg-surface-muted transition-colors">
                            <input type="checkbox" checked class="h-4 w-4 rounded border-border text-primary pointer-events-none flex-shrink-0">
                            <span class="min-w-0">
                                <span class="text-text-main" x-text="m.name"></span>
                                <span class="text-text-muted text-xs" x-show="m.parent" x-text="' · ' + (m.parent || '')"></span>
                            </span>
                        </li>
                    </template>
                    <template x-for="item in mResults" :key="'mr' + item.id">
                        <li x-show="!isMSel(item)" @click="toggleM(item)" class="flex items-center gap-2.5 px-3 py-2 cursor-pointer hover:bg-surface-muted transition-colors">
                            <input type="checkbox" class="h-4 w-4 rounded border-border text-primary pointer-events-none flex-shrink-0">
                            <span class="min-w-0">
                                <span class="text-text-secondary" x-text="item.name"></span>
                                <span class="text-text-muted text-xs" x-show="item.parent" x-text="' · ' + (item.parent || '')"></span>
                            </span>
                        </li>
                    </template>
                    <li x-show="mResults.length === 0 && selM.length === 0" class="px-3 py-3 text-xs text-text-muted">Ketik untuk cari Mini POP…</li>
                </ul>
            </div>
        </div>

        
        <div class="flex items-center justify-between gap-2 p-2 border-t border-border bg-surface-muted/40">
            <button type="button" @click="clearAll()" class="text-xs text-text-muted hover:text-text-main font-medium">Reset</button>
            <button type="button" @click="apply()" class="text-xs font-semibold text-white bg-primary hover:bg-primary-hover rounded-md px-3 py-1.5 transition-colors">Terapkan</button>
        </div>
    </div>
</div>

<?php if (! $__env->hasRenderedOnce('ace790bc-180f-4d10-b0c3-4a2d545bd12e')): $__env->markAsRenderedOnce('ace790bc-180f-4d10-b0c3-4a2d545bd12e'); ?>
<script>
    /* Fase 5.4b - komponen filter POP (Cabang + Mini POP). Mini cascade dari
       cabang terpilih. Semua fetch lewat endpoint ber-scope forUser. */
    document.addEventListener('alpine:init', () => {
        Alpine.data('popFilter', (cfg) => ({
            open: false,
            tab: 'cabang',
            cq: '', mq: '',
            cResults: [], mResults: [],
            selC: Array.isArray(cfg.c) ? cfg.c : [],
            selM: Array.isArray(cfg.m) ? cfg.m : [],
            formId: cfg.formId || 'filterForm',

            label() {
                const n = this.selC.length + this.selM.length;
                return n ? (n + ' POP dipilih') : 'POP / Cabang';
            },

            // Buka/tutup dropdown; saat dibuka langsung muat list tab aktif.
            toggle() {
                this.open = !this.open;
                if (this.open) { this.tab === 'cabang' ? this.searchC() : this.searchM(); }
            },

            async searchC() {
                try {
                    const r = await fetch(`/api/pop/cabang?q=${encodeURIComponent(this.cq)}`);
                    this.cResults = r.ok ? await r.json() : [];
                } catch (e) { this.cResults = []; }
            },

            async searchM() {
                if (this.selC.length === 0) { this.mResults = []; return; }
                const params = new URLSearchParams();
                if (this.mq) params.set('q', this.mq);
                this.selC.forEach((c) => params.append('pop_id[]', c.id));
                try {
                    const r = await fetch(`/api/pop/mini?${params.toString()}`);
                    this.mResults = r.ok ? await r.json() : [];
                } catch (e) { this.mResults = []; }
            },

            isCSel(i) { return this.selC.some((x) => Number(x.id) === Number(i.id)); },
            isMSel(i) { return this.selM.some((x) => Number(x.id) === Number(i.id)); },

            toggleC(i) {
                if (this.isCSel(i)) {
                    this.selC = this.selC.filter((x) => Number(x.id) !== Number(i.id));
                    // Cabang dicabut → buang Mini POP di bawahnya.
                    this.selM = this.selM.filter((m) => Number(m.parent_id) !== Number(i.id));
                } else {
                    this.selC.push({ id: Number(i.id), name: i.name });
                }
                this.searchM();
            },

            toggleM(i) {
                this.isMSel(i)
                    ? (this.selM = this.selM.filter((x) => Number(x.id) !== Number(i.id)))
                    : this.selM.push({ id: Number(i.id), name: i.name, parent_id: Number(i.parent_id), parent: i.parent });
            },

            clearAll() { this.selC = []; this.selM = []; this.mResults = []; },

            apply() {
                this.open = false;
                this.$nextTick(() => {
                    const form = document.getElementById(this.formId) || this.$root.closest('form');
                    if (form) form.submit();
                });
            },
        }));
    });
</script>
<?php endif; ?>
<?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/components/ui/pop-filter.blade.php ENDPATH**/ ?>