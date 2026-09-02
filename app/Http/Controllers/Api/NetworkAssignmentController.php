<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\NetworkAssignmentException;
use App\Http\Controllers\Controller;
use App\Services\NetworkAssignmentService;
use App\Services\TelegramBotService;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * Endpoint #2 — `POST /api/v1/installations/network-assignment`
 * (docs/api/api-pop-distribusi/business-logic.md). Validasi bentuk request
 * (HTTP) di sini; seluruh logic domain (resolve pelanggan, validasi Mini
 * POP/Distribusi, regenerasi CID, audit, dedup) ada di
 * {@see NetworkAssignmentService} — controller tetap tipis (CLAUDE.md).
 *
 * Sebelum rev 12 (keputusan.md §19), endpoint ini juga menangani
 * `perangkat` (kredensial PPPoE + OLT). Dipecah atas keputusan pemilik
 * produk supaya assignment jaringan dan pembaruan perangkat bisa berkembang
 * independen — lihat {@see NetworkDeviceController} untuk `perangkat`.
 */
#[Group('PoP & Network Assignment', 'Endpoint bagi sistem mitra untuk membaca struktur Mini POP dan Distribusi, serta mengonfirmasi penugasan jaringan pelanggan setelah proses pemasangan. Seluruh permintaan diautentikasi menggunakan token Bearer.')]
class NetworkAssignmentController extends Controller
{
    public function __construct(private readonly NetworkAssignmentService $service) {}

    /**
     * Network Assignment
     *
     * Mengonfirmasi Mini POP dan Distribusi yang berlaku untuk seorang
     * pelanggan.
     *
     * Pelanggan diidentifikasi melalui `idempotency_key` yang diterima pada
     * payload webhook aktivasi pemasangan, bukan melalui ID internal
     * pelanggan.
     *
     * Untuk memperbarui kredensial PPPoE atau detail titik sambung OLT,
     * gunakan `POST /api/v1/installations/network-device` setelah
     * penugasan jaringan pelanggan ini dikonfirmasi.
     */
    #[BodyParameter('idempotency_key', description: 'Kunci unik yang diterima pada payload webhook aktivasi pemasangan, digunakan untuk mengidentifikasi pelanggan.', example: 'installation:1938:activation:7')]
    #[BodyParameter('mini_pop_code', description: 'Kode Mini POP.', example: 'D1')]
    #[BodyParameter('distribution_code', description: 'Kode Distribusi.', example: 'X6A')]
    #[Response(200, description: 'Penugasan berhasil dikonfirmasi. Kolom `cid` hanya disertakan apabila pelanggan sudah berstatus aktif dan memiliki identitas pelanggan final.', examples: [
        [
            'mini_pop_code' => 'D1',
            'distribution_code' => 'X6A',
        ],
        [
            'cid' => 'D001X6ARQ000025',
            'mini_pop_code' => 'D1',
            'distribution_code' => 'X6A',
        ],
    ])]
    #[Response(401, description: 'Token akses tidak disertakan atau tidak valid.')]
    #[Response(404, description: 'Pelanggan tidak ditemukan untuk `idempotency_key` yang diberikan.')]
    #[Response(422, description: 'Permintaan tidak dapat diproses — kode Mini POP/Distribusi tidak sesuai dengan Cabang POP pelanggan, atau status pelanggan belum memungkinkan penugasan jaringan.')]
    #[Response(429, description: 'Jumlah permintaan melebihi batas yang diizinkan (20 permintaan per menit).')]
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'idempotency_key' => ['required', 'string', 'max:100'],
                'mini_pop_code' => ['required', 'string', 'max:20'],
                'distribution_code' => ['required', 'string', 'max:50'],
            ]);

            $result = $this->service->assignNetwork($validated, (string) $request->ip());
        } catch (ValidationException $e) {
            // ValidationException SELALU 422 (mini_pop_code/distribution_code
            // gak nyambung, format field salah, dst).
            $this->trackFailure($request);

            throw $e;
        } catch (NetworkAssignmentException $e) {
            // 404 (idempotency_key gak ketemu) SENGAJA tidak dihitung ke
            // ambang alert — pertanyaan #3 rencana-implementasi.md eksplisit
            // soal "422 berulang", bukan status gagal lain. idempotency_key
            // asing berulang adalah gejala berbeda (event belum sampai/
            // retensi outbox), bukan pola yang sama dengan kode Mini
            // POP/Distribusi yang salah.
            if ($e->getStatusCode() === 422) {
                $this->trackFailure($request);
            }

            throw $e;
        }

        // "beruntun" (rencana-implementasi.md pertanyaan #3) — sukses
        // memutus rentetan gagal, bukan cuma jeda waktu 10 menit yang lewat.
        $this->resetFailureCounter($request);

        return response()->json($result);
    }

    /**
     * ≥5 gagal 422 beruntun dalam 10 menit dari token+IP yang sama → alert
     * staf (rencana-implementasi.md §"Keputusan resmi" #3). Cache key per
     * token+IP supaya satu integrator Website B yang error-nya sendiri tidak
     * "menutupi" pola gagal integrator lain — meski hari ini cuma ada satu
     * token tulis, ini tetap benar kalau nanti ada konsumen kedua.
     */
    private function trackFailure(Request $request): void
    {
        $key = $this->failureCacheKey($request);
        $count = ((int) Cache::get($key, 0)) + 1;
        Cache::put($key, $count, now()->addMinutes(10));

        if ($count === 5) {
            app(TelegramBotService::class)->sendMessage(
                "⚠️ API network-assignment: {$count} gagal validasi (422) beruntun dalam 10 menit dari IP {$request->ip()}. Kemungkinan kesalahan integrasi Website B."
            );
        }
    }

    private function resetFailureCounter(Request $request): void
    {
        Cache::forget($this->failureCacheKey($request));
    }

    private function failureCacheKey(Request $request): string
    {
        return 'network-assignment:422:'.sha1(($request->bearerToken() ?? '').'|'.$request->ip());
    }
}
