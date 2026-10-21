<?php

namespace Database\Seeders;

use App\Enums\InfrastructurePortKind;
use App\Models\InfrastructureConnection;
use App\Models\InfrastructurePort;
use App\Models\InfrastructureSlot;
use App\Models\NetworkNode;
use App\Models\NetworkNodeType;
use App\Models\Pop;
use App\Models\User;
use App\Services\InfrastructureConnectionService;
use App\Services\InfrastructurePositionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Kerangka topologi contoh struktur BARU (rancangan hierarki §1, §16.2,
 * §16.4 #5):
 *
 *   Server Utama (anchor Pusat) → Cabang → Mini POP
 *     ├── OLT-{CABANG}-01 → Slot 1 → PON 1 → ODP-{CABANG}-01 (koneksi BELUM terverifikasi)
 *     └── SW-{CABANG}-01 (switch_mini_pop) → Slot 1 internal/logis → Port 1
 *
 * Aturan yang sengaja dipegang (dulu dilanggar seeder versi lama):
 * - TIDAK membuat backbone, aggregation_switch, feeder, node kartu OLT, atau
 *   node PON — representasi lama (§3, §16.2). Baris lama yang sudah ada TIDAK
 *   dipindah, diubah tipenya, atau dihapus (data nyata, audit Task 1).
 * - TIDAK memetakan pelanggan ke port ODP dari teks `odp_number` — §4.3
 *   mewajibkan referensi ID terverifikasi; pemetaan pelanggan = keputusan
 *   operator di Master Node.
 * - Koneksi contoh dibuat BELUM terverifikasi supaya tidak ada status
 *   verifikasi palsu.
 * - Idempoten: dijalankan ulang tidak menggandakan posisi.
 */
class WhusnetTopologySeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $pusat = $this->pop('WHUSNET', 'pusat');
            $branches = [$this->pop('Jetis', 'cabang'), $this->pop('Sandya', 'cabang')];

            foreach ($branches as $branch) {
                if ($branch->parent_id !== null && $branch->parent_id !== $pusat->id) {
                    throw new RuntimeException("Cabang {$branch->name} sudah memiliki Pusat lain.");
                }
                if ($branch->parent_id === null) {
                    $branch->update(['parent_id' => $pusat->id]);
                }
            }

            $this->call(NetworkNodeTypeSeeder::class);
            $types = NetworkNodeType::query()->whereIn('code', ['server', 'olt_chassis', 'switch_mini_pop', 'odp'])->pluck('id', 'code');
            if ($types->count() !== 4) {
                throw new RuntimeException('Tipe node server/olt_chassis/switch_mini_pop/odp belum tersedia.');
            }

            $positions = app(InfrastructurePositionService::class);
            $connections = app(InfrastructureConnectionService::class);
            $actor = User::query()->orderBy('id')->first();
            if ($actor === null) {
                throw new RuntimeException('Seeder membutuhkan akun operator nyata untuk mencatat koneksi.');
            }

            $this->node('CORE-WHUSNET-PUSAT', 'Server Utama WHUSNET', (int) $types['server'], $pusat);

            foreach ($branches as $branch) {
                $suffix = strtoupper($branch->name);
                $mini = $this->miniPop($branch);

                $olt = $this->node("OLT-{$suffix}-01", "OLT {$branch->name} 01", (int) $types['olt_chassis'], $mini);
                $slot = InfrastructureSlot::query()->where('network_node_id', $olt->id)->where('number', 1)->first()
                    ?? $positions->createSlot($olt, 1);
                $pon = InfrastructurePort::query()->where('infrastructure_slot_id', $slot->id)->where('number', 1)->first()
                    ?? $positions->createPort($slot, 1, InfrastructurePortKind::PON);

                $switch = $this->node("SW-{$suffix}-01", "Switch {$branch->name} 01", (int) $types['switch_mini_pop'], $mini);
                $switchSlot = $positions->ensureLogicalSlot($switch);
                InfrastructurePort::query()->where('infrastructure_slot_id', $switchSlot->id)->where('number', 1)->exists()
                    || $positions->createPort($switchSlot, 1, InfrastructurePortKind::SWITCH);

                $existingOdp = NetworkNode::query()->where('code', "ODP-{$suffix}-01")->first();
                $odp = $existingOdp ?? $this->node("ODP-{$suffix}-01", "ODP contoh {$branch->name} 01", (int) $types['odp'], $mini);
                // ODP lama (bukan hasil seeder ini) tidak disambung otomatis —
                // uplink-nya ambigu dan menunggu keputusan operator.
                if ($existingOdp === null
                    && ! InfrastructureConnection::query()->active()->where('target_network_node_id', $odp->id)->exists()) {
                    $connections->connect($pon, $actor, targetNode: $odp, notes: 'Koneksi contoh seeder — verifikasi di lapangan.');
                }
            }
        });
    }

    private function pop(string $name, string $type): Pop
    {
        $matches = Pop::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->get();
        if ($matches->count() !== 1 || $matches->first()->type !== $type) {
            throw new RuntimeException("POP {$name} harus ada tepat satu dengan tipe {$type}.");
        }

        return $matches->first();
    }

    private function miniPop(Pop $branch): Pop
    {
        $existing = Pop::query()->where('parent_id', $branch->id)->where('type', 'mini_pop')->where('status', 'active')->get();
        if ($existing->count() > 1) {
            throw new RuntimeException("Cabang {$branch->name} memiliki lebih dari satu Mini POP; pilih titik OLT secara manual.");
        }

        if ($existing->isEmpty()) {
            throw new RuntimeException("Cabang {$branch->name} belum memiliki Mini POP nyata; pilih lokasi secara manual.");
        }

        return $existing->first();
    }

    /**
     * Cari berdasarkan kode; node yang sudah ada TIDAK dipindah atau diubah
     * tipenya — kalau penempatannya berbeda, seeder berhenti supaya operator
     * memutuskan (bukan menimpa data nyata diam-diam).
     */
    private function node(string $code, string $name, int $typeId, Pop $anchor): NetworkNode
    {
        $node = NetworkNode::query()->where('code', $code)->first();
        if ($node !== null) {
            if ($node->node_type_id !== $typeId || $node->anchor_pop_id !== $anchor->id || $node->parent_id !== null) {
                throw new RuntimeException("Node {$code} sudah ada dengan tipe/penempatan berbeda — petakan manual.");
            }

            return $node;
        }

        return NetworkNode::create([
            'code' => $code,
            'name' => $name,
            'node_type_id' => $typeId,
            'anchor_pop_id' => $anchor->id,
            'status' => 'inactive',
            'notes' => 'Kerangka topologi contoh; perangkat dan jalur fisik perlu diverifikasi.',
        ]);
    }
}
