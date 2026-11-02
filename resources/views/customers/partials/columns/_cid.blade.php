                        <td data-col="cid" class="py-3.5 pl-4 px-3 font-mono font-semibold">
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
