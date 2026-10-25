<?php

namespace Database\Seeders;

use App\Models\NetworkNode;
use App\Models\NetworkNodeType;
use App\Models\Pop;
use App\Models\User;
use App\Services\InfrastructurePositionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Kerangka topologi contoh struktur BARU (rancangan hierarki §1, §16.2,
 * §16.4 #5):
 *
 *   Server Utama (anchor Pusat) → Cabang → Mini POP
 *     ├── OLT-{CABANG}-01 → Slot 1 → PON 1 → ODP-{CABANG}-01 (anak langsung PON)
 *     └── SW-{CABANG}-01 (switch_mini_pop) → Slot 1 internal/logis → Port 1
 *
 * Slot, PON, dan Port Switch adalah baris `network_nodes` biasa (anak lewat
 * `parent_id`), dibuat lewat `InfrastructurePositionService` supaya kode
 * turunan (`{OLT}-S1`, `{OLT}-S1-P1`) dan aturan penempatannya identik dengan
 * jalur UI. ODP menggantung ke PON lewat `parent_id`, bukan lewat
 * `infrastructure_connections` (sambungan hanya untuk Port Switch) — tabel
 * `infrastructure_slots`/`infrastructure_ports` sudah dibuang (Task 14).
 *
 * Aturan yang sengaja dipegang (dulu dilanggar seeder versi lama):
 * - TIDAK membuat backbone, aggregation_switch, feeder, atau node kartu OLT —
 *   representasi lama (§3, §16.2). Baris lama yang sudah ada TIDAK dipindah,
 *   diubah tipenya, atau dihapus (data nyata, audit Task 1).
 * - TIDAK memetakan pelanggan ke port ODP dari teks `odp_number` — §4.3
 *   mewajibkan referensi ID terverifikasi; pemetaan pelanggan = keputusan
 *   operator di Master Node.
 * - ODP contoh dibuat `inactive` supaya tidak dianggap sudah terpasang di
 *   lapangan (tidak ada status palsu).
 * - Idempoten: dijalankan ulang tidak menggandakan posisi.
 */
class WhusnetTopologySeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $pusat = Pop::query()->where('type', 'pusat')->whereIn(DB::raw('LOWER(name)'), ['connexa', 'whusnet'])->first()
                ?? $this->pop('CONNEXA', 'pusat');
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
            // Operator nyata tetap disyaratkan: seeder tidak boleh jalan di DB
            // tanpa akun (jejak audit pembuatan node butuh pelakunya).
            if (User::query()->doesntExist()) {
                throw new RuntimeException('Seeder membutuhkan akun operator nyata untuk mencatat pembuatan topologi.');
            }

            foreach ($branches as $branch) {
                $suffix = strtoupper($branch->name);
                $mini = $this->miniPop($branch);

                $olt = $this->node("OLT-{$suffix}-01", "OLT {$branch->name} 01", (int) $types['olt_chassis'], $mini);
                $slot = $olt->slots()->where('number', 1)->first() ?? $positions->createSlot($olt, 1);
                $pon = $slot->positionPorts()->where('number', 1)->first() ?? $positions->createPort($slot, 1);

                $switch = $this->node("SW-{$suffix}-01", "Switch {$branch->name} 01", (int) $types['switch_mini_pop'], $mini);
                $switchSlot = $positions->ensureLogicalSlot($switch);
                $switchSlot->positionPorts()->where('number', 1)->exists() || $positions->createPort($switchSlot, 1);

                // ODP lama (bukan hasil seeder ini) tidak dipindah ke PON contoh —
                // uplink-nya ambigu dan menunggu keputusan operator. ODP baru
                // menggantung ke PON lewat parent_id tanpa anchor_pop_id: POP-nya
                // diwariskan dari rantai induk (OLT → Mini POP).
                if (NetworkNode::query()->where('code', "ODP-{$suffix}-01")->doesntExist()) {
                    NetworkNode::create([
                        'code' => "ODP-{$suffix}-01",
                        'name' => "ODP contoh {$branch->name} 01",
                        'node_type_id' => (int) $types['odp'],
                        'parent_id' => $pon->id,
                        'status' => 'inactive',
                        'notes' => 'Kerangka topologi contoh; perangkat dan jalur fisik perlu diverifikasi.',
                    ]);
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
