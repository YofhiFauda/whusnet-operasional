@extends('layouts.app')

@section('title', 'Detail Riwayat — ' . $fopTask->task_number)

@section('content')
<div class="px-4 py-6 max-w-5xl mx-auto space-y-5">

    {{-- ══ Breadcrumb ══ --}}
    <nav class="flex items-center gap-1.5 text-xs text-slate-500 font-ui">
        <a href="{{ route('fop-tasks.history') }}" class="hover:text-blue-600 transition-colors">Riwayat Task FOP</a>
        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
        </svg>
        <span class="font-mono">{{ $fopTask->task_number }}</span>
    </nav>

    {{-- ══ Header ══ --}}
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
        <div>
            <div class="flex items-center gap-2 flex-wrap mb-1.5">
                <span class="px-1.5 py-0.5 rounded text-[10px] font-medium border font-ui {{ $fopTask->category instanceof \App\Enums\TaskType ? $fopTask->category->badgeClasses() : 'border-slate-200 bg-white text-slate-600' }}">
                    {{ $fopTask->category instanceof \App\Enums\TaskType ? $fopTask->category->value : $fopTask->category }}
                </span>
                @php
                    $statusBadge = match ($fopTask->status->value) {
                        'selesai' => 'bg-green-50 text-green-700 border-green-200',
                        'dibatalkan' => 'bg-red-50 text-red-700 border-red-200',
                        default => 'bg-slate-50 text-slate-600 border-slate-200',
                    };
                    $statusLabel = $fopTask->task
                        ? $fopTask->task->status->displayLabel($fopTask->task->report_deferred)
                        : $fopTask->status->displayLabel();
                @endphp
                <span class="px-1.5 py-0.5 rounded text-[10px] font-medium border font-ui {{ $statusBadge }}">
                    {{ $statusLabel }}
                </span>
            </div>
            <h1 class="text-xl font-bold text-slate-900 tracking-tight font-ui">{{ $fopTask->tugas }}</h1>
            <p class="text-xs text-slate-500 mt-0.5 font-ui font-mono">{{ $fopTask->task_number }}</p>
        </div>
    </div>

    {{-- ══ Info Task ══ --}}
    <div class="bg-white border border-slate-200 rounded shadow-sm overflow-hidden">
        <div class="px-4 py-2.5 border-b border-slate-200 bg-slate-50">
            <h2 class="text-xs font-semibold text-slate-700 uppercase tracking-wider font-ui">Info Task</h2>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 p-4 text-[11px] font-ui">
            <div>
                <p class="text-slate-400 uppercase tracking-wider text-[10px] mb-0.5">Tanggal</p>
                <p class="font-medium text-slate-800 font-data">{{ $fopTask->task_date?->format('d/m/Y H:i') ?? '—' }}</p>
            </div>
            <div>
                <p class="text-slate-400 uppercase tracking-wider text-[10px] mb-0.5">Area</p>
                <p class="font-medium text-slate-800">{{ $fopTask->village?->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-slate-400 uppercase tracking-wider text-[10px] mb-0.5">POP / Cabang</p>
                <p class="font-medium text-slate-800">{{ $fopTask->pop?->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-slate-400 uppercase tracking-wider text-[10px] mb-0.5">Prioritas</p>
                <p class="font-medium text-slate-800">{{ $fopTask->priority->value }}</p>
            </div>
            <div>
                <p class="text-slate-400 uppercase tracking-wider text-[10px] mb-0.5">Teknisi</p>
                <p class="font-medium text-slate-800">{{ $fopTask->technicians->pluck('name')->implode(', ') ?: '—' }}</p>
            </div>
            <div>
                <p class="text-slate-400 uppercase tracking-wider text-[10px] mb-0.5">Team</p>
                <p class="font-medium text-slate-800">{{ $fopTask->team?->name ?? '—' }}</p>
            </div>
            <div class="col-span-2">
                <p class="text-slate-400 uppercase tracking-wider text-[10px] mb-0.5">Issue</p>
                <p class="font-medium text-red-600">{{ $fopTask->issue ?? '—' }}</p>
            </div>
            @if($fopTask->status->value === 'dibatalkan')
            <div class="col-span-2">
                <p class="text-slate-400 uppercase tracking-wider text-[10px] mb-0.5">Alasan Cancel</p>
                <p class="font-medium text-slate-800">{{ $fopTask->cancel_reason ?? '—' }}</p>
            </div>
            @endif
            @if($fopTask->notes)
            <div class="col-span-2 sm:col-span-4">
                <p class="text-slate-400 uppercase tracking-wider text-[10px] mb-0.5">Catatan</p>
                <p class="font-medium text-slate-800">{{ $fopTask->notes }}</p>
            </div>
            @endif
        </div>
    </div>

    {{-- ══ Durasi & SLA Pengerjaan ══ --}}
    <div class="bg-white border border-slate-200 rounded shadow-sm overflow-hidden">
        <div class="px-4 py-2.5 border-b border-slate-200 bg-slate-50">
            <h2 class="text-xs font-semibold text-slate-700 uppercase tracking-wider font-ui">Durasi & SLA Pengerjaan</h2>
        </div>
        @php $report = $fopTask->task?->report; @endphp
        @if($report)
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 p-4 text-[11px] font-ui">
            <div>
                <p class="text-slate-400 uppercase tracking-wider text-[10px] mb-0.5">Mulai</p>
                <p class="font-medium text-slate-800 font-data">{{ $report->started_at?->format('d/m/Y H:i') ?? '—' }}</p>
            </div>
            <div>
                <p class="text-slate-400 uppercase tracking-wider text-[10px] mb-0.5">Pending Terakhir</p>
                <p class="font-medium text-slate-800 font-data">{{ $report->pending_at?->format('d/m/Y H:i') ?? '—' }}</p>
            </div>
            <div>
                <p class="text-slate-400 uppercase tracking-wider text-[10px] mb-0.5">Resume Terakhir</p>
                <p class="font-medium text-slate-800 font-data">{{ $report->resumed_at?->format('d/m/Y H:i') ?? '—' }}</p>
            </div>
            <div>
                <p class="text-slate-400 uppercase tracking-wider text-[10px] mb-0.5">Selesai</p>
                <p class="font-medium text-slate-800 font-data">{{ $report->completed_at?->format('d/m/Y H:i') ?? '—' }}</p>
            </div>
            <div>
                <p class="text-slate-400 uppercase tracking-wider text-[10px] mb-0.5">Durasi Aktual</p>
                <p class="font-semibold text-slate-800 font-data">{{ $report->accumulatedDurationMinutes() }} menit</p>
            </div>
            <div>
                <p class="text-slate-400 uppercase tracking-wider text-[10px] mb-0.5">Target SLA</p>
                <p class="font-semibold text-slate-800 font-data">{{ $report->sla_target_minutes ?? '—' }} menit</p>
            </div>
            <div>
                <p class="text-slate-400 uppercase tracking-wider text-[10px] mb-0.5">Status SLA</p>
                @if($report->sla_status === 'on_time')
                    <p class="font-semibold text-green-700">Tepat Waktu</p>
                @elseif($report->sla_status === 'over')
                    <p class="font-semibold text-red-600">Lewat SLA ({{ $report->sla_overrun_minutes }} mnt)</p>
                @else
                    <p class="font-medium text-slate-400">—</p>
                @endif
            </div>
        </div>
        @else
        <p class="p-4 text-[11px] text-slate-400 italic font-ui">Belum ada data siklus pengerjaan (teknisi belum pernah klik Mulai).</p>
        @endif
    </div>

    {{-- ══ Laporan ══ --}}
    <div class="bg-white border border-slate-200 rounded shadow-sm overflow-hidden">
        <div class="px-4 py-2.5 border-b border-slate-200 bg-slate-50">
            <h2 class="text-xs font-semibold text-slate-700 uppercase tracking-wider font-ui">Laporan</h2>
        </div>

        @if($fopTask->category === \App\Enums\TaskType::SURVEY)
            @if($survey)
            <div class="grid grid-cols-2 gap-4 p-4 text-[11px] font-ui">
                <div class="col-span-2">
                    <p class="text-slate-400 uppercase tracking-wider text-[10px] mb-0.5">Alat Dipakai</p>
                    <p class="font-medium text-slate-800">{{ $survey->required_tools ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-slate-400 uppercase tracking-wider text-[10px] mb-0.5">Estimasi Kabel</p>
                    <p class="font-medium text-slate-800">{{ $survey->cable_estimation_meter ? $survey->cable_estimation_meter . ' meter' : '—' }}</p>
                </div>
                <div>
                    <p class="text-slate-400 uppercase tracking-wider text-[10px] mb-0.5">ODP Terdekat</p>
                    <p class="font-medium text-slate-800">{{ $survey->nearest_odp ?? '—' }}</p>
                </div>
                <div class="col-span-2">
                    <p class="text-slate-400 uppercase tracking-wider text-[10px] mb-0.5">Catatan Survey</p>
                    <p class="font-medium text-slate-800 whitespace-pre-line">{{ $survey->survey_note ?? '—' }}</p>
                </div>
                @if($survey->survey_photo || $survey->house_photo)
                <div class="col-span-2 flex gap-3 flex-wrap">
                    @if($survey->survey_photo)
                    <a href="{{ Storage::url($survey->survey_photo) }}" target="_blank" class="text-blue-600 hover:underline">Foto ODP →</a>
                    @endif
                    @if($survey->house_photo)
                    <a href="{{ Storage::url($survey->house_photo) }}" target="_blank" class="text-blue-600 hover:underline">Foto Rumah →</a>
                    @endif
                </div>
                @endif
            </div>
            @else
            <p class="p-4 text-[11px] text-slate-400 italic font-ui">Belum ada laporan survey.</p>
            @endif
        @elseif($fopTask->category === \App\Enums\TaskType::PEMASANGAN)
            @if($installation || $technicalDetail)
            <div class="grid grid-cols-2 gap-4 p-4 text-[11px] font-ui">
                <div>
                    <p class="text-slate-400 uppercase tracking-wider text-[10px] mb-0.5">ODP</p>
                    <p class="font-medium text-slate-800">{{ $technicalDetail?->odp_number ?? '—' }} / Port {{ $technicalDetail?->odp_port ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-slate-400 uppercase tracking-wider text-[10px] mb-0.5">Redaman</p>
                    <p class="font-medium text-slate-800">{{ $technicalDetail?->actual_attenuation ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-slate-400 uppercase tracking-wider text-[10px] mb-0.5">Speedtest</p>
                    <p class="font-medium text-slate-800 font-data">↓{{ $technicalDetail?->test_download ?? '—' }} / ↑{{ $technicalDetail?->test_upload ?? '—' }} Mbps</p>
                </div>
                <div>
                    <p class="text-slate-400 uppercase tracking-wider text-[10px] mb-0.5">Kualitas Sinyal</p>
                    <p class="font-medium text-slate-800">Jitter {{ $technicalDetail?->jitter_ms ?? '—' }}ms, Loss {{ $technicalDetail?->packet_loss_percent ?? '—' }}%</p>
                </div>
                <div class="col-span-2">
                    <p class="text-slate-400 uppercase tracking-wider text-[10px] mb-0.5">Catatan Pemasangan</p>
                    <p class="font-medium text-slate-800 whitespace-pre-line">{{ $installation?->installation_note ?? '—' }}</p>
                </div>
                @if($installation?->installation_photo || $installation?->signature_photo || $installation?->contract_photo)
                <div class="col-span-2 flex gap-3 flex-wrap">
                    @if($installation->installation_photo)
                    <a href="{{ Storage::url($installation->installation_photo) }}" target="_blank" class="text-blue-600 hover:underline">Foto Pemasangan →</a>
                    @endif
                    @if($installation->contract_photo)
                    <a href="{{ Storage::url($installation->contract_photo) }}" target="_blank" class="text-blue-600 hover:underline">Foto Kontrak →</a>
                    @endif
                    @if($installation->signature_photo)
                    <a href="{{ Storage::url($installation->signature_photo) }}" target="_blank" class="text-blue-600 hover:underline">Foto Tanda Tangan →</a>
                    @endif
                </div>
                @endif
            </div>
            @else
            <p class="p-4 text-[11px] text-slate-400 italic font-ui">Belum ada laporan pemasangan.</p>
            @endif
        @elseif($fopTask->category === \App\Enums\TaskType::MAINTENANCE)
            @if($maintenance)
            <div class="grid grid-cols-2 gap-4 p-4 text-[11px] font-ui">
                <div class="col-span-2">
                    <p class="text-slate-400 uppercase tracking-wider text-[10px] mb-0.5">Kendala Teknis</p>
                    <p class="font-medium text-slate-800 whitespace-pre-line">{{ $maintenance->kendala_teknis }}</p>
                </div>
                <div class="col-span-2">
                    <p class="text-slate-400 uppercase tracking-wider text-[10px] mb-0.5">Alat Dipakai</p>
                    <div class="flex flex-wrap gap-1.5 mt-1">
                        @foreach(['kabel' => 'Kabel', 'modem' => 'Modem', 'patchcord' => 'Patchcord', 'sleeve' => 'Sleeve', 'lainnya' => 'Lainnya'] as $field => $label)
                            @if($maintenance->{$field})
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-blue-50 text-blue-700 border border-blue-100">{{ $label }}: {{ $maintenance->{$field} }}</span>
                            @endif
                        @endforeach
                    </div>
                </div>
                @if($maintenance->opm_photo || $maintenance->speedtest_photo)
                <div class="col-span-2 flex gap-3 flex-wrap">
                    @if($maintenance->opm_photo)
                    <a href="{{ Storage::url($maintenance->opm_photo) }}" target="_blank" class="text-blue-600 hover:underline">Foto OPM →</a>
                    @endif
                    @if($maintenance->speedtest_photo)
                    <a href="{{ Storage::url($maintenance->speedtest_photo) }}" target="_blank" class="text-blue-600 hover:underline">Foto Speedtest →</a>
                    @endif
                </div>
                @endif
            </div>
            @else
            <p class="p-4 text-[11px] text-slate-400 italic font-ui">Belum ada laporan maintenance.</p>
            @endif
        @else
            <p class="p-4 text-[11px] text-slate-400 italic font-ui">Tipe task ini tidak punya laporan lapangan terstruktur.</p>
        @endif
    </div>

    {{-- ══ Histori Status ══ --}}
    <div class="bg-white border border-slate-200 rounded shadow-sm overflow-hidden">
        <div class="px-4 py-2.5 border-b border-slate-200 bg-slate-50">
            <h2 class="text-xs font-semibold text-slate-700 uppercase tracking-wider font-ui">Histori Status</h2>
        </div>
        @if($fopTask->statusHistories->isNotEmpty())
        <div class="divide-y divide-slate-100">
            @foreach($fopTask->statusHistories as $history)
            <div class="px-4 py-2.5 flex items-center justify-between gap-3 text-[11px] font-ui">
                <div class="flex items-center gap-2">
                    <span class="font-medium text-slate-800">{{ $history->label() }}</span>
                    @if($history->from_status)
                    <span class="text-slate-400">dari {{ $history->from_status }}</span>
                    @endif
                </div>
                <div class="flex items-center gap-3 text-slate-500 font-data">
                    <span>{{ $history->changedByUser?->name ?? 'Sistem' }}</span>
                    <span>{{ $history->changed_at->format('d/m/Y H:i') }}</span>
                </div>
            </div>
            @endforeach
        </div>
        @else
        <p class="p-4 text-[11px] text-slate-400 italic font-ui">Belum ada histori transisi status.</p>
        @endif
    </div>
</div>
@endsection
