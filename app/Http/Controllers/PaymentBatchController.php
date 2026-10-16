<?php

namespace App\Http\Controllers;

use App\Enums\CollectorRole;
use App\Models\User;
use App\Services\CollectorBalanceService;
use App\Traits\RecordsCollectorBatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Jalur ADMIN mencatat pembayaran batch atas nama seorang kolektor — dipakai
 * dari tab Pembayaran di Worksheet Admin (`/collector-worksheet/{collector}`).
 *
 * Kolektornya diambil dari route {collector}, bukan dari body. Itu aman DI
 * SINI karena rutenya digerbang `payments.create` — hak bayar penuh yang cuma
 * dimiliki admin. Jalur kolektor punya controller sendiri
 * (CollectorPaymentController) yang memaksa `auth()->user()`; jangan
 * gabungkan dua-duanya ke satu rute ber-parameter, karena begitu kolektor
 * boleh submit ke rute ber-{collector}, kolektor A bisa mencatat pembayaran
 * atas nama kolektor B.
 *
 * Seluruh logika ada di CollectorPaymentService: satu transaksi,
 * all-or-nothing, idempotency, batas nominal. Controller tipis sesuai
 * pembagian layer repo.
 *
 * docs/plan/kolektor/analisa-alur-kolektor-2.0.md §9.
 */
class PaymentBatchController extends Controller
{
    use RecordsCollectorBatch;

    public function storeForTechnician(Request $request, User $technician, CollectorBalanceService $balance): JsonResponse
    {
        abort_unless($technician->isTechnician(), 404);
        abort_unless($balance->isVisibleTo($technician, $request->user()), 403);

        $this->normalizeBatchAmounts($request);

        $validated = $request->validate($this->batchValidationRules());

        return $this->recordBatch($technician, $request->user(), $validated, CollectorRole::TEKNISI);
    }

    public function store(Request $request, User $collector): JsonResponse
    {
        abort_unless($collector->hasRole('kolektor'), 404, 'User ini bukan kolektor.');

        $this->normalizeBatchAmounts($request);

        $validated = $request->validate($this->batchValidationRules());

        return $this->recordBatch($collector, $request->user(), $validated);
    }
}
