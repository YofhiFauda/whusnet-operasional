<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Distribution;
use App\Models\Pop;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;

/**
 * Endpoint #1 — `GET /api/v1/pop-distribusi`
 * (docs/api/api-pop-distribusi/business-logic.md). Baca-saja, seluruh
 * hierarki Cabang POP → Mini POP → Distribusi. Query sama persis yang dipakai
 * `CustomerNetworkAssignmentController::data()` (`:20-45`), cuma tanpa
 * scoping ke satu pelanggan — Website B mau daftar LENGKAP, bukan yang
 * relevan buat satu pemasangan (dikonfirmasi kebutuhan, lihat business-logic.md).
 */
#[Group('PoP & Network Assignment', 'Endpoint bagi sistem mitra untuk membaca struktur Mini POP dan Distribusi, serta mengonfirmasi penugasan jaringan pelanggan setelah proses pemasangan. Seluruh permintaan diautentikasi menggunakan token Bearer.')]
class PopDistribusiController extends Controller
{
    /**
     *  POP & Distribusi
     *
     * Mengembalikan seluruh struktur Cabang POP, Mini POP, dan Distribusi
     * yang tersedia dalam sistem. Gunakan endpoint ini untuk memperoleh kode
     * `mini_pop_code` dan `distribution_code` yang valid sebelum
     * mengonfirmasi penugasan jaringan pelanggan.
     *
     * Data bersifat statis dan jarang berubah. Sistem mitra disarankan
     * menyimpan hasilnya secara lokal dan memperbarui secara berkala,
     * alih-alih memanggil endpoint ini pada setiap proses pemasangan.
     */
    #[Response(200, description: 'Daftar topologi berhasil diambil.', examples: [[
        'data' => [
            [
                'pop_code' => 'PNR-JTS',
                'pop_name' => 'Jetis',
                'mini_pops' => [
                    [
                        'code' => 'C1',
                        'name' => 'Mini POP C1',
                        'distributions' => [
                            ['code' => 'A', 'name' => 'Distribusi A'],
                            ['code' => 'B', 'name' => 'Distribusi B'],
                        ],
                    ],
                ],
            ],
        ],
    ]])]
    #[Response(401, description: 'Token akses tidak disertakan atau tidak valid.')]
    #[Response(429, description: 'Jumlah permintaan melebihi batas yang diizinkan (120 permintaan per menit).')]
    public function index(): JsonResponse
    {
        $cabangPops = Pop::where('type', 'cabang')
            ->orderBy('name')
            ->get(['id', 'pop_code', 'name']);

        $miniPops = Pop::where('type', 'mini_pop')
            ->whereIn('parent_id', $cabangPops->pluck('id'))
            ->orderBy('name')
            ->get(['id', 'parent_id', 'pop_code', 'name']);

        $distributions = Distribution::whereIn('pop_id', $miniPops->pluck('id'))
            ->orderBy('code')
            ->get(['id', 'pop_id', 'code', 'name']);

        $miniPopsByParent = $miniPops->groupBy('parent_id');
        $distributionsByMiniPop = $distributions->groupBy('pop_id');

        $data = $cabangPops->map(function (Pop $pop) use ($miniPopsByParent, $distributionsByMiniPop) {
            $childMiniPops = $miniPopsByParent->get($pop->id) ?? collect();

            return [
                'pop_code' => $pop->pop_code,
                'pop_name' => $pop->name,
                'mini_pops' => $childMiniPops->map(function (Pop $miniPop) use ($distributionsByMiniPop) {
                    $childDistributions = $distributionsByMiniPop->get($miniPop->id) ?? collect();

                    return [
                        'code' => $miniPop->pop_code,
                        'name' => $miniPop->name,
                        'distributions' => $childDistributions->map(fn (Distribution $distribution) => [
                            'code' => $distribution->code,
                            'name' => $distribution->name,
                        ])->values(),
                    ];
                })->values(),
            ];
        })->values();

        return response()->json(['data' => $data]);
    }
}
