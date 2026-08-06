

<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'deadline',
    'totalSeconds' => 86400,
    'label'        => null,
    'compact'      => false,
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
    'deadline',
    'totalSeconds' => 86400,
    'label'        => null,
    'compact'      => false,
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<div
    x-data="countdownTimer(
        '<?php echo e($deadline); ?>',
        <?php echo e((int) $totalSeconds); ?>

    )"
    x-init="start()"
    class="inline-flex items-center gap-1.5"
>
    
    <template x-if="isLate">
        <span
            class="inline-flex items-center gap-1 font-semibold rounded-full border animate-pulse"
            :class="<?php echo e($compact ? '\'text-[10px] px-1.5 py-0.5\'' : '\'text-xs px-2 py-0.5\''); ?>"
            style="background:var(--color-error-bg); color:var(--color-error); border-color:var(--color-error-border)"
        >
            <?php if(!$compact): ?>
            <svg class="h-3 w-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
            </svg>
            <?php endif; ?>
            <span x-text="'TERLAMBAT ' + formatTime(remainSeconds, true)"></span>
        </span>
    </template>

    
    <template x-if="!isLate">
        <span
            class="inline-flex items-center gap-1 font-mono font-semibold rounded-full border transition-colors duration-500"
            :class="[
                <?php echo e($compact ? '\'text-[10px] px-1.5 py-0.5\'' : '\'text-xs px-2 py-0.5\''); ?>,
                colorClass
            ]"
        >
            
            <?php if(!$compact): ?>
            <svg class="h-3 w-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <?php endif; ?>
            <span x-text="formatTime(remainSeconds, false)"></span>
        </span>
    </template>

    
    <?php if($label && !$compact): ?>
    <span class="text-[11px] text-text-muted"><?php echo e($label); ?></span>
    <?php endif; ?>
</div>

<?php if (! $__env->hasRenderedOnce('3e5e147a-23b4-4d7d-8b1a-a7ee4b9f90be')): $__env->markAsRenderedOnce('3e5e147a-23b4-4d7d-8b1a-a7ee4b9f90be'); ?>
<?php $__env->startPush('scripts'); ?>
<script>
function countdownTimer(deadlineIso, totalSeconds) {
    return {
        deadline:     new Date(deadlineIso).getTime(),
        totalSeconds: totalSeconds,
        remainSeconds: 0,
        isLate:        false,
        colorClass:    '',
        _timer:        null,

        start() {
            this.tick();
            this._timer = setInterval(() => this.tick(), 1000);
        },

        tick() {
            // Gunakan Date.now() vs deadline — akurat meski tab di-background
            const nowMs       = Date.now();
            const remainMs    = this.deadline - nowMs;
            this.remainSeconds = Math.floor(remainMs / 1000);
            this.isLate        = this.remainSeconds < 0;

            // Hitung persentase sisa waktu untuk threshold warna
            const pct = this.remainSeconds / this.totalSeconds * 100;

            if (this.isLate) {
                this.colorClass = ''; // Pakai template x-if isLate
            } else if (pct > 50) {
                this.colorClass = 'countdown-green';
            } else if (pct > 25) {
                this.colorClass = 'countdown-yellow';
            } else {
                this.colorClass = 'countdown-red';
            }
        },

        /**
         * Format detik menjadi HH:MM:SS
         * @param {number}  seconds  — bisa negatif saat TERLAMBAT
         * @param {boolean} negative — jika true, tampilkan tanda minus
         */
        formatTime(seconds, negative) {
            const abs  = Math.abs(seconds);
            const h    = Math.floor(abs / 3600);
            const m    = Math.floor((abs % 3600) / 60);
            const s    = abs % 60;
            const fmt  = [
                String(h).padStart(2, '0'),
                String(m).padStart(2, '0'),
                String(s).padStart(2, '0'),
            ].join(':');
            return negative ? '−' + fmt : fmt;
        },
    };
}
</script>
<?php $__env->stopPush(); ?>
<?php endif; ?>


<?php if (! $__env->hasRenderedOnce('88e6e88e-ba6d-4c5a-9ccf-c6181ded316f')): $__env->markAsRenderedOnce('88e6e88e-ba6d-4c5a-9ccf-c6181ded316f'); ?>
<style>
.countdown-green {
    background: var(--color-success-bg);
    color:      var(--color-success);
    border-color: var(--color-success-border);
}
.countdown-yellow {
    background: var(--color-warning-bg);
    color:      var(--color-warning);
    border-color: var(--color-warning-border);
}
.countdown-red {
    background:   var(--color-error-bg);
    color:        var(--color-error);
    border-color: var(--color-error-border);
    animation: countdown-blink 1s step-start infinite;
}
@keyframes countdown-blink {
    0%, 100% { opacity: 1; }
    50%       { opacity: 0.55; }
}
</style>
<?php endif; ?>
<?php /**PATH /home/yopi/whusnet/whusnet-operasional/resources/views/components/countdown-timer.blade.php ENDPATH**/ ?>