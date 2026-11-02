                        <td data-col="paket" class="py-3.5 px-2 xl:px-3 font-mono text-[11px] text-slate-600 dark:text-slate-400 max-w-[120px] xl:max-w-[180px] truncate">
                            {{ $customer->internetPackage ? $customer->internetPackage->package_code . ' - ' . $customer->internetPackage->name : '-' }}
                            <x-warehouse.acquisition-badge :device="$customer->customerDevice" class="block mt-1 w-fit" />
                        </td>
