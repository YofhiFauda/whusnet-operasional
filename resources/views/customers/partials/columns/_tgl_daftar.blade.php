<td data-col="tgl_daftar" class="py-3.5 px-2 xl:px-3 whitespace-nowrap font-mono text-[11px]">
    {{ $customer->registration_date ? \App\Support\IndonesianDate::date($customer->registration_date) : "-" }}
</td>
