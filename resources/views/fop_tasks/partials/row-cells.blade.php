{{-- Tiga <td> ini (Teknisi, Team, Status) sengaja dipisah dari fop_tasks/index.blade.php
     biar bisa di-refetch per baris lewat fop-tasks.row saat App\Events\FopTaskUpdated
     masuk (Echo) — ganti setTimeout(reload) lama (docs/plan/analisa-realtime-spa-
     operasional.md §2.2 no. 13). Dipakai DUA cara: render awal (@include di
     index.blade.php) dan fragment mentah lewat FopTaskController::row(). Tombol di
     sini pakai @click Alpine (bukan onclick) — kalau di-inject lewat fetch, WAJIB
     dipanggil window.Alpine.initTree() setelahnya biar directive-nya kebind. --}}
<td class="px-3 py-2" id="tech-cell-{{ $task->id }}">
    <div class="flex flex-wrap gap-1 items-start min-w-[150px]">
        @php
            $visibleTechs = $task->technicians->take(2);
            $hiddenTechsCount = $task->technicians->count() - 2;
        @endphp
        @forelse($visibleTechs as $tech)
            @php
                // Ambil nama depan saja untuk menghemat ruang
                $firstName = explode(' ', trim($tech->name))[0];
            @endphp
            <button type="button"
                @click="openSwitchModal({{ $task->id }}, '{{ $task->task_number }}', @js($task->tugas), '{{ $task->task_date?->toDateString() }}', {{ $tech->id }}, @js($tech->name))"
                class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-blue-50 text-blue-700 border border-blue-100 hover:bg-blue-100 hover:border-blue-300 transition-colors"
                title="{{ $tech->name }} — klik buat Switch Teknisi">
                {{ \Illuminate\Support\Str::limit($firstName, 12) }}
            </button>
        @empty
            <span class="text-slate-400 dark:text-slate-500 text-[10px] italic">Unassigned</span>
        @endforelse

        @if($hiddenTechsCount > 0)
            <div class="relative" x-data="{ openHidden: false }">
                <button type="button" @click="openHidden = !openHidden"
                    class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-slate-100 dark:bg-slate-700/50 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700 hover:bg-slate-200 transition-colors">
                    +{{ $hiddenTechsCount }}
                </button>
                <div x-show="openHidden" @click.away="openHidden = false"
                    class="absolute z-40 mt-1 min-w-[140px] bg-surface border border-border rounded shadow-lg py-1"
                    style="display: none;">
                    @foreach($task->technicians->skip(2) as $tech)
                        <button type="button"
                            @click="openSwitchModal({{ $task->id }}, '{{ $task->task_number }}', @js($task->tugas), '{{ $task->task_date?->toDateString() }}', {{ $tech->id }}, @js($tech->name)); openHidden = false"
                            class="w-full text-left px-3 py-1.5 text-[11px] text-text-secondary hover:bg-surface-muted transition-colors">
                            {{ $tech->name }}
                        </button>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</td>
<td class="px-3 py-2 whitespace-nowrap" id="team-cell-{{ $task->id }}">
    @if($task->team)
        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-sky-50 dark:bg-sky-900/20 text-sky-700 dark:text-sky-400 border border-sky-100 dark:border-sky-800/30">
            {{ $task->team->name }}
        </span>
    @elseif($task->technicians->count() === 1)
        <button type="button"
                @click="openTeamSelectionModal({{ $task->id }}, '{{ $task->task_number }}', '{{ addslashes($task->tugas) }}', '{{ $task->task_date?->format('Y-m-d') }}')"
                class="text-[10px] text-blue-600 hover:text-blue-800 font-medium underline decoration-dotted">
            + Masukkan ke Team...
        </button>
    @else
        @php
            $taskDate = $task->task_date?->toDateString();
            $techIds = $task->technicians->pluck('id')->all();
            $candidates = \App\Models\FopTaskTeam::whereDate('work_date', $taskDate)
                ->whereHas('members', fn($q) => $q->whereIn('users.id', $techIds))
                ->get()
                ->map(fn($t) => ['team_id' => $t->id, 'team_name' => $t->name])
                ->all();
        @endphp
        @if(count($candidates) >= 2)
            <button type="button"
                    @click="triggerConflictModal({{ $task->id }}, '{{ $task->task_number }}', {{ json_encode($candidates) }})"
                    class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-medium bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 border border-red-100 dark:border-red-800/30 hover:bg-red-100 transition-colors"
                    title="Klik untuk memilih team">
                ⚠️ Konflik Roster
            </button>
        @else
            <span class="text-slate-300 text-[10px]">—</span>
        @endif
    @endif
</td>
<td class="px-3 py-2 whitespace-nowrap" id="status-cell-{{ $task->id }}">
    @php
        // FopTask.status share vocab persis sama TaskStatus (unifikasi
        // 2026-07-20) — kalau udah ada Task eksekusi terhubung, pakai label/
        // badge dari situ (bawa nuansa report_deferred). Kalau belum (FopTask
        // standalone, task_id null, masih 'draft' — belum ada teknisi
        // di-assign), pakai punya FopTask sendiri, dikasih label khusus biar
        // gak nyesatin ("draft" doang kurang jelas buat FOP).
        $statusValue = $task->status->value;
        $statusLabel = $task->task
            ? $task->task->status->displayLabel($task->task->report_deferred)
            : ($statusValue === 'draft' ? 'Belum Ditugaskan' : $task->status->displayLabel());
        $statusClasses = $task->task
            ? $task->task->status->displayBadgeClasses($task->task->report_deferred)
            : ($statusValue === 'draft' ? 'border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-400 bg-slate-50 dark:bg-slate-800/50' : $task->status->displayBadgeClasses());
    @endphp
    <div class="flex flex-col gap-1 items-start">
        <span class="inline-flex items-center px-2 py-1 rounded text-[11px] font-medium border w-fit {{ $statusClasses }}"
              title="Status realtime — derived otomatis dari status Task teknisi, gak bisa diedit manual">
            {{ $statusLabel }}
        </span>
        <div class="flex flex-col gap-0.5 mt-0.5">
            {{-- SRV/PSB gak boleh dibatalkan dari sini — harus lewat halaman
                 Customer (tab Survey/Pemasangan), biar masuk List Pelanggan
                 Gagal. Lihat TaskPolicy::cancel() & FopTaskController::update(). --}}
            @can('fop_tasks.cancel')
                @if(!in_array($statusValue, ['selesai', 'dibatalkan']) && !in_array($task->category->value, ['SURVEY', 'PSB']))
                    <button type="button"
                            @click="openCancelModal({{ $task->id }}, '{{ $task->task_number }}')"
                            class="text-[10px] text-red-600 dark:text-red-400 underline decoration-dotted text-left cursor-pointer">
                        Cancel
                    </button>
                @endif
            @endcan
        </div>
    </div>
</td>
