<?php

namespace App\Http\Controllers\CustomerPortal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\CustomerPortal\Concerns\ScopedToAuthenticatedCustomer;
use App\Http\Resources\CustomerPortal\CustomerBalanceMutationResource;
use App\Services\CustomerBalanceService;
use App\Support\Money;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `GET /me/balance` (docs/api/api-portal-pelanggan/, Fase 3, dikonfirmasi
 * user 2026-08-25: saldo + riwayat mutasi ringkas).
 *
 * Bukan `ApiResource` tunggal — respons gabungan scalar (`balance`) + list
 * (`mutations`), sedangkan `ApiResource::with()` didesain untuk satu
 * resource/collection homogen. Envelope {data, meta} dirakit manual di sini,
 * tetap reuse Money::decimalString() (bukan format ulang sendiri).
 */
#[Group('Portal Pelanggan', 'Endpoint bagi aplikasi Portal Pelanggan (domain terpisah, tanpa kredensial DB operasional) untuk kredensial, tagihan, pembayaran, kwitansi, saldo, dan riwayat ticketing pelanggan. Semua permintaan WAJIB header `X-Portal-Client` (client secret statis); endpoint `/me/*` tambah `Authorization: Bearer <access_token>`.')]
class PortalBalanceController extends Controller
{
    use ScopedToAuthenticatedCustomer;

    /**
     * Saldo & riwayat mutasi
     *
     * Saldo lebih-bayar pelanggan (dari `customer_balance_mutations`) +
     * riwayat mutasi ringkas, dipaginasi.
     */
    #[Response(200, description: 'Saldo berhasil diambil.', examples: [[
        'data' => [
            'balance' => '50000.00',
            'mutations' => [[
                'date' => '2026-08-20T10:00:00+07:00',
                'type' => 'credit',
                'type_label' => 'Masuk',
                'amount' => '50000.00',
                'note' => 'Lebih bayar dari PAY-202608-0042',
            ]],
        ],
        'meta' => ['generated_at' => '2026-08-25T09:00:00+07:00'],
    ]])]
    #[Response(401, description: 'Access token tidak disertakan atau tidak valid.')]
    public function show(Request $request, CustomerBalanceService $balances): JsonResponse
    {
        $customer = $this->customer($request);

        $balance = $balances->balance($customer);

        $mutations = $customer->balanceMutations()
            ->latest('created_at')
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return response()->json([
            'data' => [
                'balance' => Money::decimalString($balance),
                'mutations' => CustomerBalanceMutationResource::collection($mutations),
            ],
            'meta' => [
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
