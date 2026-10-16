<?php

use App\Models\Pop;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Sengaja TIDAK lewat `NetworkNode::resolvePop()`: method itu naik
        // rantai `parent`/`anchorPop` secara lazy, dan begitu node datang dari
        // koleksi >1 baris, `Model::preventLazyLoading()` (aktif di luar
        // production) melempar LazyLoadingViolationException — migrate di
        // data nyata gagal walau test 1-node lolos. Rantai parent di-walk dari
        // peta tabel mentah supaya migration juga tidak ikut berubah perilaku
        // kalau model NetworkNode diubah belakangan.
        $allNodes = DB::table('network_nodes')->get(['id', 'parent_id', 'anchor_pop_id'])->keyBy('id');
        $odpTypeIds = DB::table('network_node_types')->where('code', 'odp')->pluck('id');

        $nodesByBranchAndLabel = [];
        DB::table('network_nodes')->whereIn('node_type_id', $odpTypeIds)->get(['id', 'code', 'name'])
            ->each(function ($node) use (&$nodesByBranchAndLabel, $allNodes): void {
                $current = $allNodes->get($node->id);
                $visited = [];
                while ($current !== null && $current->anchor_pop_id === null && ! isset($visited[$current->id])) {
                    $visited[$current->id] = true;
                    $current = $current->parent_id !== null ? $allNodes->get($current->parent_id) : null;
                }

                $branchId = Pop::resolveCabangId($current?->anchor_pop_id);
                if ($branchId === null) {
                    return;
                }

                foreach (array_unique(array_filter([$node->code, $node->name], fn ($label) => $label !== null)) as $label) {
                    $key = $branchId.'|'.mb_strtolower(trim($label));
                    $nodesByBranchAndLabel[$key][] = $node->id;
                }
            });

        $claims = [];
        $customerClaims = [];
        DB::table('customer_technical_details as details')
            ->join('customers', 'customers.id', '=', 'details.customer_id')
            ->select('details.customer_id', 'details.odp_number', 'details.odp_port', 'customers.pop_id')
            ->orderBy('details.id')
            ->chunk(500, function ($details) use (&$claims, &$customerClaims, $nodesByBranchAndLabel): void {
                foreach ($details as $detail) {
                    $label = mb_strtolower(trim((string) $detail->odp_number));
                    $portText = trim((string) $detail->odp_port);
                    $branchId = Pop::resolveCabangId($detail->pop_id);
                    if ($branchId === null || $label === '' || ! ctype_digit($portText) || (int) $portText < 1) {
                        continue;
                    }

                    $nodes = array_unique($nodesByBranchAndLabel[$branchId.'|'.$label] ?? []);
                    if (count($nodes) !== 1) {
                        continue;
                    }

                    $portId = DB::table('network_node_ports')
                        ->where('network_node_id', reset($nodes))
                        ->where('number', (int) $portText)
                        ->value('id');
                    if ($portId === null) {
                        continue;
                    }

                    $claims[$portId][$detail->customer_id] = true;
                    $customerClaims[$detail->customer_id][$portId] = true;
                }
            });

        foreach ($claims as $portId => $customers) {
            if (count($customers) !== 1) {
                continue;
            }
            $customerId = (int) array_key_first($customers);
            if (count($customerClaims[$customerId]) !== 1) {
                continue;
            }

            DB::transaction(function () use ($portId, $customerId): void {
                $port = DB::table('network_node_ports')->where('id', $portId)->lockForUpdate()->first();
                if ($port === null || DB::table('network_port_assignments')
                    ->where('customer_id', $customerId)->where('active_key', 1)->exists()) {
                    return;
                }

                $active = DB::table('network_port_assignments')
                    ->where('network_node_port_id', $portId)->where('active_key', 1)->lockForUpdate()->first();
                if ($active !== null) {
                    if ($active->customer_id !== null || $active->target_node_id !== null
                        || $active->manual_label !== 'Terpakai (data lama; perlu verifikasi)') {
                        return;
                    }
                    DB::table('network_port_assignments')->where('id', $active->id)->update([
                        'customer_id' => $customerId,
                        'manual_label' => null,
                        'updated_at' => now(),
                    ]);

                    return;
                }
                if ($port->is_used) {
                    return;
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
            });
        }
    }

    public function down(): void
    {
        // Penempatan pelanggan yang telah diverifikasi tidak aman dibatalkan otomatis.
    }
};
