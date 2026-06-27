@extends('layouts.app')

@section('title', 'Proses Verifikasi & Pemasangan - Whusnet Operasional')
@section('page_title', 'Antrean Verifikasi & Pemasangan')

@section('content')
<div x-data="processToTimHandler()">
<!-- Top Action Bar -->
<div class="flex justify-between items-center mb-6">
    <h3 class="text-text-main text-sm font-semibold uppercase tracking-wider">Antrean Verifikasi Lapangan</h3>
</div>

<!-- Filter & Search Panel -->
<div class="bg-surface border border-border rounded-lg p-6 mb-6">
    <form action="{{ route('verifications.queue') }}" method="GET" class="flex flex-col sm:flex-row gap-4 items-end">
        <!-- Search -->
        <div class="flex-1">
            <label for="search" class="block text-xs font-semibold text-text-muted mb-2">CARI PELANGGAN</label>
            <input type="text" name="search" id="search" value="{{ request('search') }}" placeholder="Cari nama, No. HP, atau ID Lama..." class="w-full font-sans text-sm px-3 py-2 border border-border rounded-md bg-surface text-text-main focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/25">
        </div>

        <!-- Action Buttons -->
        <div class="flex gap-2">
            <button type="submit" class="bg-primary hover:bg-primary/90 text-white text-sm font-medium py-2 px-6 rounded-md transition-colors cursor-pointer focus:outline-none focus:ring-2 focus:ring-primary/25">
                Cari
            </button>
            <a href="{{ route('verifications.queue') }}" class="bg-surface-muted hover:bg-surface-muted text-text-main text-sm font-medium py-2 px-4 rounded-md transition-colors cursor-pointer text-center focus:outline-none">
                Reset
            </a>
        </div>
    </form>
</div>

<!-- Table Content -->
<div class="bg-surface border border-border rounded-lg overflow-hidden">
    <div class="border-b border-border bg-info/5 px-6 py-3 flex items-center justify-between">
        <span class="text-sm font-bold text-info uppercase tracking-wider">Daftar Antrean</span>
    </div>

    <!-- Table Container -->
    <div class="overflow-x-auto">
        <table class="w-full border-collapse text-left text-sm text-text-main">
            <thead>
                <tr class="bg-surface-muted/50 border-b border-border text-text-muted font-semibold text-xs">
                    <th class="px-6 py-3.5 w-12 text-center">NO</th>
                    <th class="px-6 py-3.5">ID</th>
                    <th class="px-6 py-3.5">NAMA</th>
                    <th class="px-6 py-3.5">HP</th>
                    <th class="px-6 py-3.5">DESA</th>
                    <th class="px-6 py-3.5 text-center">STATUS</th>
                    <th class="px-6 py-3.5">INSERTED AT</th>
                    <th class="px-6 py-3.5">WAKTU (LIVE)</th>
                    <th class="px-6 py-3.5 text-right">ACTION</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($customers as $customer)
                    @php
                    $installation = $customer->latestInstallation;
                    @endphp
                <tr class="hover:bg-surface-muted/45 transition-colors">
                    <td class="px-6 py-3.5 text-center text-text-muted font-mono">{{ $loop->iteration }}</td>
                    <td class="px-6 py-3.5 whitespace-nowrap font-mono">{{ $customer->display_id }}</td>
                    <td class="px-6 py-3.5 font-medium text-text-main">{{ $customer->full_name }}</td>
                    <td class="px-6 py-3.5 font-mono">{{ $customer->primary_phone }}</td>
                    <td class="px-6 py-3.5 font-medium">{{ $customer->village->name ?? '-' }}</td>
                    <td class="px-6 py-3.5 text-center">
                        @php
                            $statusLabel = match($customer->status) {
                                'waiting_acc', 'surveyed' => 'MENUNGGU ACC',
                                'waiting_installation' => 'MENUNGGU PEMASANGAN',
                                'installation_in_progress' => 'MULAI PASANG',
                                'revision_installation' => 'REVISI PEMASANGAN',
                                'installed', 'verification_admin' => 'VERIFIKASI ADMIN',
                                default => $customer->status,
                            };

                            $statusStyle = match($customer->status) {
                                'waiting_acc', 'surveyed' => 'background:var(--color-warning-bg); color:var(--color-warning); border-color:var(--color-warning-border);',
                                'waiting_installation' => 'background:var(--color-surface-muted); color:var(--color-text-main); border-color:var(--color-border);',
                                'installation_in_progress' => 'background:var(--color-info-bg); color:var(--color-info); border-color:var(--color-info-border);',
                                'revision_installation' => 'background:var(--color-error-bg); color:var(--color-error); border-color:var(--color-error-border);',
                                'installed', 'verification_admin' => 'background:var(--color-success-bg); color:var(--color-success); border-color:var(--color-success-border);',
                                default => 'background:var(--color-surface-muted); color:var(--color-text-main); border-color:var(--color-border);',
                            };

                            $showPulse = in_array($customer->status, ['installation_in_progress', 'revision_installation']);
                        @endphp
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-[11px] font-bold tracking-wide uppercase border" style="{{ $statusStyle }}">
                            @if($showPulse)
                                <span class="w-1.5 h-1.5 rounded-full animate-pulse" style="background:currentColor"></span>
                            @endif
                            {{ $statusLabel }}
                        </span>
                    </td>
                    <td class="px-6 py-3.5 font-mono text-xs">{{ $customer->created_at->format('Y-m-d H:i:s') }}</td>
                    <td class="px-6 py-3.5 font-mono text-xs">
                        @if(($customer->status === 'installation_in_progress' || $customer->status === 'revision_installation') && $installation && $installation->started_at)
                            <div class="font-bold" id="countdown-{{ $customer->id }}" data-start="{{ $installation->started_at->toIso8601String() }}" style="color:var(--color-info)">
                                Menghitung...
                            </div>
                        @elseif($customer->status === 'waiting_installation' || $customer->status === 'waiting_acc' || $customer->status === 'surveyed')
                            @php
                                $surveyCompletedAt = $customer->tasks->first()?->completed_at;
                            @endphp
                            @if($surveyCompletedAt)
                                <x-countdown-timer 
                                    deadline="{{ \Carbon\Carbon::parse($surveyCompletedAt)->addDays(3)->toIso8601String() }}" 
                                    :total-seconds="259200" 
                                    label="Sisa Pemasangan" 
                                    :compact="true"
                                />
                            @else
                                <span class="text-text-muted">Belum Mulai</span>
                            @endif
                        @else
                            <span class="text-success font-bold">Selesai</span>
                        @endif
                    </td>
                    <td class="px-6 py-3.5 text-right whitespace-nowrap">
                        <div class="flex items-center justify-end gap-2">
                            <button type="button" class="text-text-muted hover:text-primary transition-colors p-1" title="Generate/Lihat QR" onclick="window.Toast.info('Mockup', 'Generate/Lihat QR')">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm14 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                                </svg>
                            </button>
                            <a href="{{ route('customers.show', $customer) }}" class="text-text-muted hover:text-primary transition-colors p-1" title="Detail">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                            </a>
                            <form action="{{ route('customers.destroy', $customer) }}" method="POST" class="inline-block m-0 p-0" onsubmit="event.preventDefault(); window.confirmDelete('Apakah Anda yakin ingin menghapus pelanggan ini?', this);">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-text-muted hover:text-error transition-colors p-1" title="Delete">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                </button>
                            </form>
                            
                            @if($customer->status === 'waiting_acc' || $customer->status === 'surveyed')
                                @php
                                    $latestSurvey = $customer->latestSurvey;
                                    $surveyNote = $latestSurvey?->survey_note ?? '';
                                    $mediaType = '—';
                                    if (preg_match('/Media:\s*(Fiber|Wireless|UTP)/i', $surveyNote, $matches)) {
                                        $mediaType = $matches[1];
                                    }
                                    $surveyStatus = $latestSurvey?->survey_status;
                                    $resultLabel = match ($surveyStatus) {
                                        'completed' => 'Layak',
                                        'failed'    => 'Tidak Layak',
                                        'pending'   => 'Kunjungan Ulang',
                                        default     => '—'
                                    };
                                    $surveyData = [
                                        'id' => $customer->id,
                                        'survey_result' => $resultLabel,
                                        'survey_distance' => $latestSurvey?->cable_estimation_meter ? $latestSurvey->cable_estimation_meter . 'm' : '—',
                                        'survey_media' => $mediaType,
                                        'survey_odp' => $latestSurvey?->nearest_odp ?? '—',
                                        'survey_notes' => $surveyNote,
                                    ];
                                @endphp
                                <button type="button" @click="openProcessToTim({{ json_encode($surveyData) }})" class="bg-primary hover:bg-primary/90 text-white text-[11px] font-bold uppercase tracking-wider py-1.5 px-3 rounded shadow-sm transition-colors cursor-pointer">
                                    Proses ke Tim
                                </button>
                                <button type="button" onclick="openRejectModal('{{ $customer->id }}')" class="bg-error/10 hover:bg-error/20 text-error text-[11px] font-bold uppercase tracking-wider py-1.5 px-3 rounded shadow-sm transition-colors cursor-pointer">
                                    Batalkan / Gagal
                                </button>
                            @elseif($customer->status === 'waiting_installation')
                                <form action="{{ route('customers.installation.start', $customer) }}" method="POST" class="inline-block m-0 p-0" onsubmit="event.preventDefault(); window.confirmAction('Mulai proses pemasangan untuk pelanggan ini?', this);">
                                    @csrf
                                    <button type="submit" class="bg-primary hover:bg-primary/90 text-white text-[11px] font-bold uppercase tracking-wider py-1.5 px-3 rounded shadow-sm transition-colors cursor-pointer">
                                        Start Proses
                                    </button>
                                </form>
                            @elseif($customer->status === 'installation_in_progress')
                                <a href="{{ route('customers.installation.report', $customer) }}" class="bg-success hover:bg-success/90 text-white text-[11px] font-bold uppercase tracking-wider py-1.5 px-3 rounded shadow-sm transition-colors cursor-pointer text-center">
                                    Lapor Pemasangan
                                </a>
                            @elseif($customer->status === 'revision_installation')
                                <a href="{{ route('customers.installation.report', $customer) }}" class="bg-error hover:bg-error/90 text-white text-[11px] font-bold uppercase tracking-wider py-1.5 px-3 rounded shadow-sm transition-colors cursor-pointer text-center">
                                    Revisi
                                </a>
                            @elseif($customer->status === 'installed' || $customer->status === 'verification_admin')
                                <a href="{{ route('customers.verification.admin', $customer) }}" class="bg-success hover:bg-success/90 text-white text-[11px] font-bold uppercase tracking-wider py-1.5 px-3 rounded shadow-sm transition-colors cursor-pointer text-center inline-flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    Verifikasi
                                </a>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-6 py-8 text-center text-text-muted">
                        <div class="flex flex-col items-center justify-center gap-2">
                            <svg class="w-8 h-8 text-border" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" /></svg>
                            <span class="text-sm font-medium">Tidak ada antrean saat ini.</span>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
    @if($customers->hasPages())
        <div class="border-t border-border px-6 py-4 bg-surface-muted/50">
            {{ $customers->links() }}
        </div>
    @endif
</div>



{{-- Modal Final Verify telah dipindahkan ke halaman verifications/admin.blade.php --}}

<!-- Modal Reject -->
<div id="rejectModal" class="fixed inset-0 z-[100] flex items-center justify-center hidden bg-text-main/50 backdrop-blur-sm transition-opacity opacity-0 duration-300">
    <div class="bg-surface rounded-xl shadow-2xl w-full max-w-md overflow-hidden transform scale-95 transition-transform duration-300">
        <div class="flex justify-between items-center px-6 py-4 border-b border-border bg-error/5">
            <h3 class="text-lg font-bold text-error">Batalkan / Gagal Pelanggan</h3>
            <button type="button" onclick="closeRejectModal()" class="text-text-muted hover:text-text-main transition-colors focus:outline-none rounded-md hover:bg-surface-muted p-1 cursor-pointer">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
        </div>
        
        <form id="rejectForm" method="POST" action="">
            @csrf
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-text-muted mb-2">ALASAN PENOLAKAN <span class="text-error">*</span></label>
                    <textarea name="reason" rows="3" class="w-full text-sm px-3 py-2 border border-border rounded-md focus:outline-none focus:border-error focus:ring-1 focus:ring-error bg-surface" required placeholder="Contoh: Lokasi tidak terjangkau jaringan (ODP Penuh)..."></textarea>
                </div>
            </div>
            
            <div class="px-6 py-4 border-t border-border bg-surface-muted flex justify-end gap-3">
                <button type="button" onclick="closeRejectModal()" class="px-5 py-2 text-sm font-medium text-text-muted bg-surface border border-border rounded-md hover:bg-surface-muted transition-colors cursor-pointer">Tutup</button>
                <button type="submit" class="px-5 py-2 text-sm font-medium text-white bg-error rounded-md hover:bg-error/90 transition-colors shadow-sm cursor-pointer">Batalkan / Gagal</button>
            </div>
        </form>
    </div>
</div>

    {{-- Slide-Over Drawer --}}
    <x-ui.drawer name="process-to-tim" title="Proses ke TIM" maxWidth="md">
        <form x-ref="form" :action="'/verifications/' + customer.id + '/process-to-team'" method="POST" @submit.prevent="submitForm($el)">
            @csrf
            
            {{-- Summary of survey report --}}
            <div class="mb-5 bg-surface-muted border border-border rounded-lg p-4">
                <h3 class="text-xs font-semibold text-text-main uppercase tracking-widest mb-3">Ringkasan Laporan Survey</h3>
                <div class="grid grid-cols-2 gap-3 text-xs">
                    <div>
                        <p class="text-text-muted">Hasil Survey</p>
                        <p class="font-semibold text-text-main" x-text="customer.survey_result"></p>
                    </div>
                    <div>
                        <p class="text-text-muted">Rekomendasi Media</p>
                        <p class="font-semibold text-text-main" x-text="customer.survey_media"></p>
                    </div>
                    <div>
                        <p class="text-text-muted">Estimasi Jarak</p>
                        <p class="font-semibold text-text-main" x-text="customer.survey_distance"></p>
                    </div>
                    <div>
                        <p class="text-text-muted">ODP Terdekat</p>
                        <p class="font-semibold text-text-main" x-text="customer.survey_odp"></p>
                    </div>
                </div>
                <div class="mt-3 text-xs border-t border-border pt-2.5">
                    <p class="text-text-muted">Catatan Survey</p>
                    <p class="text-text-main mt-1 whitespace-pre-line" x-text="customer.survey_notes || '—'"></p>
                </div>
            </div>

            {{-- Assign technician --}}
            <div class="mb-4">
                <label for="technician_id" class="block text-xs font-semibold text-text-muted uppercase tracking-widest mb-2">
                    Assign Teknisi Pemasangan <span class="text-error">*</span>
                </label>
                <select name="technician_id" id="technician_id" x-model="technicianId" @change="checkConflicts()" required
                        class="w-full border border-border rounded-md px-3 py-2 text-sm text-text-main bg-surface focus:outline-none focus:ring-2 focus:ring-primary/25 focus:border-transparent transition">
                    <option value="">-- Pilih Teknisi --</option>
                    @foreach($teknisiList as $tek)
                        <option value="{{ $tek['id'] }}">{{ $tek['name'] }} ({{ $tek['status'] }})</option>
                    @endforeach
                </select>
            </div>

            {{-- Scheduled date & time --}}
            <div class="mb-4">
                <label for="scheduled_at" class="block text-xs font-semibold text-text-muted uppercase tracking-widest mb-2">
                    Jadwal Pemasangan <span class="text-error">*</span>
                </label>
                <input type="datetime-local" name="scheduled_at" id="scheduled_at" x-model="scheduledAt" @change="checkConflicts()" required
                       class="w-full border border-border rounded-md px-3 py-2 text-sm text-text-main bg-surface focus:outline-none focus:ring-2 focus:ring-primary/25 focus:border-transparent transition">
            </div>

            {{-- Conflict warning --}}
            <div x-show="conflictUsers.length > 0"
                 class="mb-4 px-3 py-2.5 rounded-md border text-xs bg-warning/10 border-warning/30 text-warning">
                <p class="font-semibold mb-1">⚠ Konflik jadwal terdeteksi</p>
                <p class="text-warning">Teknisi berikut sudah punya task aktif pada waktu yang sama:</p>
                <ul class="mt-1 space-y-0.5">
                    <template x-for="u in conflictUsers" :key="u.id">
                        <li class="font-medium" x-text="'• ' + u.name"></li>
                    </template>
                </ul>
                @can('conflictOverride', \App\Models\Task::class)
                <label class="flex items-center gap-2 mt-2.5 cursor-pointer">
                    <input type="checkbox" name="conflict_override" value="1" x-model="conflictOverride" class="h-3.5 w-3.5 rounded accent-warning">
                    <span class="font-semibold">Override konflik jadwal</span>
                </label>
                @else
                <p class="mt-1.5 font-medium text-error">Anda tidak punya izin override konflik.</p>
                @endcan
            </div>

            {{-- Notes --}}
            <div class="mb-5">
                <label for="notes" class="block text-xs font-semibold text-text-muted uppercase tracking-widest mb-2">
                    Catatan untuk teknisi
                </label>
                <textarea name="notes" id="notes" x-model="notes" rows="3"
                          placeholder="Masukkan petunjuk khusus pengerjaan..."
                          class="w-full border border-border rounded-md px-3 py-2 text-sm text-text-main bg-surface focus:outline-none focus:ring-2 focus:ring-primary/25 focus:border-transparent transition"></textarea>
            </div>

            <div class="border-t border-border pt-4 bg-surface flex gap-2 justify-end">
                <button type="button" @click="$dispatch('close-drawer', 'process-to-tim')"
                        class="px-4 py-2 border border-border rounded-md text-xs font-semibold text-text-muted hover:bg-surface-muted transition-colors cursor-pointer">
                    Batal
                </button>
                <button type="submit" :disabled="submitting || (conflictUsers.length > 0 && !conflictOverride)"
                        :class="submitting || (conflictUsers.length > 0 && !conflictOverride) ? 'opacity-50 cursor-not-allowed' : ''"
                        class="px-4 py-2 bg-primary hover:bg-primary/90 text-white text-xs font-semibold rounded-md transition-colors leading-none flex items-center justify-center cursor-pointer">
                    <span x-text="submitting ? 'Memproses...' : 'Konfirmasi Proses'"></span>
                </button>
            </div>
        </form>
    </x-ui.drawer>
</div>

<script>
    function processToTimHandler() {
        return {
            customer: {
                id: null,
                survey_result: '',
                survey_distance: '',
                survey_media: '',
                survey_odp: '',
                survey_notes: '',
            },
            technicianId: '',
            scheduledAt: '',
            notes: '',
            conflictOverride: false,
            conflictUsers: [],
            submitting: false,

            init() {
                @if(session('active_customer_id'))
                    this.openProcessToTim({ id: {{ session('active_customer_id') }} });
                    this.technicianId = '{{ old("technician_id") }}';
                    this.scheduledAt = '{{ old("scheduled_at") }}';
                    this.notes = '{{ old("notes") }}';
                    this.conflictOverride = {{ old("conflict_override") ? 'true' : 'false' }};
                    this.checkConflicts();
                @endif
            },

            openProcessToTim(item) {
                this.customer = Object.assign(this.customer, item);
                this.technicianId = '';
                this.scheduledAt = '';
                this.notes = '';
                this.conflictOverride = false;
                this.conflictUsers = [];
                this.submitting = false;
                this.$dispatch('open-drawer', 'process-to-tim');
            },

            async checkConflicts() {
                if (!this.scheduledAt || !this.technicianId) {
                    this.conflictUsers = [];
                    return;
                }
                try {
                    const res  = await fetch('/api/tasks/check-conflict', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            user_ids:     [this.technicianId],
                            scheduled_at: this.scheduledAt,
                            task_type:    'pemasangan',
                        }),
                    });
                    const data = await res.json();
                    this.conflictUsers = data.conflict_users || [];
                    if (!data.has_conflict) this.conflictOverride = false;
                } catch (e) { /* silent */ }
            },

            submitForm(form) {
                if (this.conflictUsers.length > 0 && !this.conflictOverride) return;
                this.submitting = true;
                form.submit();
            }
        };
    }


    function openRejectModal(customerId) {
        const modal = document.getElementById('rejectModal');
        const form = document.getElementById('rejectForm');
        
        form.action = `/verifications/${customerId}/reject`;
        
        modal.classList.remove('hidden');
        setTimeout(() => {
            modal.classList.remove('opacity-0');
            modal.querySelector('div').classList.remove('scale-95');
        }, 10);
    }

    function closeRejectModal() {
        const modal = document.getElementById('rejectModal');
        modal.classList.add('opacity-0');
        modal.querySelector('div').classList.add('scale-95');
        setTimeout(() => {
            modal.classList.add('hidden');
            document.getElementById('rejectForm').reset();
        }, 300);
    }

    // Live Countdown Logic
    document.addEventListener('DOMContentLoaded', function() {
        const countdownElements = document.querySelectorAll('[id^="countdown-"]');
        
        function updateCountdowns() {
            const now = new Date();
            
            countdownElements.forEach(el => {
                const startTimeStr = el.getAttribute('data-start');
                if (!startTimeStr) return;
                
                const startTime = new Date(startTimeStr);
                const diffMs = now - startTime;
                
                if (diffMs < 0) {
                    el.textContent = "00:00:00";
                    return;
                }
                
                const hours = Math.floor(diffMs / 3600000);
                const minutes = Math.floor((diffMs % 3600000) / 60000);
                const seconds = Math.floor((diffMs % 60000) / 1000);
                
                const h = String(hours).padStart(2, '0');
                const m = String(minutes).padStart(2, '0');
                const s = String(seconds).padStart(2, '0');
                
                el.textContent = `${h}:${m}:${s}`;
            });
        }
        
        if (countdownElements.length > 0) {
            updateCountdowns(); // Initial call
            setInterval(updateCountdowns, 1000); // Update every second
        }
    });
</script>
@endsection
