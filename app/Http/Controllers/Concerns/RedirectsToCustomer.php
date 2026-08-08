<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Customer;
use Illuminate\Http\RedirectResponse;

/**
 * Redirect ke Detail Pelanggan (`customers.show`) SETELAH aksi tulis sukses —
 * TAPI cuma kalau actor beneran punya akses lihatnya (`customers.detail.view`).
 *
 * Banyak aksi customer digerbangi permission granular yang INDEPENDEN dari
 * `customers.detail.view`: `customers.create` (mis. Sales input-only),
 * `customers.update`, `customers.detail.documents.upload`,
 * `customers.detail.installation.activate`, dst. Kombinasi "boleh nulis tapi
 * gak boleh lihat Detail" valid secara RBAC (diatur lewat Role Matrix), tapi
 * redirect buta ke `customers.show` bikin actor itu ke-403 PADAHAL aksinya
 * sendiri sukses (data sudah tersimpan) — dead end yang membingungkan, bukan
 * cuma UX jelek.
 *
 * `$fallbackRoute` dipakai kalau actor gak punya `customers.detail.view`.
 * Default `null` = `redirect()->back()` — aman secara universal karena actor
 * pasti baru saja berada di halaman GET terakhir (form/list) sebelum POST ini,
 * jadi otomatis halaman yang dia memang punya akses.
 */
trait RedirectsToCustomer
{
    protected function redirectToCustomer(Customer $customer, ?string $fallbackRoute = null): RedirectResponse
    {
        if (auth()->user()->hasPermission('customers.detail.view')) {
            return redirect()->route('customers.show', $customer->id);
        }

        return $fallbackRoute ? redirect()->route($fallbackRoute) : redirect()->back();
    }
}
