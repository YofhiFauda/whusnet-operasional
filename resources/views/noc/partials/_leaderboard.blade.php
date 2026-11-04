{{--
    Leaderboard performa individu Helpdesk & NOC — diekstrak dari
    deep-analytics.blade.php biar bisa dipakai ULANG oleh endpoint
    noc.dashboard.leaderboard-stats (live-refresh partial, lihat
    NocDashboardController::leaderboardStats()) tanpa duplikasi markup.
--}}
@if($canViewPerformance)
    <div id="leaderboard-container" class="bg-surface border border-border rounded-xl overflow-hidden shadow-xs">
        <div class="px-5 py-3.5 border-b border-border bg-slate-50/50 dark:bg-slate-900/40">
            <h2 class="text-xs font-black uppercase tracking-wider text-text-main flex items-center gap-2">
                <svg class="w-4 h-4 text-violet-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                Leaderboard Performa Individu
            </h2>
            <p class="text-[11px] text-text-muted mt-0.5">Diukur dari riwayat tiket (ticket_histories) per aktor — satu tiket bisa dipegang lebih dari satu orang, jadi atribusinya per aksi, bukan per tiket.</p>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-2 divide-y lg:divide-y-0 lg:divide-x divide-border">
            {{-- Helpdesk --}}
            <div class="p-4">
                <h3 class="text-[11px] font-extrabold uppercase text-sky-600 dark:text-sky-400 mb-2 flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span> Helpdesk
                </h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead>
                            <tr class="border-b border-border text-[10px] uppercase text-text-muted font-extrabold">
                                <th class="py-2 pr-2">Nama</th>
                                <th class="py-2 px-2 text-center">Selesai</th>
                                <th class="py-2 px-2 text-center">Eskalasi NOC</th>
                                <th class="py-2 px-2 text-center">Avg. Durasi</th>
                                <th class="py-2 pl-2 text-center">SLA Breach</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/60">
                            @forelse($leaderboard['helpdesk'] as $row)
                                <tr>
                                    <td class="py-2 pr-2 font-bold text-text-main truncate max-w-[140px]">{{ $row['name'] }}</td>
                                    <td class="py-2 px-2 text-center font-mono font-bold text-emerald-600 dark:text-emerald-400">{{ $row['jumlah_selesai'] }}</td>
                                    <td class="py-2 px-2 text-center font-mono">{{ $row['jumlah_eskalasi_noc'] }}</td>
                                    <td class="py-2 px-2 text-center font-mono text-text-muted">
                                        {{ $row['avg_durasi_menit'] !== null ? ($row['avg_durasi_menit'] < 60 ? $row['avg_durasi_menit'].'m' : intdiv($row['avg_durasi_menit'], 60).'j '.($row['avg_durasi_menit'] % 60).'m') : '-' }}
                                    </td>
                                    <td class="py-2 pl-2 text-center font-mono {{ $row['sla_breach_count'] > 0 ? 'text-rose-600 dark:text-rose-400 font-bold' : 'text-text-muted' }}">{{ $row['sla_breach_count'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="py-6 text-center text-text-muted">Belum ada data pada periode ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- NOC --}}
            <div class="p-4">
                <h3 class="text-[11px] font-extrabold uppercase text-amber-600 dark:text-amber-400 mb-2 flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> NOC
                </h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs text-left">
                        <thead>
                            <tr class="border-b border-border text-[10px] uppercase text-text-muted font-extrabold">
                                <th class="py-2 pr-2">Nama</th>
                                <th class="py-2 px-2 text-center">Selesai</th>
                                <th class="py-2 px-2 text-center">Eskalasi FOP</th>
                                <th class="py-2 px-2 text-center">Avg. Durasi</th>
                                <th class="py-2 pl-2 text-center">SLA Breach</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/60">
                            @forelse($leaderboard['noc'] as $row)
                                <tr>
                                    <td class="py-2 pr-2 font-bold text-text-main truncate max-w-[140px]">{{ $row['name'] }}</td>
                                    <td class="py-2 px-2 text-center font-mono font-bold text-emerald-600 dark:text-emerald-400">{{ $row['jumlah_selesai'] }}</td>
                                    <td class="py-2 px-2 text-center font-mono">{{ $row['jumlah_eskalasi_fop'] }}</td>
                                    <td class="py-2 px-2 text-center font-mono text-text-muted">
                                        {{ $row['avg_durasi_menit'] !== null ? ($row['avg_durasi_menit'] < 60 ? $row['avg_durasi_menit'].'m' : intdiv($row['avg_durasi_menit'], 60).'j '.($row['avg_durasi_menit'] % 60).'m') : '-' }}
                                    </td>
                                    <td class="py-2 pl-2 text-center font-mono {{ $row['sla_breach_count'] > 0 ? 'text-rose-600 dark:text-rose-400 font-bold' : 'text-text-muted' }}">{{ $row['sla_breach_count'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="py-6 text-center text-text-muted">Belum ada data pada periode ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endif
