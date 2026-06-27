{{--
    Komponen: Countdown Timer Hitung Mundur
    =======================================
    Props:
      $deadline     (string) — ISO 8601 datetime string — kapan countdown habis
      $totalSeconds (int)    — total batas waktu dalam detik (untuk hitung % threshold warna)
      $label        (string) — label opsional, misal "Sisa Survey", "Sisa Verifikasi"
      $compact      (bool)   — mode kompak (hanya angka + warna, tanpa label teks)

    Logika countdown:
      remainSeconds = floor((deadline - Date.now()) / 1000)
      pct           = remainSeconds / totalSeconds * 100

    Threshold warna:
      🟢 Hijau         : pct > 50%
      🟡 Kuning        : pct 25–50%
      🔴 Merah berkedip: pct < 25%
      🔴 TERLAMBAT     : remainSeconds < 0 (tampilkan minus)

    Catatan:
      - Menggunakan Date.now() vs deadline (bukan counter integer) agar akurat saat tab di-background.
      - setInterval 1 detik untuk update tampilan.
      - Saat TERLAMBAT, countdown terus berjalan ke negatif (−HH:MM:SS).

    Contoh penggunaan:
      [Survey 1x24 jam]
      <x-countdown-timer
          deadline="[[ $item['deadline_iso'] ]]"
          :total-seconds="86400"
          label="Sisa Survey"
      />

      [Verifikasi 3x24 jam]
      <x-countdown-timer
          deadline="[[ $item['verif_deadline_iso'] ]]"
          :total-seconds="259200"
          label="Sisa Verifikasi"
      />

      [SLA Eksekusi task]
      <x-countdown-timer
          deadline="[[ $slaDeadlineIso ]]"
          :total-seconds="$task['sla_minutes'] * 60"
          label="Sisa SLA"
          :compact="true"
      />
--}}

@props([
    'deadline',
    'totalSeconds' => 86400,
    'label'        => null,
    'compact'      => false,
])

<div
    x-data="countdownTimer(
        '{{ $deadline }}',
        {{ (int) $totalSeconds }}
    )"
    x-init="start()"
    class="inline-flex items-center gap-1.5"
>
    {{-- TERLAMBAT state --}}
    <template x-if="isLate">
        <span
            class="inline-flex items-center gap-1 font-semibold rounded-full border animate-pulse"
            :class="{{ $compact ? '\'text-[10px] px-1.5 py-0.5\'' : '\'text-xs px-2 py-0.5\'' }}"
            style="background:var(--color-error-bg); color:var(--color-error); border-color:var(--color-error-border)"
        >
            @if(!$compact)
            <svg class="h-3 w-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
            </svg>
            @endif
            <span x-text="'TERLAMBAT ' + formatTime(remainSeconds, true)"></span>
        </span>
    </template>

    {{-- Normal countdown state --}}
    <template x-if="!isLate">
        <span
            class="inline-flex items-center gap-1 font-mono font-semibold rounded-full border transition-colors duration-500"
            :class="[
                {{ $compact ? '\'text-[10px] px-1.5 py-0.5\'' : '\'text-xs px-2 py-0.5\'' }},
                colorClass
            ]"
        >
            {{-- Ikon jam kecil --}}
            @if(!$compact)
            <svg class="h-3 w-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            @endif
            <span x-text="formatTime(remainSeconds, false)"></span>
        </span>
    </template>

    {{-- Label teks opsional --}}
    @if($label && !$compact)
    <span class="text-[11px] text-text-muted">{{ $label }}</span>
    @endif
</div>

@once
@push('scripts')
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
@endpush
@endonce

{{--
    Inline CSS untuk state warna countdown — menggunakan CSS variables project.
    Diletakkan di @once agar tidak duplikat jika komponen dipakai berkali-kali.
--}}
@once
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
@endonce
