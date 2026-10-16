<?php

namespace Database\Seeders;

use App\Models\NetworkNode;
use App\Models\NetworkNodeType;
use App\Models\Pop;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

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

            $codes = ['server', 'backbone', 'aggregation_switch', 'olt_chassis', 'pon_port', 'feeder', 'odp', 'drop_cable'];
            $types = NetworkNodeType::query()->whereIn('code', $codes)->pluck('id', 'code');
            if ($types->count() !== count($codes)) {
                throw new RuntimeException('Jalankan NetworkNodeTypeSeeder lebih dulu.');
            }

            $server = $this->node(
                'CORE-WHUSNET-PUSAT',
                'Kabel Core Server ke POP WHUSNET',
                (int) $types['server'],
                null,
                $pusat,
                ['CORE-WHUSNET-SERVER', 'WHUSNET-SERVER', 'TOPO-WHUSNET-SERVER', 'TOPO-WHUSNET-CORE', 'CORE-WHUSNET', 'CORE-PUSAT-01']
            );

            // Bersihkan node yatim bekas eksperimen penamaan yang tidak punya relasi
            $orphanIds = NetworkNode::query()
                ->whereIn('code', ['WHUSNET-SERVER', 'CORE-WHUSNET-SERVER', 'TOPO-WHUSNET-SERVER', 'TOPO-WHUSNET-CORE'])
                ->where('id', '!=', $server->id)
                ->whereDoesntHave('children')
                ->whereDoesntHave('ports')
                ->pluck('id');

            if ($orphanIds->isNotEmpty()) {
                NetworkNode::query()->whereIn('id', $orphanIds)->delete();
            }
            foreach ($branches as $branch) {
                $suffix = strtoupper($branch->name);
                $mini = $this->miniPop($branch);
                $backboneCode = "BB-PUSAT-{$suffix}-01";
                $aggregationCode = "AGG-{$suffix}-01";

                $backbone = $this->node(
                    $backboneCode,
                    "Backbone WHUSNET ke {$branch->name}",
                    (int) $types['backbone'],
                    $server,
                    null,
                    ["TOPO-WHUSNET-{$suffix}", "CORE-WHUSNET-{$suffix}", "BB-{$suffix}-01"]
                );
                $this->linkPop($branch, $backbone);

                $aggregation = $this->node(
                    $aggregationCode,
                    "Agregasi {$branch->name}",
                    (int) $types['aggregation_switch'],
                    null,
                    $branch,
                    ["TOPO-{$suffix}-AGG", "CORE-{$suffix}-AGG"]
                );
                $this->linkPop($mini, $aggregation);

                $olt = $this->node(
                    "OLT-{$suffix}-01",
                    "OLT {$branch->name} 01",
                    (int) $types['olt_chassis'],
                    null,
                    $mini,
                    ["TOPO-{$suffix}-OLT", "OLT-{$suffix}"]
                );
                $pon = $this->node(
                    "PON-{$suffix}-01",
                    "PON {$branch->name} 01",
                    (int) $types['pon_port'],
                    $olt,
                    null,
                    ["TOPO-{$suffix}-PON", "PON-{$suffix}"]
                );
                $feeder = $this->node(
                    "FDR-{$suffix}-01",
                    "Feeder {$branch->name} 01",
                    (int) $types['feeder'],
                    $pon,
                    null,
                    ["TOPO-{$suffix}-FDR", "FDR-{$suffix}"]
                );
                $this->node(
                    "ODP-{$suffix}-01",
                    "ODP contoh {$branch->name} 01",
                    (int) $types['odp'],
                    $feeder,
                    null,
                    ["TOPO-{$suffix}-ODP", "ODP-{$suffix}"]
                );
            }

            $this->assignVerifiedCustomers((int) $types['odp'], (int) $types['drop_cable']);
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
        $code = strtoupper($branch->name).'-MINI';
        $name = "Mini POP {$branch->name}";
        $matches = Pop::query()->where('code', $code)
            ->orWhere(fn ($query) => $query->where('parent_id', $branch->id)->where('type', 'mini_pop')->whereRaw('LOWER(name) = ?', [mb_strtolower($name)]))
            ->get();

        if ($matches->count() > 1) {
            throw new RuntimeException("Mini POP {$branch->name} tidak unik.");
        }

        $mini = $matches->first();
        if ($mini === null) {
            $existing = Pop::query()->where('parent_id', $branch->id)
                ->where('type', 'mini_pop')->where('status', 'active')->get();
            if ($existing->count() > 1) {
                throw new RuntimeException("Cabang {$branch->name} memiliki lebih dari satu Mini POP; pilih titik OLT secara manual.");
            }
            $mini = $existing->first();
        }

        if ($mini !== null) {
            if ($mini->type !== 'mini_pop' || $mini->parent_id !== $branch->id) {
                throw new RuntimeException("Mini POP {$branch->name} memiliki penempatan berbeda.");
            }

            return $mini;
        }

        return Pop::create([
            'code' => $code,
            'name' => $name,
            'type' => 'mini_pop',
            'parent_id' => $branch->id,
            'status' => 'active',
        ]);
    }

    private function linkPop(Pop $pop, NetworkNode $uplink): void
    {
        if ($pop->uplink_node_id !== $uplink->id) {
            $pop->update(['uplink_node_id' => $uplink->id]);
        }
    }

    /**
     * @param  array<int, string>  $legacyCodes
     */
    private function node(string $code, string $name, int $typeId, ?NetworkNode $parent, ?Pop $anchor, array $legacyCodes = []): NetworkNode
    {
        $node = NetworkNode::query()->where('code', $code)->first();

        if ($node === null && ! empty($legacyCodes)) {
            $node = NetworkNode::query()->whereIn('code', $legacyCodes)->first();
        }

        if ($node !== null) {
            $node->update([
                'code' => $code,
                'name' => $name,
                'node_type_id' => $typeId,
                'parent_id' => $parent?->id,
                'anchor_pop_id' => $anchor?->id,
            ]);

            return $node;
        }

        return NetworkNode::create([
            'code' => $code,
            'name' => $name,
            'node_type_id' => $typeId,
            'parent_id' => $parent?->id,
            'anchor_pop_id' => $anchor?->id,
            'status' => 'inactive',
            'notes' => 'Kerangka topologi logis; perangkat dan jalur fisik perlu diverifikasi.',
        ]);
    }

    private function assignVerifiedCustomers(int $odpTypeId, int $dropCableTypeId): void
    {
        $allNodes = DB::table('network_nodes')->get(['id', 'parent_id', 'anchor_pop_id'])->keyBy('id');
        $labels = [];
        foreach (DB::table('network_nodes')->where('node_type_id', $odpTypeId)->get(['id', 'code', 'name']) as $node) {
            $current = $allNodes->get($node->id);
            $visited = [];
            while ($current !== null && $current->anchor_pop_id === null && ! isset($visited[$current->id])) {
                $visited[$current->id] = true;
                $current = $allNodes->get($current->parent_id);
            }
            $branchId = Pop::resolveCabangId($current?->anchor_pop_id);
            if ($branchId === null) {
                continue;
            }
            foreach (array_unique([$node->code, $node->name]) as $label) {
                $labels[$branchId.'|'.mb_strtolower(trim($label))][$node->id] = true;
            }
        }

        $ports = DB::table('network_node_ports')->get(['id', 'network_node_id', 'number'])->keyBy(fn (object $port): string => $port->network_node_id.'|'.$port->number);
        $claims = [];
        $customerClaims = [];
        $serials = [];
        $details = DB::table('customer_technical_details as details')
            ->join('customers', 'customers.id', '=', 'details.customer_id')
            ->get(['details.customer_id', 'details.odp_number', 'details.odp_port', 'details.router_or_ont_serial', 'customers.ont_sn', 'customers.pop_id']);
        foreach ($details as $detail) {
            $label = mb_strtolower(trim((string) $detail->odp_number));
            $number = trim((string) $detail->odp_port);
            $branchId = Pop::resolveCabangId($detail->pop_id);
            if ($branchId === null || $label === '' || ! ctype_digit($number) || (int) $number < 1) {
                continue;
            }
            $nodeIds = array_keys($labels[$branchId.'|'.$label] ?? []);
            if (count($nodeIds) !== 1) {
                continue;
            }
            $port = $ports->get($nodeIds[0].'|'.(int) $number);
            if ($port === null) {
                continue;
            }
            $claims[$port->id][$detail->customer_id] = true;
            $serials[$detail->customer_id] = trim((string) ($detail->router_or_ont_serial ?: $detail->ont_sn));
            $customerClaims[$detail->customer_id][$port->id] = true;
        }

        $inventorySerials = DB::table('inventory_serials as serials')
            ->join('items', 'items.id', '=', 'serials.item_id')
            ->join('item_categories', 'item_categories.id', '=', 'items.item_category_id')
            ->whereIn('serials.customer_id', array_keys($customerClaims))
            ->where('serials.status', 'installed')
            ->where('item_categories.code', 'modem_ont')
            ->orderByDesc('serials.installed_at')
            ->get(['serials.customer_id', 'serials.serial_number'])
            ->unique('customer_id')
            ->pluck('serial_number', 'customer_id');

        $customerDeviceSerials = DB::table('customer_devices')
            ->whereIn('customer_id', array_keys($customerClaims))
            ->whereNull('device_retrieved_at')
            ->whereRaw('LOWER(device_type) IN (?, ?, ?)', ['ont', 'onu', 'modem'])
            ->pluck('serial_number', 'customer_id');

        foreach ($claims as $portId => $customers) {
            if (count($customers) !== 1) {
                continue;
            }
            $customerId = (int) array_key_first($customers);
            if (count($customerClaims[$customerId]) !== 1) {
                continue;
            }
            $port = DB::table('network_node_ports')->where('id', $portId)->lockForUpdate()->first();
            $activePortAssignment = DB::table('network_port_assignments')
                ->where('network_node_port_id', $portId)->where('active_key', 1)->first();
            if ($activePortAssignment !== null && (int) $activePortAssignment->customer_id !== $customerId) {
                continue;
            }
            if ($activePortAssignment === null) {
                if ($port->is_used || DB::table('network_port_assignments')->where('customer_id', $customerId)->where('active_key', 1)->exists()) {
                    continue;
                }
                DB::table('network_port_assignments')->insert([
                    'network_node_port_id' => $portId,
                    'customer_id' => $customerId,
                    'active_key' => 1,
                    'assigned_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('network_node_ports')->where('id', $portId)->update(['is_used' => true, 'updated_at' => now()]);
            }

            if (($serials[$customerId] ?? '') === ''
                && ! $inventorySerials->has($customerId)
                && trim((string) $customerDeviceSerials->get($customerId)) === '') {
                continue;
            }

            $odp = NetworkNode::findOrFail($port->network_node_id);
            $dropCode = "CUST-{$customerId}-DC";
            $this->node(
                $dropCode,
                "Drop cable pelanggan {$customerId}",
                $dropCableTypeId,
                $odp,
                null,
                ["TOPO-CUST-{$customerId}-DC"]
            );
        }
    }
}
