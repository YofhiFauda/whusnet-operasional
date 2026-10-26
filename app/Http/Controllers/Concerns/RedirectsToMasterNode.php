<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * PRG untuk form di panel "Manajemen Node" Master Node (kapasitas perangkat/
 * Slot/PON, Tambah Slot/PON/ODP). Form yang sama juga ada di halaman Detail
 * dan di sana tetap mendarat di Detail (docs/PRG_REDIRECT_CONVENTION.md).
 * Dari Master Node, mendarat di Detail memutus kerja beruntun di papan
 * pohon — user harus kembali dan memilih node lagi — jadi partial panel
 * mengirim `master_node` = id node yang sedang dibuka, dan redirect sukses
 * diarahkan ke `network.nodes.index?node=<id>` supaya node itu terpilih ulang.
 *
 * Hanya id integer yang diterima dan rute tujuannya tetap (bukan URL
 * kiriman klien), jadi tidak ada open redirect. Id di luar scope POP
 * diabaikan diam-diam oleh `NetworkNodeController::index()`, tidak bocor.
 * Pesan flash yang sudah dipasang di `$detail` ikut terbawa (session).
 */
trait RedirectsToMasterNode
{
    protected function redirectToNodeContext(Request $request, RedirectResponse $detail): RedirectResponse
    {
        $masterNodeId = $request->integer('master_node');

        if ($masterNodeId > 0) {
            $detail->setTargetUrl(route('network.nodes.index', ['node' => $masterNodeId]));
        }

        return $detail;
    }
}
