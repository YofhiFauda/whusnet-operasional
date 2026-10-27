{{-- ══ Pemakaian Alat Gudang & Telemetri Material ════════════════ --}}
<div id="gudang-stats-container" class="flex flex-col gap-3 font-sans">
    <div class="flex items-center justify-between">
        <div>
            <h4 class="text-xs font-bold text-text-main uppercase tracking-wider">Pemakaian Alat Gudang (Hari Ini)</h4>
            <p class="text-[11px] text-text-muted">Pergerakan material harian dan pengawasan modem di lapangan</p>
        </div>
        <div class="flex items-center gap-3">
            @can('warehouse.usage.view')
            <a href="{{ route('warehouse.usage.index') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:text-primary-hover transition-colors cursor-pointer">
                <span>Rekap Pemakaian (Harian/Mingguan/Bulanan/Custom)</span>
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                </svg>
            </a>
            @endcan
            @can('warehouse_report.view')
            <a href="{{ route('warehouse.reports.index') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:text-primary-hover transition-colors cursor-pointer">
                <span>Lihat Laporan Gudang</span>
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                </svg>
            </a>
            @endcan
        </div>
    </div>

    {{-- 3 Hero Tiles Gudang (Kompatibel 100% dengan FopDashboardGudangStatsTest) --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div class="metric-card status-info">
            <div>
                <div class="metric-card-label"><span>Keluar dari Gudang</span></div>
                <div class="metric-card-value-container">
                    <p class="metric-card-value">{{ rtrim(rtrim(number_format($gudangStats['keluar'], 2, ',', '.'), '0'), ',') }}</p>
                </div>
            </div>
            <p class="metric-card-footer">Diserahkan ke teknisi hari ini</p>
        </div>
        <div class="metric-card status-success">
            <div>
                <div class="metric-card-label"><span>Terpakai</span></div>
                <div class="metric-card-value-container">
                    <p class="metric-card-value">{{ rtrim(rtrim(number_format($gudangStats['terpakai'], 2, ',', '.'), '0'), ',') }}</p>
                </div>
            </div>
            <p class="metric-card-footer">Dipasang/dihabiskan di laporan hari ini</p>
        </div>
        <div class="metric-card status-warning">
            <div>
                <div class="metric-card-label"><span>Sisa di Tangan Teknisi</span></div>
                <div class="metric-card-value-container">
                    <p class="metric-card-value">{{ rtrim(rtrim(number_format($gudangStats['sisa_di_teknisi'], 2, ',', '.'), '0'), ',') }}</p>
                </div>
            </div>
            <p class="metric-card-footer">Belum dipakai / belum dikembalikan</p>
        </div>
    </div>
    <p class="text-[10px] text-text-muted italic -mt-1">
        Catatan: "Keluar" (gudang→teknisi) dan "Terpakai" (laporan task) dihitung dari sumber & rentang scope yang berbeda — jangan dibandingkan langsung sebagai keluar = terpakai + sisa.
    </p>

    {{-- Stok Kritis ONT/Modem Widget --}}
    @if(!empty($warehouseTelemetry['critical_stock_items']) && count($warehouseTelemetry['critical_stock_items']) > 0)
    <div id="critical-stock-widget" class="bg-surface border border-error-border rounded-xl p-4 shadow-xs">
        <div class="flex items-center justify-between mb-2.5">
            <div class="flex items-center gap-2">
                <span class="h-2 w-2 rounded-full bg-error animate-pulse"></span>
                <span class="text-xs font-bold text-text-main uppercase tracking-wider">Stok Kritis ONT/Modem</span>
            </div>
            <span class="text-[10px] font-mono font-bold text-error bg-error-bg border border-error-border px-2 py-0.5 rounded">
                {{ count($warehouseTelemetry['critical_stock_items']) }} Item
            </span>
        </div>
        <div class="divide-y divide-border/60">
            @foreach($warehouseTelemetry['critical_stock_items'] as $item)
            <div class="py-2 first:pt-0 last:pb-0 flex items-center justify-between text-[11px]">
                <div class="flex items-center gap-2 truncate flex-1 mr-2">
                    <span class="font-semibold text-text-main truncate">{{ $item['item_name'] }}</span>
                    <span class="text-text-muted truncate">({{ $item['pop_name'] }})</span>
                </div>
                <span class="font-mono font-bold text-error shrink-0">{{ rtrim(rtrim(number_format($item['qty'], 2, ',', '.'), '0'), ',') }} / {{ rtrim(rtrim(number_format($item['minimum_stock'], 2, ',', '.'), '0'), ',') }}</span>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Modem Retur Transit Watchlist Widget --}}
    <div id="warehouse-telemetry-widget" class="bg-surface border border-border rounded-xl p-4 shadow-xs">
        <div class="flex items-center justify-between mb-2.5">
            <div class="flex items-center gap-2">
                <span class="h-2 w-2 rounded-full bg-warning animate-pulse"></span>
                <span class="text-xs font-bold text-text-main uppercase tracking-wider">Watchlist Modem Retur Transit</span>
            </div>
            <span class="text-[10px] font-mono font-bold text-warning bg-warning-bg border border-warning-border px-2 py-0.5 rounded">
                {{ count($warehouseTelemetry['returned_serials_in_transit'] ?? []) }} Unit di Teknisi
            </span>
        </div>

        @if(!empty($warehouseTelemetry['returned_serials_in_transit']) && count($warehouseTelemetry['returned_serials_in_transit']) > 0)
            <div class="divide-y divide-border/60 max-h-48 overflow-y-auto custom-scrollbar">
                @foreach($warehouseTelemetry['returned_serials_in_transit'] as $ser)
                    <div class="py-2 first:pt-0 last:pb-0 flex items-center justify-between text-[11px]">
                        <div class="flex items-center gap-2 truncate flex-1 mr-2">
                            <span class="font-mono font-bold text-text-main">{{ $ser['serial_number'] }}</span>
                            <span class="text-text-muted truncate">({{ $ser['item_name'] }})</span>
                        </div>
                        <div class="flex items-center gap-2 shrink-0 text-text-secondary text-[10px]">
                            <span class="font-medium text-primary">{{ $ser['technician_name'] }}</span>
                            <span class="text-text-muted">· {{ $ser['since_human'] }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
            <p class="text-[10px] text-text-muted mt-2 pt-2 border-t border-border/50">
                Modem hasil cabut pelanggan (DEAC/Swap) wajib diverifikasi penerimaannya di Gudang Cabang.
            </p>
        @else
            <div class="py-3 text-center text-[11px] text-text-muted italic">
                Tidak ada modem retur tertahan di teknisi. Semua retur telah diterima gudang.
            </div>
        @endif
    </div>
</div>
