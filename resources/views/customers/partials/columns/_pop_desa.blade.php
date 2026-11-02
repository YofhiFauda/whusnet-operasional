                        <td data-col="pop_desa" class="py-3.5 px-2 xl:px-3 whitespace-nowrap max-w-[130px] xl:max-w-[200px] truncate">
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
