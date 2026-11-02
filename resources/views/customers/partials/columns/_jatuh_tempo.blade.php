                        <td data-col="jatuh_tempo" class="py-3.5 px-2 xl:px-3 font-mono text-[11px]">
                            @if($customer->latestInvoice)
                                @php
                                    $isOverdue = $customer->latestInvoice->isPiutang();
                                @endphp
                                <span class="{{ $isOverdue ? 'text-rose-600 dark:text-rose-400 font-bold' : 'text-slate-600 dark:text-slate-400' }}">
                                    {{ \App\Support\IndonesianDate::date($customer->latestInvoice->due_date) }}
                                </span>
                            @else
                                <span class="text-slate-400">-</span>
                            @endif
                        </td>
