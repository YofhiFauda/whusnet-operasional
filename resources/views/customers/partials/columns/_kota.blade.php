<td data-col="kota" class="py-3.5 px-2 xl:px-3 whitespace-nowrap">
    {{ $customer->city->name ?? ($customer->customerAddress->city ?? "-") }}
</td>
