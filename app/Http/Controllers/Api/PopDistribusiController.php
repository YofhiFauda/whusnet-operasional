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
 * Cabang POP dengan Mini POP dan Distribusi di bawahnya — Website B mau
 * daftar LENGKAP, bukan yang relevan buat satu pemasangan (dikonfirmasi
 * kebutuhan, lihat business-logic.md).
 *
 * Distribusi SEJAJAR Mini POP (keduanya anak langsung Cabang), BUKAN nested
 * di dalam Mini POP — Distribusi (ODP) lazim dipakai bareng lebih dari satu
 * Mini POP dalam Cabang yang sama (ADHOC-312, laporan user 2026-11-23:
 * Cabang Siman D1/D2 lintas Mini POP). BREAKING CHANGE kontrak API dari
 * bentuk sebelumnya (Distribusi nested per Mini POP) — Website B wajib
 * update parsing-nya.
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
                    ['code' => 'C1', 'name' => 'Mini POP C1'],
                ],
                'distributions' => [
                    ['code' => 'A', 'name' => 'Distribusi A'],
                    ['code' => 'B', 'name' => 'Distribusi B'],
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

        // Distribusi anak Cabang, BUKAN anak Mini POP (ADHOC-312: satu
        // Distribusi lazim dipakai bareng lebih dari satu Mini POP dalam
        // Cabang yang sama) — jadi sejajar `mini_pops`, bukan nested di
        // dalamnya. BREAKING CHANGE kontrak API (keputusan user 2026-11-23):
        // Website B wajib update parsing-nya.
        $distributions = Distribution::whereIn('pop_id', $cabangPops->pluck('id'))
            ->orderBy('code')
            ->get(['id', 'pop_id', 'code', 'name']);

        $miniPopsByParent = $miniPops->groupBy('parent_id');
        $distributionsByPop = $distributions->groupBy('pop_id');

        $data = $cabangPops->map(function (Pop $pop) use ($miniPopsByParent, $distributionsByPop) {
            $childMiniPops = $miniPopsByParent->get($pop->id) ?? collect();
            $childDistributions = $distributionsByPop->get($pop->id) ?? collect();

            return [
                'pop_code' => $pop->pop_code,
                'pop_name' => $pop->name,
                'mini_pops' => $childMiniPops->map(fn (Pop $miniPop) => [
                    'code' => $miniPop->pop_code,
                    'name' => $miniPop->name,
                ])->values(),
                'distributions' => $childDistributions->map(fn (Distribution $distribution) => [
                    'code' => $distribution->code,
                    'name' => $distribution->name,
                ])->values(),
            ];
        })->values();

        return response()->json(['data' => $data]);
    }
}
