<?php

namespace App\Http\Controllers\CustomerPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\CustomerPortal\PortalUpdatePasswordRequest;
use App\Http\Resources\CustomerPortal\CustomerPortalProfileResource;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerPortalToken;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * `GET /me` + `PUT /me/password` (docs/api/api-portal-pelanggan/, Fase 2).
 * Semua route SUDAH di belakang middleware `portal_client` + `portal_token`
 * — `customer_id` HANYA datang dari token, tidak pernah dari request
 * (business-logic.md §Kepemilikan data).
 */
#[Group('Portal Pelanggan', 'Endpoint bagi aplikasi Portal Pelanggan (domain terpisah, tanpa kredensial DB operasional) untuk kredensial, tagihan, pembayaran, kwitansi, saldo, dan riwayat ticketing pelanggan. Semua permintaan WAJIB header `X-Portal-Client` (client secret statis); endpoint `/me/*` tambah `Authorization: Bearer <access_token>`.')]
class PortalMeController extends Controller
{
    /**
     * Profil ringkas
     *
     * Nama, status layanan, paket aktif, alamat generic (desa/kecamatan —
     * bukan alamat detail/koordinat).
     */
    #[Response(200, description: 'Profil berhasil diambil.', examples: [[
        'data' => [
            'login_id' => 'PNG-RQ000631',
            'full_name' => 'Budi Santoso',
            'status' => 'active',
            'package' => 'Home 20 Mbps',
            'village' => 'Joresan',
            'district' => 'Mlarak',
            'claimed_at' => '2026-08-01T10:00:00+07:00',
        ],
        'meta' => ['generated_at' => '2026-08-25T09:00:00+07:00'],
    ]])]
    #[Response(401, description: 'Access token tidak disertakan atau tidak valid.')]
    public function show(Request $request): CustomerPortalProfileResource
    {
        $customer = Customer::with(['portalAccount', 'internetPackage', 'village', 'district'])
            ->findOrFail($request->attributes->get('portal_customer_id'));

        return new CustomerPortalProfileResource($customer);
    }

    /**
     * Ganti password
     *
     * `current_password` wajib. Sukses mencabut semua token LAIN (sesi
     * pemanggil tetap hidup).
     */
    #[BodyParameter('current_password', description: 'Password saat ini — wajib, sesi yang dicuri tidak cukup untuk mengambil alih akun permanen.', example: 'Kuda-Nil-Rajin-88')]
    #[BodyParameter('new_password', description: 'Password baru, minimal 10 karakter, tidak boleh mengandung login_id/nomor HP, tidak boleh dari daftar password umum.', example: 'Layang-Layang-Sore-42')]
    #[Response(200, description: 'Password berhasil diganti.', examples: [['message' => 'Password berhasil diganti.']])]
    #[Response(401, description: 'Access token tidak disertakan atau tidak valid.')]
    #[Response(422, description: 'Password saat ini salah, atau new_password tidak memenuhi syarat (< 10 karakter, mengandung login_id/nomor HP, atau daftar password umum).')]
    #[Response(429, description: 'Jumlah percobaan melebihi batas (limiter sama seperti login).')]
    public function updatePassword(PortalUpdatePasswordRequest $request): JsonResponse
    {
        $customerId = (int) $request->attributes->get('portal_customer_id');
        $customer = Customer::with('portalAccount')->findOrFail($customerId);
        $account = $customer->portalAccount;

        if (! $account || ! Hash::check($request->string('current_password'), $account->password_hash)) {
            return response()->json(['message' => 'Password saat ini salah.'], 422);
        }

        $account->forceFill([
            'password_hash' => Hash::make($request->string('new_password')),
            'password_changed_at' => now(),
        ])->save();

        // Sesi PEMANGGIL tetap hidup, sisanya dicabut — beda dari logout
        // yang cabut semua tanpa kecuali (business-logic.md §1: "SEMUA token
        // pelanggan itu dicabut KECUALI sesi yang sedang dipakai").
        $currentTokenId = $request->attributes->get('portal_token')?->id;
        CustomerPortalToken::revokeAllForCustomer($customerId, exceptTokenId: $currentTokenId);

        // Audit MANUAL (bukan trait — trait sengaja tidak dipasang di
        // CustomerPortalAccount). Mencatat siapa/kapan/IP, TIDAK PERNAH
        // passwordnya.
        AuditLog::create([
            'user_id' => null,
            'module' => 'Portal Pelanggan',
            'action' => 'password_changed',
            'auditable_type' => $account::class,
            'auditable_id' => $account->id,
            'old_values' => null,
            'new_values' => ['changed_at' => now()->toIso8601String()],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        return response()->json(['message' => 'Password berhasil diganti.']);
    }
}
