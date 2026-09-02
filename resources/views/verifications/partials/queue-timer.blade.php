{{-- Kolom WAKTU (LIVE). Prop $idPrefix wajib beda antara render tabel dan
     render kartu mobile — keduanya ada di DOM sekaligus (yang satu cuma
     disembunyikan CSS), jadi id yang sama bikin countdown mobile tak pernah
     ter-update: querySelector berhenti di elemen desktop. --}}
@php
    $installation = $installation ?? $customer->latestInstallation;
    $idPrefix = $idPrefix ?? '';
@endphp
@if (($customer->status === 'installation_in_progress' || $customer->status === 'revision_installation') && $installation && $installation->started_at)
    <span class="font-mono text-xs font-bold" id="countdown-{{ $idPrefix }}{{ $customer->id }}" data-start="{{ $installation->started_at->toIso8601String() }}" style="color:var(--color-info)">
        Menghitung...
    </span>
@elseif ($customer->status === 'waiting_installation' || $customer->status === 'waiting_acc' || $customer->status === 'surveyed')
    @php
        $surveyCompletedAt = $customer->tasks->first()?->completed_at;
    @endphp
    @if ($surveyCompletedAt)
        <x-countdown-timer
            deadline="{{ \Carbon\Carbon::parse($surveyCompletedAt)->addDays(3)->toIso8601String() }}"
            :total-seconds="259200"
            label="Sisa Pemasangan"
            :compact="true"
        />
    @else
        <span class="text-xs text-text-muted">Belum Mulai</span>
    @endif
@else
    <span class="text-xs font-semibold text-success">Selesai</span>
@endif
