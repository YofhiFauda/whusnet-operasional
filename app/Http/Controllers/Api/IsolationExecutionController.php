<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\IsolationExecutionException;
use App\Http\Controllers\Controller;
use App\Models\IsolationCase;
use App\Services\IsolationExecutionService;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint eksekusi isolir dari Web A
 * (docs/superpowers/specs/2026-11-20-webhook-isolir-web-a-design.md).
 */
#[Group('Isolir — Eksekusi Web A', 'Endpoint arah masuk bagi Web A untuk mengeksekusi isolir setelah menerima webhook keluar `isolation.ready_for_execution`. Diautentikasi menggunakan token Bearer statis terpisah dari token-token lain di API ini.')]
class IsolationExecutionController extends Controller
{
    /**
     * Eksekusi Isolir
     *
     * Mengeksekusi isolir untuk kasus yang `case_number`-nya dikirim pada
     * payload webhook `isolation.ready_for_execution`.
     *
     * Directional ("pastikan status pelanggan suspended"), bukan toggle —
     * aman dipanggil berkali-kali untuk `case_number` yang sama (retry
     * jaringan, atau NOC sudah isolir manual duluan): balasannya tetap
     * `200` dengan `already_suspended: true`, tidak pernah membalik
     * pelanggan ke aktif.
     *
     * `case_number` yang basi (kasusnya sudah dibatalkan/selesai, atau
     * sudah digantikan kasus baru untuk layanan yang sama) ditolak `422`,
     * tidak ikut mengeksekusi kasus lain yang kebetulan aktif sekarang.
     */
    #[BodyParameter('case_number', description: 'Nomor kasus isolir, diambil dari payload webhook `isolation.ready_for_execution` (field `case_number`).', example: 'TFOP-20261120-000005')]
    #[Response(200, description: 'Isolir berhasil dieksekusi. `already_suspended: false` kalau ini eksekusi pertama; `true` kalau pelanggan sudah berstatus suspended sebelumnya (idempotent no-op, tidak ada perubahan).', type: 'array{success: bool, case_number: string, status: "suspended", already_suspended: bool}', examples: [
        [
            'success' => true,
            'case_number' => 'TFOP-20261120-000005',
            'status' => 'suspended',
            'already_suspended' => false,
        ],
        [
            'success' => true,
            'case_number' => 'TFOP-20261120-000005',
            'status' => 'suspended',
            'already_suspended' => true,
        ],
    ])]
    #[Response(401, description: 'Token akses tidak disertakan atau tidak valid.')]
    #[Response(422, description: '`case_number` tidak ditemukan, atau kasus ditemukan tapi tidak lagi siap dieksekusi (sudah dibatalkan/selesai/digantikan kasus baru), atau pelanggan tidak punya layanan aktif.', examples: [
        [
            'success' => false,
            'message' => 'Kasus ini tidak lagi siap dieksekusi.',
        ],
    ])]
    #[Response(429, description: 'Jumlah permintaan melebihi batas yang diizinkan (20 permintaan per menit).')]
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'case_number' => ['required', 'string', 'exists:isolation_cases,case_number'],
        ]);

        $case = IsolationCase::where('case_number', $data['case_number'])->firstOrFail();

        try {
            $result = app(IsolationExecutionService::class)->suspendFromWebA($case);
        } catch (IsolationExecutionException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'case_number' => $case->case_number,
            'status' => 'suspended',
            'already_suspended' => $result['already_suspended'],
        ]);
    }
}
