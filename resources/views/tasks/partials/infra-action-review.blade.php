@if($infraActions->isNotEmpty())
    @php
        $actionLabels = [
            'inspect' => 'Pemeriksaan lokasi',
            'install' => 'Pemasangan barang jaringan',
            'retrieve' => 'Pencabutan perangkat',
            'replace' => 'Penggantian perangkat',
            'port_connect' => 'Penyambungan port',
            'port_release' => 'Pelepasan sambungan port',
        ];
        $stockMessages = [
            'inspect' => 'Tidak mengubah stok',
            'install' => 'Barang dipindahkan dari tim ke lokasi ini',
            'retrieve' => 'Barang cabutan menunggu pemeriksaan Gudang',
            'replace' => 'SN lama dicabut, SN baru dipasang',
            'port_connect' => 'Tidak mengubah stok',
            'port_release' => 'Tidak mengubah stok',
        ];
    @endphp
    <section class="border-y border-border py-4">
        <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
            <h4 class="text-xs font-semibold uppercase tracking-wider text-text-main">Rincian tindakan</h4>
            <span class="text-xs text-text-muted">{{ $infraActions->count() }} tindakan tercatat</span>
        </div>
        <ol class="divide-y divide-border">
            @foreach($infraActions as $action)
                <li class="py-4 first:pt-0 last:pb-0">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <div class="flex items-baseline gap-2">
                            <span class="font-mono text-xs font-semibold text-primary">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                            <h5 class="text-sm font-semibold text-text-main">{{ $actionLabels[$action->action_type] ?? $action->action_type }}</h5>
                        </div>
                        <span class="text-xs text-text-muted">{{ $action->technician?->name ?? 'Teknisi' }} · {{ $action->performed_at?->format('d M Y H:i') }}</span>
                    </div>
                    <dl class="mt-2 grid gap-2 text-xs sm:grid-cols-2">
                        <div><dt class="text-text-muted">Lokasi</dt><dd class="mt-0.5 font-medium text-text-main">{{ $action->node?->breadcrumbLabel() ?? 'Pekerjaan kantor / tanpa node' }}</dd></div>
                        <div><dt class="text-text-muted">Dampak aset</dt><dd class="mt-0.5 font-medium text-text-main">{{ $stockMessages[$action->action_type] ?? 'Tercatat' }}</dd></div>
                    </dl>
                    @if($action->result_note)
                        <p class="mt-3 text-sm leading-relaxed text-text-secondary">{{ $action->result_note }}</p>
                    @endif
                    @if($action->mutation['slot_id'] ?? null)
                        <p class="mt-2 text-xs text-text-secondary">Slot OLT: {{ $infraSlots->get($action->mutation['slot_id'])?->number ?? 'tidak tersedia' }}</p>
                    @endif
                    @if($action->mutation['port_id'] ?? null)
                        <p class="mt-2 text-xs text-text-secondary">
                            Port asal: {{ $infraPorts->get($action->mutation['port_id'])?->number ?? 'tidak tersedia' }}
                            @if($action->mutation['target_node_id'] ?? null)
                                → {{ $infraTargetNodes->get($action->mutation['target_node_id'])?->breadcrumbLabel() ?? 'Node tujuan tidak tersedia' }}
                                @if($action->mutation['target_port_id'] ?? null)
                                    · port {{ $infraPorts->get($action->mutation['target_port_id'])?->number ?? 'tidak tersedia' }}
                                @endif
                            @endif
                        </p>
                    @endif
                    @if($action->transactions->isNotEmpty())
                        <div class="mt-3 border-t border-border pt-3">
                            <p class="mb-1 text-xs font-semibold text-text-main">Catatan barang</p>
                            <ul class="space-y-1 text-xs text-text-secondary">
                                @foreach($action->transactions as $transaction)
                                    <li>
                                        {{ $transaction->type instanceof \App\Enums\InventoryTransactionType ? $transaction->type->label() : 'Perubahan barang' }}:
                                        <span class="font-medium text-text-main">{{ $transaction->item?->name ?? 'Barang' }}</span>
                                        @if($transaction->serial)
                                            · SN {{ $transaction->serial->serial_number }}
                                        @else
                                            · {{ $transaction->qty }} {{ $transaction->item?->unit }}
                                        @endif
                                        <span class="text-text-muted">({{ $transaction->reference_number }})</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </li>
            @endforeach
        </ol>
    </section>
@endif
