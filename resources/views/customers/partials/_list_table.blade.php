{{-- Tabel desktop + kartu mobile daftar pelanggan. Dipakai List Pelanggan,
     Survey Pelanggan, dan Verifikasi Pelanggan — ketiganya menampilkan kolom
     yang sama persis, cuma beda filter status di controller. Halaman Putus &
     Gagal TIDAK pakai ini (kolomnya beda: alasan/tanggal/status alat).

     Butuh @include('customers.partials._quick_hub_modal') +
     @include('customers.partials._list_scripts') di halaman pemakainya —
     openActionsModal()/openNetworkAssignmentModal() dipanggil dari sini. --}}
        <!-- DESKTOP TABLE VIEW (hidden di mobile & tablet portrait) -->
        <div class="hidden lg:block overflow-x-auto">
            <table class="w-full min-w-full xl:min-w-[960px] border-collapse text-left" id="customerTable">
                <thead>
                    <tr class="bg-slate-50/80 dark:bg-slate-800/50 border-b border-slate-200/80 dark:border-slate-800 text-[11px] font-bold text-slate-400 uppercase tracking-wider">
                        <th class="py-3.5 pl-4 px-2 xl:px-3">CID (ID PELANGGAN)</th>
                        <th class="py-3.5 px-2 xl:px-3">NAMA LENGKAP</th>
                        <th class="py-3.5 px-2 xl:px-3">POP & DESA</th>
                        <th class="py-3.5 px-2 xl:px-3">PAKET INTERNET</th>
                        <th class="py-3.5 px-2 xl:px-3 hidden 2xl:table-cell">NO. TELEPON</th>
                        <th class="py-3.5 px-2 xl:px-3 hidden 2xl:table-cell">JATUH TEMPO</th>
                        <th class="py-3.5 px-2 xl:px-3 text-right">TAGIHAN</th>
                        <th class="py-3.5 px-2 xl:px-3 text-center hidden 2xl:table-cell">BERKAS</th>
                        <th class="py-3.5 px-2 xl:px-3 text-center">STATUS</th>
                        <th class="py-3.5 px-2 xl:px-3 text-center w-16">AKSI</th>
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
                        <!-- CID (Klik untuk Atur Mini POP / Jaringan) -->
                        <td class="py-3.5 pl-4 px-3 font-mono font-semibold">
                            @if(auth()->user()->hasPermission('customers.detail.installation.validate'))
                            <button type="button" onclick="openNetworkAssignmentModal('{{ route('customers.network-assignment.update', $customer->id) }}', '{{ route('customers.network-assignment.data', $customer->id) }}')"
                                    class="text-sky-600 dark:text-sky-400 hover:text-sky-700 hover:underline flex items-center gap-1 text-left group-hover:scale-[1.01] transition-transform"
                                    title="Klik untuk Atur Mini POP & Distribusi">
                                <span>{{ $displayId }}</span>
                                <svg class="w-3.5 h-3.5 opacity-0 group-hover:opacity-100 text-sky-500 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/></svg>
                            </button>
                            @else
                            <span class="text-sky-600 dark:text-sky-400 font-mono">{{ $displayId }}</span>
                            @endif
                        </td>

                        <td class="py-3.5 px-3">
                            <span class="font-bold text-slate-900 dark:text-white group-hover:text-sky-600 transition-colors">
                                {{ $customer->full_name }}
                            </span>
                            @if($customer->collector)
                                <div class="mt-0.5">
                                    <span class="inline-flex items-center px-1.5 py-0.5 text-[9px] font-bold rounded border bg-violet-50 dark:bg-violet-500/10 text-violet-700 dark:text-violet-400 border-violet-100 dark:border-violet-500/20">
                                        Kolektor: {{ $customer->collector->name }}
                                    </span>
                                </div>
                            @endif
                        </td>

                        <td class="py-3.5 px-2 xl:px-3 whitespace-nowrap max-w-[130px] xl:max-w-[200px] truncate">
                            <div class="flex items-center gap-1.5">
                                <span class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-semibold text-[11px] shrink-0">
                                    {{ $customer->pop->name ?? '-' }}
                                </span>
                                <span class="text-slate-400 shrink-0">·</span>
                                <span class="text-slate-500 dark:text-slate-400 truncate">
                                    {{ $customer->village->name ?? ($customer->customerAddress->village ?? '-') }}
                                </span>
                            </div>
                        </td>

                        <td class="py-3.5 px-2 xl:px-3 font-mono text-[11px] text-slate-600 dark:text-slate-400 max-w-[120px] xl:max-w-[180px] truncate">
                            {{ $customer->internetPackage ? $customer->internetPackage->package_code . ' - ' . $customer->internetPackage->name : '-' }}
                        </td>

                        <td class="py-3.5 px-2 xl:px-3 font-mono hidden 2xl:table-cell">
                            @if($cleanPhone)
                                <div class="inline-flex items-center gap-1.5">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="1.1em" height="1.1em" fill="currentColor" class="text-emerald-500 shrink-0">
                                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347M12.05 21.785h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884a9.82 9.82 0 0 1 6.988 2.896 9.83 9.83 0 0 1 2.893 6.994c-.003 5.45-4.437 9.886-9.885 9.886m8.413-18.297A11.8 11.8 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.9 11.9 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.82 11.82 0 0 0-3.48-8.413"/>
                                    </svg>
                                    <span>{{ $customer->primary_phone }}</span>
                                </div>
                            @else
                                <span class="text-slate-400 italic">-</span>
                            @endif
                        </td>

                        <td class="py-3.5 px-2 xl:px-3 font-mono text-[11px] hidden 2xl:table-cell">
                            @if($customer->latestInvoice)
                                @php
                                    $isOverdue = $customer->latestInvoice->due_date && $customer->latestInvoice->due_date->isPast() && $customer->latestInvoice->invoice_status !== \App\Enums\InvoiceStatus::LUNAS;
                                @endphp
                                <span class="{{ $isOverdue ? 'text-rose-600 dark:text-rose-400 font-bold' : 'text-slate-600 dark:text-slate-400' }}">
                                    {{ \App\Support\IndonesianDate::date($customer->latestInvoice->due_date) }}
                                </span>
                            @else
                                <span class="text-slate-400">-</span>
                            @endif
                        </td>

                        <td class="py-3.5 px-2 xl:px-3 text-right font-mono">
                            @if($customer->latestInvoice)
                                @php
                                    $isPaid = $customer->latestInvoice->invoice_status === \App\Enums\InvoiceStatus::LUNAS;
                                    $isOverdue = !$isPaid && $customer->latestInvoice->due_date && $customer->latestInvoice->due_date->isPast();
                                @endphp
                                <span class="font-bold {{ $isPaid ? 'text-emerald-600 dark:text-emerald-400' : ($isOverdue ? 'text-rose-600 dark:text-rose-400' : 'text-slate-900 dark:text-white') }}">
                                    Rp {{ number_format($customer->latestInvoice->total_amount, 0, ',', '.') }}
                                </span>
                                <span class="block text-[10px] font-sans font-semibold {{ $isPaid ? 'text-emerald-500' : ($isOverdue ? 'text-rose-500' : 'text-slate-400') }}">
                                    {{ $isPaid ? 'Lunas' : ($isOverdue ? 'Lewat Tempo' : 'Belum Bayar') }}
                                </span>
                            @else
                                <span class="text-slate-400">-</span>
                            @endif
                        </td>

                        <td class="py-3.5 px-2 xl:px-3 text-center font-mono hidden 2xl:table-cell">
                            <span class="px-2 py-0.5 rounded-full font-bold text-[11px] {{ $completeness['percentage'] >= 80 ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400' : 'bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400' }}">
                                {{ $completeness['percentage'] }}%
                            </span>
                        </td>

                        <!-- Status Badge -->
                        <td class="py-3.5 px-3 text-center">
                            @php
                                $statusLabel = $customer->subscriptionStatus->name ?? ucfirst($customer->status);
                                $isSuspended = $customer->status === 'suspended';
                                $isActive = $customer->status === 'active';
                            @endphp
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold border
                                  {{ $isActive ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800' : '' }}
                                  {{ $isSuspended ? 'bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800' : '' }}
                                  {{ !$isActive && !$isSuspended ? 'bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800' : '' }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $isActive ? 'bg-emerald-500 animate-pulse-glow' : ($isSuspended ? 'bg-amber-500' : 'bg-rose-500') }} mr-1.5"></span>
                                <span>{{ $statusLabel }}</span>
                            </span>
                        </td>

                        <!-- Aksi Button -->
                        <td class="py-3.5 px-3 text-center">
                            <button type="button"
                                    onclick="openActionsModal(this)"
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
                                    data-pppoe="{{ $customer->customerService->pppoe_username ?? '-' }}"
                                    data-vlan="{{ $customer->customerService->vlan_id ?? '-' }}"
                                    data-onu="{{ $customer->customerDevice->onu_sn ?? ($customer->customerDevice->mac_address ?? '-') }}"
                                    data-onu-brand="{{ $customer->customerDevice->onu_brand ?? '-' }}"
                                    data-router="{{ $customer->customerDevice->router_sn ?? '-' }}"
                                    data-router-brand="{{ $customer->customerDevice->router_brand ?? '-' }}"
                                    data-contract="{{ match($customer->customerService->contract_type ?? null) { 'sewa' => 'Sewa', 'beli' => 'Beli', default => '-' } }}"
                                    data-distribution="{{ $customer->distribution->name ?? '-' }}"
                                    data-detail-url="{{ route('customers.show', $customer->id) }}"
                                    data-payment-info-url="{{ route('customers.payment-info', $customer->id) }}"
                                    data-network-update-url="{{ route('customers.network-assignment.update', $customer->id) }}"
                                    data-network-data-url="{{ route('customers.network-assignment.data', $customer->id) }}"
                                    class="w-9 h-9 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-sky-50 dark:hover:bg-slate-700 hover:border-sky-300 text-slate-500 hover:text-sky-600 inline-flex items-center justify-center transition-all shadow-sm cursor-pointer"
                                    title="Buka Modal Hub Aksi Cepat">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/></svg>
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="px-6 py-8 text-center text-slate-400">Tidak ada data pelanggan yang cocok.</td>
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
                @endphp
                <div class="p-4 rounded-2xl border border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm hover:border-sky-300 dark:hover:border-sky-800 transition-all card-interactive flex flex-col justify-between space-y-3">
                    <div class="space-y-2">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                @if(auth()->user()->hasPermission('customers.detail.installation.validate'))
                                <button type="button" onclick="openNetworkAssignmentModal('{{ route('customers.network-assignment.update', $customer->id) }}', '{{ route('customers.network-assignment.data', $customer->id) }}')"
                                        class="font-mono text-xs font-bold text-sky-600 dark:text-sky-400 flex items-center gap-1 hover:underline text-left">
                                    <span>{{ $displayId }}</span>
                                    <svg class="w-3 h-3 text-sky-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/></svg>
                                </button>
                                @else
                                <span class="font-mono text-xs font-bold text-sky-600 dark:text-sky-400">{{ $displayId }}</span>
                                @endif
                                <h4 class="font-bold text-slate-900 dark:text-white text-base mt-0.5">{{ $customer->full_name }}</h4>
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
                                <span class="w-1.5 h-1.5 rounded-full {{ $isActive ? 'bg-emerald-500 animate-pulse-glow' : ($isSuspended ? 'bg-amber-500' : 'bg-rose-500') }} mr-1"></span>
                                <span>{{ $customer->subscriptionStatus->name ?? ucfirst($customer->status) }}</span>
                            </span>
                        </div>

                        <div class="grid grid-cols-2 gap-2 text-xs bg-slate-50 dark:bg-slate-800/50 p-2.5 rounded-xl border border-slate-100 dark:border-slate-800">
                            <div>
                                <span class="text-slate-400 text-[10px] block">POP / Desa</span>
                                <span class="font-medium text-slate-700 dark:text-slate-200">{{ $customer->pop->name ?? '-' }} · {{ $customer->village->name ?? ($customer->customerAddress->village ?? '-') }}</span>
                            </div>
                            <div>
                                <span class="text-slate-400 text-[10px] block">Tagihan</span>
                                <span class="font-mono font-semibold text-emerald-600 dark:text-emerald-400">
                                    Rp {{ number_format($customer->latestInvoice->total_amount ?? 0, 0, ',', '.') }}
                                </span>
                                <span class="text-[9px] font-bold block {{ $isPaid ? 'text-emerald-500' : 'text-rose-500' }}">
                                    ● {{ $isPaid ? 'LUNAS' : 'BELUM BAYAR' }}
                                </span>
                            </div>
                            <div class="col-span-2">
                                <span class="text-slate-400 text-[10px] block">Paket Internet</span>
                                <span class="font-mono text-slate-600 dark:text-slate-300">{{ $customer->internetPackage ? $customer->internetPackage->package_code . ' - ' . $customer->internetPackage->name : '-' }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                        @if($customer->latestPayment)
                            <a href="{{ route('payments.receipt', $customer->latestPayment->id) }}"
                               target="_blank"
                               title="Cetak Struk / Kwitansi Pembayaran Terakhir"
                               class="px-2.5 py-1.5 rounded-xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/40 text-rose-700 dark:text-rose-300 text-xs font-semibold inline-flex items-center gap-1 hover:bg-rose-100 shrink-0 touch-target btn-interactive">
                                <svg class="w-4 h-4 text-rose-600 dark:text-rose-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                                <span>Struk</span>
                            </a>
                        @else
                            <button type="button"
                                    onclick="showModalToast('Belum ada pembayaran/kwitansi untuk pelanggan ini.')"
                                    title="Belum ada pembayaran yang bisa dicetak"
                                    class="px-2.5 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-400 text-xs font-semibold inline-flex items-center gap-1 opacity-60 cursor-not-allowed shrink-0">
                                <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v4a2 2 0 002 2z"/></svg>
                                <span>Invoice</span>
                            </button>
                        @endif

                        <button type="button"
                                onclick="openActionsModal(this)"
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
                                data-pppoe="{{ $customer->customerService->pppoe_username ?? '-' }}"
                                data-vlan="{{ $customer->customerService->vlan_id ?? '-' }}"
                                data-onu="{{ $customer->customerDevice->onu_sn ?? ($customer->customerDevice->mac_address ?? '-') }}"
                                data-onu-brand="{{ $customer->customerDevice->onu_brand ?? '-' }}"
                                data-router="{{ $customer->customerDevice->router_sn ?? '-' }}"
                                data-router-brand="{{ $customer->customerDevice->router_brand ?? '-' }}"
                                data-contract="{{ match($customer->customerService->contract_type ?? null) { 'sewa' => 'Sewa', 'beli' => 'Beli', default => '-' } }}"
                                data-distribution="{{ $customer->distribution->name ?? '-' }}"
                                    data-detail-url="{{ route('customers.show', $customer->id) }}"
                                    data-payment-info-url="{{ route('customers.payment-info', $customer->id) }}"
                                    data-network-update-url="{{ route('customers.network-assignment.update', $customer->id) }}"
                                    data-network-data-url="{{ route('customers.network-assignment.data', $customer->id) }}"
                                class="flex-1 py-1.5 px-2.5 rounded-xl bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold flex items-center justify-center gap-1 shadow-md shadow-sky-600/20 transition-all btn-interactive touch-target min-w-0">
                            <span class="truncate">Quick Hub</span>
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </button>
                    </div>
                </div>
                @empty
                <div class="p-8 text-center text-slate-400 text-xs">Tidak ada data pelanggan yang cocok.</div>
                @endforelse
            </div>
        </div>
