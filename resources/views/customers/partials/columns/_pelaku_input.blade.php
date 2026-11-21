<td data-col="pelaku_input" class="py-3.5 px-2 xl:px-3 whitespace-nowrap">
    {{ $customer->registered_by_name ?? $customer->creator?->name ?? "-" }}
</td>
