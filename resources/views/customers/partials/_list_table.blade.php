{{-- Tabel desktop + kartu mobile daftar pelanggan. Dipakai List Pelanggan,
     Survey Pelanggan, dan Verifikasi Pelanggan — ketiganya menampilkan kolom
     yang sama persis, cuma beda filter status di controller. Halaman Putus &
     Gagal TIDAK pakai ini (kolomnya beda: alasan/tanggal/status alat).

     Butuh @include('customers.partials._quick_hub_modal') +
     @include('customers.partials._list_scripts') di halaman pemakainya —
     openActionsModal()/openNetworkAssignmentModal() dipanggil dari sini. --}}
        <!-- DESKTOP TABLE VIEW (hidden di mobile & tablet portrait) -->
        <div class="hidden lg:block overflow-x-auto">
            <table class="w-full min-w-full xl:min-w-[960px] border-collapse text-left" id="customerTable"
                   data-columns-save-url="{{ $customizable ? route('customers.list-columns.update') : '' }}">
                <caption class="sr-only">Daftar Pelanggan Whusnet Operasional</caption>
                <thead>
                    <tr class="bg-slate-50/80 dark:bg-slate-800/50 border-b border-slate-200/80 dark:border-slate-800 text-[11px] font-bold text-slate-600 dark:text-slate-300 uppercase tracking-wider">
                        {{-- Header dirender dari $columns (katalog + preferensi user). Klik =
                             sort server-side: kolom baru → ASC, lagi → DESC, lagi → urutan
                             default. Hanya List Pelanggan utama ($customizable); Survey &
                             Verifikasi tetap header polos. URL sort dirender server
                             (fullUrlWithQuery) supaya filter/pencarian ikut terbawa. --}}
                        @foreach($columns as $colKey => $col)
                            @php
                                $alignClass = match ($col['align']) { 'right' => 'text-right', 'center' => 'text-center', default => '' };
                                $isSorted = $customizable && $sort === $colKey;
                                $nextQuery = ! $isSorted
                                    ? ['sort' => $colKey, 'dir' => 'asc', 'page' => null]
                                    : ($dir === 'asc'
                                        ? ['sort' => $colKey, 'dir' => 'desc', 'page' => null]
                                        : ['sort' => null, 'dir' => null, 'page' => null]);
                            @endphp
                            <th data-col="{{ $colKey }}" @if($customizable) draggable="true" @endif scope="col"
                                class="py-3.5 px-2 xl:px-3 whitespace-nowrap select-none {{ $alignClass }} {{ $customizable ? 'cursor-grab' : '' }}">
                                @if($customizable && $col['sort'])
                                    <a href="{{ request()->fullUrlWithQuery($nextQuery) }}"
                                       class="inline-flex items-center gap-1 hover:text-sky-600 dark:hover:text-sky-400 {{ $isSorted ? 'text-sky-600 dark:text-sky-400' : '' }}"
                                       title="Klik untuk urutkan"
                                       aria-label="Urutkan kolom {{ $col['label'] }}">
                                        <span>{{ $col['label'] }}</span>
                                        <span class="text-[9px]" aria-hidden="true">{{ $isSorted ? ($dir === 'desc' ? '▼' : '▲') : '' }}</span>
                                    </a>
                                @else
                                    {{ $col['label'] }}
                                @endif
                            </th>
                        @endforeach
                        <th scope="col" data-col="aksi" class="py-3.5 px-2 xl:px-3 text-center w-16">AKSI</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 text-xs text-slate-700 dark:text-slate-200">
                    @forelse($customers as $customer)
                    @php
                        $displayId = $customer->display_id;
                        $completeness = $customer->dataCompleteness();
                        $cleanPhone = preg_replace('/[^0-9]/', '', $customer->primary_phone ?? '');
                        if (str_starts_with($cleanPhone, '0')) {
                            $cleanPhone = '62' . substr($cleanPhone, 1);
                        }
                    @endphp
                    {{-- data-customer-row: penanda baris untuk navigasi keyboard.
                         Sebelumnya baris dikenali lewat checkbox .select-customer —
                         begitu checkbox dihapus, shortcut ↑/↓/Home/End/Enter ikut mati. --}}
                    <tr data-customer-row class="hover:bg-sky-50/40 dark:hover:bg-sky-950/20 transition-colors group">
                        @foreach($columns as $colKey => $col)
                            @include('customers.partials.columns._'.$colKey)
                        @endforeach
                        @include('customers.partials.columns._aksi')
                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ count($columns) + 1 }}" class="px-6 py-8 text-center text-slate-600 dark:text-slate-400">Tidak ada data pelanggan yang cocok.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- MOBILE & TABLET PORTRAIT CARD VIEW (block di mobile & tablet) -->
        <div class="block lg:hidden p-3 sm:p-4">
            <div class="grid grid-cols-1 md:grid-cols-1 lg:grid-cols-2 gap-3">
                @forelse($customers as $customer)
                @php
                    $displayId = $customer->display_id;
                    $completeness = $customer->dataCompleteness();
                    $isActive = $customer->status === 'active';
                    $isSuspended = $customer->status === 'suspended';
                    $isPaid = $customer->latestInvoice && $customer->latestInvoice->invoice_status === \App\Enums\InvoiceStatus::LUNAS;
                    $isOverdue = $customer->latestInvoice && !$isPaid && $customer->latestInvoice->isPiutang();
                @endphp
                <div class="p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm hover:border-sky-300 dark:hover:border-sky-800 transition-all card-interactive flex flex-col justify-between space-y-3">
                    <div class="space-y-2">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                @if(auth()->user()->hasPermission('customers.detail.installation.validate'))
                                <button type="button" onclick="openNetworkAssignmentModal('{{ route('customers.network-assignment.update', $customer->id) }}', '{{ route('customers.network-assignment.data', $customer->id) }}')"
                                        aria-label="Atur jaringan untuk {{ $customer->full_name }} ({{ $displayId }})"
                                        class="font-mono text-xs font-bold text-sky-600 dark:text-sky-400 flex items-center gap-1 hover:underline text-left">
                                    <span>{{ $displayId }}</span>
                                    <svg class="w-3 h-3 text-sky-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/></svg>
                                </button>
                                @else
                                <span class="font-mono text-xs font-bold text-sky-600 dark:text-sky-400">{{ $displayId }}</span>
                                @endif
                                <h3 class="font-bold text-slate-900 dark:text-white text-base mt-0.5">{{ $customer->full_name }}</h3>
                                @if($customer->collector)
                                    <span class="inline-flex items-center mt-1 px-1.5 py-0.5 text-[9px] font-bold rounded border bg-violet-50 dark:bg-violet-500/10 text-violet-700 dark:text-violet-400 border-violet-100 dark:border-violet-500/20">
                                        Kolektor: {{ $customer->collector->name }}
                                    </span>
                                @endif
                            </div>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-semibold border shrink-0
                                  {{ $isActive ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800' : '' }}
                                  {{ $isSuspended ? 'bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800' : '' }}
                                  {{ !$isActive && !$isSuspended ? 'bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800' : '' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $isActive ? 'bg-emerald-500 animate-pulse-glow' : ($isSuspended ? 'bg-amber-500' : 'bg-rose-500') }} mr-1" aria-hidden="true"></span>
                                <span>{{ $customer->subscriptionStatus->name ?? ucfirst($customer->status) }}</span>
                            </span>
                        </div>

                        <div class="grid grid-cols-2 gap-2 text-xs bg-slate-50 dark:bg-slate-800/50 p-2.5 rounded-xl border border-slate-100 dark:border-slate-800">
                            <div>
                                <span class="text-slate-600 dark:text-slate-400 text-[10px] block font-medium">POP / Desa</span>
                                <span class="font-medium text-slate-700 dark:text-slate-200">{{ $customer->pop->name ?? '-' }} · {{ $customer->village->name ?? ($customer->customerAddress->village ?? '-') }}</span>
                            </div>
                            <div>
                                <span class="text-slate-600 dark:text-slate-400 text-[10px] block font-medium">Tagihan</span>
                                <span class="font-mono font-semibold {{ $isPaid ? 'text-emerald-600 dark:text-emerald-400' : ($isOverdue ? 'text-rose-600 dark:text-rose-400' : 'text-amber-600 dark:text-amber-400') }}">
                                    Rp {{ number_format($customer->latestInvoice->total_amount ?? 0, 0, ',', '.') }}
                                </span>
                                <span class="text-[9px] font-bold block {{ $isPaid ? 'text-emerald-500' : ($isOverdue ? 'text-rose-500' : 'text-amber-500') }}">
                                    ● {{ $isPaid ? 'LUNAS' : ($isOverdue ? 'PIUTANG' : 'BELUM BAYAR') }}
                                </span>
                            </div>
                            <div class="col-span-2">
                                <span class="text-slate-600 dark:text-slate-400 text-[10px] block font-medium">Paket Internet</span>
                                <span class="font-mono text-slate-600 dark:text-slate-300">{{ $customer->internetPackage ? $customer->internetPackage->package_code . ' - ' . $customer->internetPackage->name : '-' }}</span>
                                <x-warehouse.acquisition-badge :device="$customer->customerDevice" class="block mt-1 w-fit" />
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                        @if($customer->latestPayment)
                            <a href="{{ route('payments.receipt', $customer->latestPayment->id) }}"
                               target="_blank"
                               title="Cetak Struk / Kwitansi Pembayaran Terakhir"
                               aria-label="Cetak struk pembayaran terakhir untuk {{ $customer->full_name }}"
                               class="px-2.5 py-1.5 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/40 text-rose-700 dark:text-rose-300 text-xs font-semibold inline-flex items-center gap-1 hover:bg-rose-100 shrink-0 touch-target btn-interactive">
                                <svg class="w-4 h-4 text-rose-600 dark:text-rose-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                <span>Struk</span>
                            </a>
                        @else
                            <button type="button"
                                    onclick="showModalToast('Belum ada pembayaran/kwitansi untuk pelanggan ini.')"
                                    title="Belum ada pembayaran yang bisa dicetak"
                                    aria-label="Belum ada pembayaran yang bisa dicetak untuk {{ $customer->full_name }}"
                                    class="px-2.5 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-400 text-xs font-semibold inline-flex items-center gap-1 opacity-60 cursor-not-allowed shrink-0">
                                <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v4a2 2 0 002 2z"/></svg>
                                <span>Invoice</span>
                            </button>
                        @endif

                        <button type="button"
                                onclick="openActionsModal(this)"
                                aria-label="Detail pelanggan {{ $customer->full_name }} ({{ $displayId }})"
                                data-id="{{ $customer->id }}"
                                data-code="{{ $displayId }}"
                                data-name="{{ $customer->full_name }}"
                                data-nik="{{ $customer->identity_number ?? '-' }}"
                                data-phone="{{ $customer->primary_phone }}"
                                data-email="{{ $customer->email ?? '-' }}"
                                data-status="{{ $customer->subscriptionStatus->name ?? Str::headline($customer->status) }}"
                                data-raw-status="{{ $customer->status }}"
                                data-pop="{{ $customer->pop->name ?? '-' }}"
                                data-reg="{{ \App\Support\IndonesianDate::date($customer->registration_date) }}"
                                data-package="{{ $customer->internetPackage ? $customer->internetPackage->package_code . ' - ' . $customer->internetPackage->name : '-' }}"
                                data-bandwidth="{{ $customer->internetPackage?->speed_mbps ? $customer->internetPackage->speed_mbps . ' Mbps' : '-' }}"
                                data-price="{{ $customer->internetPackage ? 'Rp ' . number_format($customer->internetPackage->monthly_price, 0, ',', '.') : '-' }}"
                                data-due-date="{{ $customer->latestInvoice ? \App\Support\IndonesianDate::date($customer->latestInvoice->due_date) : '-' }}"
                                data-address="{{ $customer->address }}"
                                data-landmark="{{ $customer->customerAddress->landmark ?? '-' }}"
                                data-rt-rw="{{ ($customer->customerAddress?->rt ? 'RT ' . $customer->customerAddress->rt : '') . ($customer->customerAddress?->rw ? ' / RW ' . $customer->customerAddress->rw : '') ?: '-' }}"
                                data-village="{{ $customer->village->name ?? ($customer->customerAddress->village ?? '-') }}"
                                data-district="{{ $customer->district->name ?? ($customer->customerAddress->district ?? '-') }}"
                                data-city="{{ $customer->city->name ?? ($customer->customerAddress->city ?? 'Kab. Ponorogo') }}"
                                data-postal-code="{{ $customer->customerAddress->postal_code ?? '-' }}"
                                data-lat="{{ $customer->customerAddress->latitude ?? '' }}"
                                data-lng="{{ $customer->customerAddress->longitude ?? '' }}"
                                data-completeness-pct="{{ $completeness['percentage'] }}"
                                data-completeness-status="{{ Str::headline($customer->data_completeness_status ?? 'draft') }}"
                                data-pppoe="{{ $customer->customerDevice?->pppoe_username ?: ($customer->customerService?->pppoe_username ?: ($customer->pppoe_username ?: '-')) }}"
                                data-vlan="{{ $customer->customerDevice?->vlan_id ?? ($customer->vlan_id ?? '-') }}"
                                data-onu="{{ $customer->customerDevice?->serial_number ?? ($customer->ont_sn ?? ($customer->customerDevice?->mac_address ?? '-')) }}"
                                data-onu-brand="{{ $customer->customerDevice?->brand ?? '-' }}"
                                data-router="{{ ($customer->customerDevice?->brand ? trim($customer->customerDevice->brand . ' ' . ($customer->customerDevice->model ?? '')) : ($customer->customerDevice?->mac_address ?? '-')) ?: '-' }}"
                                data-router-brand="{{ $customer->customerDevice?->model ?? '-' }}"
                                data-contract="{{ match($customer->customerService->contract_type ?? null) { 'sewa' => 'Sewa', 'beli' => 'Beli', default => '-' } }}"
                                data-distribution="{{ $customer->customerDevice?->odp ?: ($customer->distribution->name ?? ($customer->odp_code ?? '-')) }}"
                                    data-detail-url="{{ route('customers.show', $customer->id) }}"
                                    data-payment-info-url="{{ route('customers.payment-info', $customer->id) }}"
                                    data-network-update-url="{{ route('customers.network-assignment.update', $customer->id) }}"
                                    data-network-data-url="{{ route('customers.network-assignment.data', $customer->id) }}"
                                    data-package-update-url="{{ route('customers.package.update', $customer->id) }}"
                                    data-current-package-id="{{ $customer->customerService->internet_package_id ?? '' }}"
                                    data-toggle-suspend-url="{{ route('customers.toggle-suspend', $customer->id) }}"
                                class="flex-1 py-1.5 px-2.5 rounded-xl bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold flex items-center justify-center gap-1 shadow-md shadow-sky-600/20 transition-all btn-interactive touch-target min-w-0">
                            <span class="truncate">Detail Pelanggan</span>
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </button>
                    </div>
                </div>
                @empty
                <div class="p-8 text-center text-slate-400 text-xs">Tidak ada data pelanggan yang cocok.</div>
                @endforelse
            </div>
        </div>
