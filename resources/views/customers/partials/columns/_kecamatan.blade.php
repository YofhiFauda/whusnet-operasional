<td data-col="kecamatan" class="py-3.5 px-2 xl:px-3 whitespace-nowrap">
    {{ $customer->district->name ?? ($customer->customerAddress->district ?? "-") }}
</td>
