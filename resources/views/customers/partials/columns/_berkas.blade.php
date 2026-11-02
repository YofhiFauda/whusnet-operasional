                        <td data-col="berkas" class="py-3.5 px-2 xl:px-3 text-center font-mono">
                            <span class="px-2 py-0.5 rounded-full font-bold text-[11px] {{ $completeness['percentage'] >= 80 ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400' : 'bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400' }}">
                                {{ $completeness['percentage'] }}%
                            </span>
                        </td>
