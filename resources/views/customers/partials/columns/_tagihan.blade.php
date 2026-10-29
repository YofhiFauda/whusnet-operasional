                        <td data-col="tagihan" class="py-3.5 px-2 xl:px-3 text-right font-mono">
                            @if($customer->latestInvoice)
                                @php
                                    $isPaid = $customer->latestInvoice->invoice_status === \App\Enums\InvoiceStatus::LUNAS;
                                    $isOverdue = !$isPaid && $customer->latestInvoice->isPiutang();
                                @endphp
                                <span class="font-bold {{ $isPaid ? 'text-emerald-600 dark:text-emerald-400' : ($isOverdue ? 'text-rose-600 dark:text-rose-400' : 'text-amber-600 dark:text-amber-400') }}">
                                    Rp {{ number_format($customer->latestInvoice->total_amount, 0, ',', '.') }}
                                </span>
                                <span class="block text-[10px] font-sans font-semibold {{ $isPaid ? 'text-emerald-500' : ($isOverdue ? 'text-rose-500' : 'text-amber-500') }}">
                                    {{ $isPaid ? 'Lunas' : ($isOverdue ? 'Piutang' : 'Belum Bayar') }}
                                </span>
                            @else
                                <span class="text-slate-400">-</span>
                            @endif
                        </td>
