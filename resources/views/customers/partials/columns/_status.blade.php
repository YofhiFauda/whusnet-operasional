                        <td data-col="status" class="py-3.5 px-3 text-center">
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
