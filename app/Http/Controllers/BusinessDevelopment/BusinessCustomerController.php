<?php

namespace App\Http\Controllers\BusinessDevelopment;

use App\Enums\SerialStatus;
use App\Enums\WorkflowTransition;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\PackageCategory;
use App\Models\SubscriptionStatus;
use App\Services\EffectiveAccessService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * List Pelanggan Bisnis (Business Development) — daftar pelanggan yang
 * paketnya masuk kategori Bisnis, lengkap dengan harga, alat yang
 * ditinggalkan, biaya instalasi & tanggal aktivasi.
 *
 * MURNI TURUNAN data sistem, tanpa tabel/kolom baru & tanpa aksi tulis
 * (dikonfirmasi user, menggantikan mockup "Tambah Pelanggan" — pelanggan
 * masuk lewat alur registrasi biasa, bukan diketik ulang di sini supaya
 * tidak ada dua sumber kebenaran untuk nama/harga pelanggan).
 *
 *  - "Kategori Bisnis" = kategori paket yang diberi role validator Biaya
 *    Instalasi di Master Kategori Paket (`installation_fee_approval_role_id`
 *    terisi) — definisi SAMA dengan yang dipakai antrean Verifikasi BD &
 *    `/customer-acquisitions`, jadi tiga halaman tidak bisa beda tafsir
 *    "apa itu pelanggan Bisnis". Bukan `customers.customer_type`: dua field
 *    itu bisa gak sinkron, yang menentukan biaya nyata adalah paketnya.
 *  - Harga Paket = harga bulanan setelah diskon, SEBELUM PPN. Harga Sesudah
 *    PPN = `total_monthly_bill` (sudah memakai `ppn` % per pelanggan), bukan
 *    PPN 11% hardcode Busdev — pelanggan bisa saja ber-PPN 0%.
 *  - Alat = unit SN gudang berstatus INSTALLED di pelanggan (traceability
 *    gudang), dikelompokkan per nama barang.
 */
class BusinessCustomerController extends Controller
{
    /**
     * Status yang relevan buat daftar ini — pelanggan yang sudah melewati
     * pemasangan. Tahap registrasi/survey sengaja disembunyikan: belum ada
     * tanggal aktivasi, belum ada alat, cuma bikin daftar berisik.
     *
     * @return list<WorkflowTransition>
     */
    private function visibleStatuses(): array
    {
        return [
            WorkflowTransition::WAITING_BUSINESS_DEVELOPMENT_VERIFICATION,
            WorkflowTransition::ACTIVE,
            WorkflowTransition::SUSPENDED,
            WorkflowTransition::TERMINATED,
        ];
    }

    public function index(Request $request): View
    {
        $access = app(EffectiveAccessService::class);
        $user = $request->user();

        $search = trim((string) $request->query('q', ''));
        $category = $request->query('category');
        $status = $request->query('status');

        $businessCategories = PackageCategory::query()
            ->whereNotNull('installation_fee_approval_role_id')
            ->ordered()
            ->pluck('name');

        $visibleStatusValues = array_map(fn (WorkflowTransition $s) => $s->value, $this->visibleStatuses());
        $statusNames = SubscriptionStatus::whereIn('code', $visibleStatusValues)->pluck('name', 'code');
        $statusOptions = collect($visibleStatusValues)->mapWithKeys(
            fn (string $code) => [$code => $statusNames[$code] ?? ucwords(str_replace('_', ' ', $code))]
        );

        $customers = Customer::query()
            ->with([
                'customerService.internetPackage',
                'customerAcquisition',
                'pop',
                'subscriptionStatus',
                // Cuma unit yang benar-benar terpasang — SN yang masih di
                // gudang/custody teknisi bukan "alat yang ditinggalkan".
                'inventorySerials' => fn ($q) => $q->where('status', SerialStatus::INSTALLED->value),
                'inventorySerials.item',
            ])
            // Wajib lewat POP scope (CLAUDE.md) — role ber-scope cuma boleh
            // lihat pelanggan di POP yang diizinkan.
            ->when(! $access->hasAllPopAccess($user), function ($query) use ($access, $user) {
                $query->whereIn('pop_id', $access->getAllowedPopIds($user));
            })
            ->whereIn('status', $status && in_array($status, $visibleStatusValues, true) ? [$status] : $visibleStatusValues)
            ->whereHas('customerService.internetPackage', function ($q) use ($businessCategories, $category) {
                $q->whereIn('category', $category && $businessCategories->contains($category) ? [$category] : $businessCategories->all());
            })
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.$search.'%';
                $query->where(function ($q) use ($like) {
                    $q->where('full_name', 'like', $like)
                        // `display_id` cuma accessor (bukan kolom) — yang dicari
                        // dua kolom sumbernya: kode registrasi & CID.
                        ->orWhere('customer_code', 'like', $like)
                        ->orWhere('cid', 'like', $like)
                        ->orWhereHas('inventorySerials.item', fn ($iq) => $iq->where('name', 'like', $like));
                });
            })
            ->orderByRaw('(select activation_date from customer_services where customer_services.customer_id = customers.id) desc')
            ->orderBy('full_name')
            ->paginate(25)
            ->withQueryString();

        return view('business-development.business-customers.index', [
            'customers' => $customers,
            'businessCategories' => $businessCategories,
            'statusOptions' => $statusOptions,
            'search' => $search,
            'category' => $category,
            'status' => $status,
        ]);
    }
}
