                        <td data-col="nama" class="py-3.5 px-3">
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
