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
 * Endpoint #3 — `POST /api/v1/installations/network-device`
 * (docs/api/api-pop-distribusi/business-logic.md, keputusan.md §19). Pisahan
 * dari `NetworkAssignmentController` — kredensial PPPoE & detail titik
 * sambung OLT, terpisah dari assignment Mini POP/Distribusi supaya keduanya
 * bisa berkembang independen.
 */
#[Group('PoP & Network Assignment', 'Endpoint bagi sistem mitra untuk membaca struktur Mini POP dan Distribusi, serta mengonfirmasi penugasan jaringan pelanggan setelah proses pemasangan. Seluruh permintaan diautentikasi menggunakan token Bearer.')]
class NetworkDeviceController extends Controller
{
    public function __construct(private readonly NetworkAssignmentService $service) {}

    /**
     * Network Device
     *
     * Memperbarui kredensial PPPoE dan/atau detail titik sambung OLT untuk
     * seorang pelanggan.
     *
     * Pelanggan diidentifikasi melalui `idempotency_key` yang diterima pada
     * payload webhook aktivasi pemasangan, bukan melalui ID internal
     * pelanggan. Pelanggan harus sudah memiliki Mini POP dan Distribusi
     * yang tersimpan — konfirmasikan lebih dulu lewat
     * `POST /api/v1/installations/network-assignment` apabila belum.
     *
     * Objek `perangkat` boleh diisi sebagian — kirim field yang sudah
     * diketahui, field yang tidak dikirim tidak akan menimpa nilai yang
     * sudah tersimpan sebelumnya.
     */
    #[BodyParameter('idempotency_key', description: 'Kunci unik yang diterima pada payload webhook aktivasi pemasangan, digunakan untuk mengidentifikasi pelanggan.', example: 'installation:1938:activation:7')]
    #[BodyParameter('perangkat', example: [
        'pppoe_username' => 'D1X6ARQ000025_BROTONEGARAN_SAMSUDIN',
        'pppoe_password' => 'secretpassword123',
        'olt_number' => '3',
        'olt_slot' => '1',
        'olt_port' => '8',
        'vlan' => '301',
    ])]
    #[Response(200, description: 'Perangkat berhasil diperbarui. Objek `perangkat` pada respons memuat field yang barusan disimpan (tanpa `pppoe_password`, tidak pernah dikembalikan). Kolom `cid` hanya disertakan apabila pelanggan sudah berstatus aktif dan memiliki identitas pelanggan final.', examples: [
        [
            'mini_pop_code' => 'D1',
            'distribution_code' => 'X6A',
            'perangkat' => [
                'pppoe_username' => 'D1X6ARQ000025_BROTONEGARAN_SAMSUDIN',
                'olt_number' => '3',
                'olt_slot' => '1',
                'olt_port' => '8',
                'vlan' => '301',
            ],
        ],
    ])]
    #[Response(401, description: 'Token akses tidak disertakan atau tidak valid.')]
    #[Response(404, description: 'Pelanggan tidak ditemukan untuk `idempotency_key` yang diberikan.')]
    #[Response(422, description: 'Permintaan tidak dapat diproses — objek `perangkat` kosong, atau pelanggan belum memiliki Mini POP/Distribusi tersimpan.')]
    #[Response(429, description: 'Jumlah permintaan melebihi batas yang diizinkan (20 permintaan per menit).')]
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'idempotency_key' => ['required', 'string', 'max:100'],
                'perangkat' => ['required', 'array'],
                'perangkat.pppoe_username' => ['nullable', 'string', 'max:150'],
                'perangkat.pppoe_password' => ['nullable', 'string', 'max:150'],
                'perangkat.olt_number' => ['nullable', 'string', 'max:50'],
                'perangkat.olt_slot' => ['nullable', 'string', 'max:20'],
                'perangkat.olt_port' => ['nullable', 'string', 'max:50'],
                'perangkat.vlan' => ['nullable', 'string', 'max:20'],
            ]);

            $this->assertPerangkatNotEmpty($validated);

            $result = $this->service->assignDevice($validated, (string) $request->ip());
        } catch (ValidationException $e) {
            $this->trackFailure($request);

            throw $e;
        } catch (NetworkAssignmentException $e) {
            // Sama seperti NetworkAssignmentController — 404 (idempotency_key
            // gak ketemu) gak dihitung ke ambang alert, cuma 422.
            if ($e->getStatusCode() === 422) {
                $this->trackFailure($request);
            }

            throw $e;
        }

        $this->resetFailureCounter($request);

        return response()->json($result);
    }

    /**
     * `perangkat` wajib ada sebagai objek (`required`), tapi semua field
     * di dalamnya nullable satu-satu — objek yang isinya semua kosong/null
     * gak ada gunanya, jadi ditolak di sini. Dipisah dari rules `validate()`
     * di `store()` dengan alasan sama seperti versi lama endpoint ini:
     * Scramble membaca `$request->validate()` inline secara statis untuk
     * skema dokumentasi, jadi rules-nya tidak boleh pindah ke method privat.
     *
     * @param  array{idempotency_key: string, perangkat: array<string, mixed>}  $validated
     */
    private function assertPerangkatNotEmpty(array $validated): void
    {
        $hasPerangkat = array_filter(
            $validated['perangkat'] ?? [],
            fn ($v) => $v !== null && $v !== ''
        ) !== [];

        if (! $hasPerangkat) {
            throw NetworkAssignmentException::unprocessable(
                'Objek perangkat harus berisi minimal satu field.'
            );
        }
    }

    /**
     * ≥5 gagal 422 beruntun dalam 10 menit dari token+IP yang sama → alert
     * staf. Cache key SENGAJA namespace-nya beda dari
     * NetworkAssignmentController ('network-device' vs 'network-assignment')
     * — dua endpoint independen, kegagalan beruntun di satu endpoint tidak
     * boleh ikut memicu/menutupi ambang di endpoint yang lain.
     */
    private function trackFailure(Request $request): void
    {
        $key = $this->failureCacheKey($request);
        $count = ((int) Cache::get($key, 0)) + 1;
        Cache::put($key, $count, now()->addMinutes(10));

        if ($count === 5) {
            app(TelegramBotService::class)->sendMessage(
                "⚠️ API network-device: {$count} gagal validasi (422) beruntun dalam 10 menit dari IP {$request->ip()}. Kemungkinan kesalahan integrasi Website B."
            );
        }
    }

    private function resetFailureCounter(Request $request): void
    {
        Cache::forget($this->failureCacheKey($request));
    }

    private function failureCacheKey(Request $request): string
    {
        return 'network-device:422:'.sha1(($request->bearerToken() ?? '').'|'.$request->ip());
    }
}
