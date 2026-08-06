
<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'selectedDistricts' => collect(),
    'selectedVillages'  => collect(),
    'formId'            => 'filterForm',
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
    'selectedDistricts' => collect(),
    'selectedVillages'  => collect(),
    'formId'            => 'filterForm',
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<div
    x-data="wilayahFilter({
        d: <?php echo \Illuminate\Support\Js::from($selectedDistricts->map(fn ($x) => ['id' => (int) $x->id, 'name' => $x->name])->values())->toHtml() ?>,
        v: <?php echo \Illuminate\Support\Js::from($selectedVillages->map(fn ($x) => ['id' => (int) $x->id, 'name' => $x->name, 'district_id' => (int) ($x->district_id ?? 0), 'district' => optional($x->district)->name])->values())->toHtml() ?>,
        formId: <?php echo \Illuminate\Support\Js::from($formId)->toHtml() ?>
    })"
    @click.outside="open = false"
    @keydown.escape="open = false"
    class="relative"
>
    
    <template x-for="d in selD" :key="'hd' + d.id"><input type="hidden" name="district_id[]" :value="d.id"></template>
    <template x-for="v in selV" :key="'hv' + v.id"><input type="hidden" name="village_id[]" :value="v.id"></template>

    
    <button type="button" @click="toggle()"
        class="h-[38px] w-full inline-flex items-center gap-2 px-3 rounded-lg border border-border bg-surface
               text-xs text-text-main hover:border-primary/60 focus:outline-none focus:border-primary
               focus:ring-2 focus:ring-primary-border transition-colors">
        <svg class="h-3.5 w-3.5 text-text-muted flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M17.657 16.657L13.414 20.9a2 2 0 01-2.828 0l-4.243-4.243a8 8 0 1111.314 0z"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
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
            <button type="button" @click="tab = 'kec'; searchD()"
                    class="flex-1 py-2.5 transition-colors"
                    :class="tab === 'kec' ? 'text-primary border-b-2 border-primary bg-primary/5' : 'text-text-muted hover:text-text-main'">
                Kecamatan <span x-show="selD.length" x-text="'(' + selD.length + ')'"></span>
            </button>
            <button type="button" @click="tab = 'desa'; searchV()"
                    class="flex-1 py-2.5 transition-colors"
                    :class="tab === 'desa' ? 'text-primary border-b-2 border-primary bg-primary/5' : 'text-text-muted hover:text-text-main'">
                Desa <span x-show="selV.length" x-text="'(' + selV.length + ')'"></span>
            </button>
        </div>

        
        <div x-show="tab === 'kec'">
            <div class="p-2 border-b border-border">
                <div class="relative">
                    <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 0 5 11a6 6 0 0 0 12 0z"/>
                    </svg>
                    <input type="text" x-model="dq" @input.debounce.300ms="searchD()" @focus="searchD()"
                           placeholder="Cari kecamatan..." autocomplete="off"
                           class="w-full rounded-md border border-border bg-surface pl-7 pr-3 py-1.5 text-sm text-text-main placeholder:text-text-muted focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary-border">
                </div>
            </div>
            <ul class="max-h-56 overflow-y-auto text-sm">
                <template x-for="d in selD" :key="'sd' + d.id">
                    <li @click="toggleD(d)" class="flex items-center gap-2.5 px-3 py-2 cursor-pointer hover:bg-surface-muted transition-colors">
                        <input type="checkbox" checked class="h-4 w-4 rounded border-border text-primary pointer-events-none">
                        <span class="text-text-main" x-text="d.name"></span>
                    </li>
                </template>
                <template x-for="item in dResults" :key="'dr' + item.id">
                    <li x-show="!isDSel(item)" @click="toggleD(item)" class="flex items-center gap-2.5 px-3 py-2 cursor-pointer hover:bg-surface-muted transition-colors">
                        <input type="checkbox" class="h-4 w-4 rounded border-border text-primary pointer-events-none">
                        <span class="text-text-secondary" x-text="item.name"></span>
                    </li>
                </template>
                <li x-show="dResults.length === 0 && selD.length === 0" class="px-3 py-3 text-xs text-text-muted">Ketik untuk cari kecamatan…</li>
            </ul>
        </div>

        
        <div x-show="tab === 'desa'">
            
            <div x-show="selD.length === 0" class="px-3 py-6 text-center text-xs text-text-muted">
                Pilih <button type="button" @click="tab='kec'" class="text-primary font-medium">Kecamatan</button> dulu untuk memfilter desa.
            </div>

            <div x-show="selD.length > 0">
                <div class="p-2 border-b border-border">
                    <div class="relative">
                        <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 1 0 5 11a6 6 0 0 0 12 0z"/>
                        </svg>
                        <input type="text" x-model="vq" @input.debounce.300ms="searchV()" @focus="searchV()"
                               placeholder="Cari desa..." autocomplete="off"
                               class="w-full rounded-md border border-border bg-surface pl-7 pr-3 py-1.5 text-sm text-text-main placeholder:text-text-muted focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary-border">
                    </div>
                </div>
                <ul class="max-h-56 overflow-y-auto text-sm">
                    <template x-for="v in selV" :key="'sv' + v.id">
                        <li @click="toggleV(v)" class="flex items-center gap-2.5 px-3 py-2 cursor-pointer hover:bg-surface-muted transition-colors">
                            <input type="checkbox" checked class="h-4 w-4 rounded border-border text-primary pointer-events-none flex-shrink-0">
                            <span class="min-w-0">
                                <span class="text-text-main" x-text="v.name"></span>
                                <span class="text-text-muted text-xs" x-show="v.district" x-text="' · ' + (v.district || '')"></span>
                            </span>
                        </li>
                    </template>
                    <template x-for="item in vResults" :key="'vr' + item.id">
                        <li x-show="!isVSel(item)" @click="toggleV(item)" class="flex items-center gap-2.5 px-3 py-2 cursor-pointer hover:bg-surface-muted transition-colors">
                            <input type="checkbox" class="h-4 w-4 rounded border-border text-primary pointer-events-none flex-shrink-0">
                            <span class="min-w-0">
                                <span class="text-text-secondary" x-text="item.name"></span>
                                <span class="text-text-muted text-xs" x-show="item.district" x-text="' · ' + (item.district || '')"></span>
                            </span>
                        </li>
                    </template>
                    <li x-show="vResults.length === 0 && selV.length === 0" class="px-3 py-3 text-xs text-text-muted">Ketik untuk cari desa…</li>
                </ul>
            </div>
        </div>

        
        <div class="flex items-center justify-between gap-2 p-2 border-t border-border bg-surface-muted/40">
            <button type="button" @click="clearAll()" class="text-xs text-text-muted hover:text-text-main font-medium">Reset</button>
            <button type="button" @click="apply()" class="text-xs font-semibold text-white bg-primary hover:bg-primary-hover rounded-md px-3 py-1.5 transition-colors">Terapkan</button>
        </div>
    </div>
</div>

<?php if (! $__env->hasRenderedOnce('a8cabc34-6a88-4ab2-b6cb-9b7af222db55')): $__env->markAsRenderedOnce('a8cabc34-6a88-4ab2-b6cb-9b7af222db55'); ?>
<script>
    /* Fase 5.4 - komponen filter wilayah (Kecamatan + Desa). Desa cascade dari
       kecamatan terpilih (mencegah desa senama lintas kecamatan). Registrasi
       sekali lewat alpine:init. Batch: pilih beberapa lalu "Terapkan". */
    document.addEventListener('alpine:init', () => {
        Alpine.data('wilayahFilter', (cfg) => ({
            open: false,
            tab: 'kec',
            dq: '', vq: '',
            dResults: [], vResults: [],
            selD: Array.isArray(cfg.d) ? cfg.d : [],
            selV: Array.isArray(cfg.v) ? cfg.v : [],
            formId: cfg.formId || 'filterForm',

            label() {
                const n = this.selD.length + this.selV.length;
                return n ? (n + ' wilayah dipilih') : 'Wilayah';
            },

            // Buka/tutup dropdown; saat dibuka langsung muat list tab aktif
            // (tak perlu klik input dulu).
            toggle() {
                this.open = !this.open;
                if (this.open) { this.tab === 'kec' ? this.searchD() : this.searchV(); }
            },

            async searchD() {
                try {
                    const r = await fetch(`/api/wilayah/districts?q=${encodeURIComponent(this.dq)}`);
                    this.dResults = r.ok ? await r.json() : [];
                } catch (e) { this.dResults = []; }
            },

            async searchV() {
                // Desa HANYA dari kecamatan terpilih (cascade). Tanpa kecamatan → kosong.
                if (this.selD.length === 0) { this.vResults = []; return; }
                const params = new URLSearchParams();
                if (this.vq) params.set('q', this.vq);
                this.selD.forEach((d) => params.append('district_id[]', d.id));
                try {
                    const r = await fetch(`/api/wilayah/villages?${params.toString()}`);
                    this.vResults = r.ok ? await r.json() : [];
                } catch (e) { this.vResults = []; }
            },

            isDSel(i) { return this.selD.some((x) => Number(x.id) === Number(i.id)); },
            isVSel(i) { return this.selV.some((x) => Number(x.id) === Number(i.id)); },

            toggleD(i) {
                if (this.isDSel(i)) {
                    this.selD = this.selD.filter((x) => Number(x.id) !== Number(i.id));
                    // Kecamatan dicabut → buang desa di bawahnya dari pilihan.
                    this.selV = this.selV.filter((v) => Number(v.district_id) !== Number(i.id));
                } else {
                    this.selD.push({ id: Number(i.id), name: i.name });
                }
                this.searchV();
            },

            toggleV(i) {
                this.isVSel(i)
                    ? (this.selV = this.selV.filter((x) => Number(x.id) !== Number(i.id)))
                    : this.selV.push({ id: Number(i.id), name: i.name, district_id: Number(i.district_id), district: i.district });
            },

            clearAll() { this.selD = []; this.selV = []; this.vResults = []; },

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
<?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/components/ui/wilayah-filter.blade.php ENDPATH**/ ?>