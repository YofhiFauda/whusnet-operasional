<?php

namespace App\Http\Controllers;

use App\Enums\NotificationType;
use App\Enums\WorkflowTransition;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerAcquisition;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Services\CustomerVerificationDetailService;
use App\Services\EffectiveAccessService;
use App\Services\InitialInvoiceService;
use App\Services\InstallationFeeInvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Antrean "Menunggu Verifikasi BD" — pelanggan kategori Bisnis
 * (`package_categories.installation_fee_approval_role_id` terisi) yang
 * sudah lolos verifikasi CS (`CustomerVerificationController::finalVerify()`,
 * data teknis sudah kelar, prorata sudah dihitung) tapi BELUM resmi ACTIVE
 * DAN Invoice Awal-nya BELUM terbit — nyangkut di
 * `WorkflowTransition::WAITING_BUSINESS_DEVELOPMENT_VERIFICATION` sampai
 * Business Development (BD) mengisi "Biaya Instalasi" & menekan
 * "Verifikasi & Aktifkan" di sini.
 *
 * SATU aksi, TIGA efek sekaligus (dikonfirmasi user, TANPA jalur tolak):
 *   1. Menerbitkan Invoice AWAL yang DITUNDA CS (`finalVerify()` cuma
 *      menitipkan snapshot hitungannya ke `customers.pending_initial_invoice`
 *      — lihat `InitialInvoiceService::issue()`). Tagihan pertama pelanggan
 *      Bisnis baru sah terbit di titik INI, bukan saat CS verifikasi
 *      (ditandai user sebagai bug 2026-09-14: sebelumnya terbit lebih awal
 *      walau BD belum menyetujui).
 *   2. Menerbitkan tagihan Biaya Instalasi (`InstallationFeeInvoiceService`
 *      — SAMA persis dipakai `CustomerAcquisitionController::
 *      updateInstallationFee()`, jangan duplikasi logikanya). Invoice
 *      TERPISAH dari Invoice Awal di atas — jangan digabung.
 *   3. Transisi status pelanggan ke ACTIVE — `CustomerObserver` otomatis
 *      membuat baris `CustomerAcquisition` (modul Busdev lama,
 *      `/customer-acquisitions`) tepat di titik ini, lalu baris itu
 *      LANGSUNG diisi nominal & invoice-nya di request yang sama (tidak
 *      pernah kosong/"Menunggu Validasi" di modul lama buat pelanggan yang
 *      lewat jalur ini).
 */
class BusinessDevelopmentVerificationController extends Controller
{
    public function index(Request $request): View
    {
        $access = app(EffectiveAccessService::class);
        $user = $request->user();

        $customers = Customer::query()
            ->with(['customerService.internetPackage', 'pop'])
            ->where('status', WorkflowTransition::WAITING_BUSINESS_DEVELOPMENT_VERIFICATION->value)
            ->when(! $access->hasAllPopAccess($user), function ($query) use ($access, $user) {
                $query->whereIn('pop_id', $access->getAllowedPopIds($user));
            })
            ->orderBy('updated_at')
            ->get();

        return view('business-development-verifications.index', compact('customers'));
    }

    public function show(Request $request, Customer $customer): View
    {
        $customer->loadMissing('customerService.internetPackage', 'pop');

        abort_unless($customer->status === WorkflowTransition::WAITING_BUSINESS_DEVELOPMENT_VERIFICATION->value, 404, 'Pelanggan ini tidak sedang menunggu verifikasi BD.');
        abort_unless($customer->canInstallationFeeBeValidatedBy($request->user()), 403, 'Anda tidak punya izin memverifikasi pelanggan kategori paket ini.');

        $access = app(EffectiveAccessService::class);
        $user = $request->user();
        if (! $access->hasAllPopAccess($user) && ! in_array($customer->pop_id, $access->getAllowedPopIds($user), true)) {
            abort(403, 'Pelanggan ini di luar POP scope Anda.');
        }

        // BUKAN halaman terpisah — reuse view `verifications.admin` yang
        // sama persis dipakai CS (`CustomerVerificationController::
        // showAdmin()`, tab Registrasi/Survey/Pemasangan/Pengujian
        // identik), cuma tab "Verifikasi"-nya sendiri yang cabang beda isi
        // (lihat `$isWaitingBdStage` di view) — diminta eksplisit user
        // biar BD bisa ninjau data teknis lengkap sebelum isi Biaya
        // Instalasi, bukan cuma form kosong.
        $detail = app(CustomerVerificationDetailService::class)->load($customer);

        return view('verifications.admin', array_merge(['customer' => $customer], $detail));
    }

    public function verify(Request $request, Customer $customer, InstallationFeeInvoiceService $installationFeeInvoiceService, InitialInvoiceService $initialInvoiceService): RedirectResponse
    {
        $customer->loadMissing('customerService.internetPackage', 'pop');

        abort_unless($customer->status === WorkflowTransition::WAITING_BUSINESS_DEVELOPMENT_VERIFICATION->value, 404, 'Pelanggan ini tidak sedang menunggu verifikasi BD.');
        abort_unless($customer->canInstallationFeeBeValidatedBy($request->user()), 403, 'Anda tidak punya izin memverifikasi pelanggan kategori paket ini.');

        $access = app(EffectiveAccessService::class);
        $user = $request->user();
        if (! $access->hasAllPopAccess($user) && ! in_array($customer->pop_id, $access->getAllowedPopIds($user), true)) {
            abort(403, 'Pelanggan ini di luar POP scope Anda.');
        }

        // Snapshot dititipkan CS di `finalVerify()` — kalau kosong berarti
        // data cacat (mis. dimanipulasi lewat tinker/migrasi), jangan
        // lanjut menerbitkan invoice dengan angka kosong/nol.
        abort_if(! $customer->pending_initial_invoice, 422, 'Data tagihan awal dari CS tidak ditemukan — hubungi CS untuk verifikasi ulang.');

        $validated = $request->validate([
            'installation_fee' => ['required', 'numeric', 'min:0.01'],
        ]);

        DB::transaction(function () use ($customer, $validated, $installationFeeInvoiceService, $initialInvoiceService) {
            $pending = $customer->pending_initial_invoice;

            // 1. Invoice AWAL yang ditunda CS — terbit BARU di titik ini,
            // pakai angka PERSIS yang sudah dikonfirmasi CS ke pelanggan
            // (bukan dihitung ulang, lihat docblock kelas ini).
            $initialInvoice = $initialInvoiceService->issue(
                $customer,
                $customer->customerService,
                $pending['billing'],
                $pending['issue_date'],
                auth()->id()
            );

            // 2. Invoice Biaya Instalasi — TERPISAH dari Invoice Awal di atas.
            $installationInvoice = $installationFeeInvoiceService->issue($customer, (float) $validated['installation_fee']);

            $oldStatus = $customer->status;
            $customer->update([
                'status' => WorkflowTransition::ACTIVE->value,
                'pending_initial_invoice' => null,
            ]);

            // CustomerObserver::updated() barusan membuat baris
            // CustomerAcquisition (status berubah ke ACTIVE) — diisi
            // langsung di sini biar gak pernah nongol "Menunggu Validasi"
            // buat pelanggan yang lewat jalur gate ini.
            CustomerAcquisition::where('customer_id', $customer->id)
                ->latest()
                ->first()
                ?->update([
                    'installation_fee' => $validated['installation_fee'],
                    'installation_fee_invoice_id' => $installationInvoice->id,
                ]);

            AuditLog::create([
                'user_id' => auth()->id(),
                'module' => 'Data Pelanggan',
                'action' => 'activate_from_business_development_verification',
                'auditable_type' => Customer::class,
                'auditable_id' => $customer->id,
                'old_values' => ['status' => $oldStatus],
                'new_values' => [
                    'status' => WorkflowTransition::ACTIVE->value,
                    'installation_fee' => $validated['installation_fee'],
                    'initial_invoice_id' => $initialInvoice->id,
                ],
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
            ]);

            $creator = $customer->creator ?? ($customer->created_by ? User::find($customer->created_by) : null);
            if ($creator && $creator->id !== auth()->id()) {
                $creator->notify(new AppNotification(
                    title: 'Pelanggan Aktif: '.$customer->full_name,
                    message: "Pelanggan {$customer->full_name} resmi aktif — tagihan awal ({$initialInvoice->invoice_number}) & Biaya Instalasi ({$installationInvoice->invoice_number}) sudah terbit.",
                    actionUrl: route('customers.show', $customer->id),
                    type: NotificationType::SUCCESS
                ));
            }
        });

        return redirect()
            ->route('business-development-verifications.index')
            ->with('success', "Pelanggan {$customer->full_name} berhasil diverifikasi, tagihan awal & biaya instalasi terbit, pelanggan resmi aktif.");
    }
}
