@extends('layouts.app')

@section('title', 'Master Timeline SLA - Whusnet Operasional')
@section('page_title', 'Master Timeline SLA')

@section('content')
<div x-data="slaTimelineMatrix()">
    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-5 mb-6">
        <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-200">Master Timeline SLA</h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
            Batas waktu wajib mulai ditangani per jenis tiket, berbeda-beda per paket internet.
            Ini bukan durasi pengerjaan teknisi di lapangan.
        </p>
    </div>

    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 dark:bg-slate-800/50 text-slate-500 dark:text-slate-400">
                <tr>
                    <th class="text-left px-4 py-3 font-medium sticky left-0 bg-slate-50 dark:bg-slate-800/50">Paket Internet</th>
                    @foreach ($taskTypes as $type)
                        <th class="text-left px-4 py-3 font-medium whitespace-nowrap">{{ $type->label() }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                @foreach ($packages as $package)
                    <tr>
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-200 sticky left-0 bg-white dark:bg-slate-800 whitespace-nowrap">
                            {{ $package->name }}
                            <div class="text-xs text-slate-400 dark:text-slate-500">{{ $package->package_code }}</div>
                        </td>
                        @foreach ($taskTypes as $type)
                            @php
                                $setting = $package->slaSettings->firstWhere('task_type', $type);
                            @endphp
                            <td class="px-4 py-2">
                                <div class="flex items-center gap-1">
                                    <input
                                        type="number"
                                        min="1"
                                        class="w-16 rounded-md border border-slate-300 dark:border-slate-600 px-2 py-1 text-sm"
                                        value="{{ $setting?->sla_duration ?? $type->defaultHandlingSlaHours() }}"
                                        @change="save({{ $package->id }}, '{{ $type->value }}', $event.target.value, $refs.unit_{{ $package->id }}_{{ $loop->index }}.value)"
                                        x-ref="duration_{{ $package->id }}_{{ $loop->index }}"
                                    >
                                    <select
                                        class="rounded-md border border-slate-300 dark:border-slate-600 px-1 py-1 text-sm"
                                        x-ref="unit_{{ $package->id }}_{{ $loop->index }}"
                                        @change="save({{ $package->id }}, '{{ $type->value }}', $refs.duration_{{ $package->id }}_{{ $loop->index }}.value, $event.target.value)"
                                    >
                                        <option value="hour" @selected(($setting?->sla_unit ?? 'day') === 'hour')>jam</option>
                                        <option value="day" @selected(($setting?->sla_unit ?? 'day') === 'day')>hari</option>
                                    </select>
                                </div>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <p class="text-xs text-slate-500 dark:text-slate-400 mt-3" x-show="savedMessage" x-text="savedMessage"></p>
</div>

@push('scripts')
<script>
    function slaTimelineMatrix() {
        return {
            savedMessage: '',
            save(packageId, taskType, duration, unit) {
                fetch(`{{ url('/master/sla-timeline') }}/${packageId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'X-HTTP-Method-Override': 'PUT',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ task_type: taskType, sla_duration: duration, sla_unit: unit }),
                })
                    .then(res => res.json())
                    .then(() => {
                        this.savedMessage = 'Tersimpan.';
                        setTimeout(() => (this.savedMessage = ''), 2000);
                    });
            },
        };
    }
</script>
@endpush
@endsection
