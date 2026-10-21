<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\InfrastructurePort;
use App\Models\InfrastructureSlot;
use App\Models\NetworkNode;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Reset topologi yang TERVERIFIKASI dummy (§16.4 pengantar) — hanya node
 * yang kodenya disebut eksplisit. Ditolak bila node/posisi turunannya pernah
 * menyentuh data nyata: assignment pelanggan atau sambungan (termasuk
 * histori), serial/ledger, saldo, koneksi kanonis, aksi laporan, uplink POP,
 * atau punya anak yang tidak ikut terdaftar. Default dry-run.
 *
 * Audit Task 1: lingkungan aplikasi saat ini BUKAN dummy (ODP-JETIS-01
 * memegang pelanggan) — service ini akan menolaknya.
 */
class InfrastructureDummyResetService
{
    /**
     * @param  list<string>  $codes
     * @return array{nodes: list<string>, slots: int, ports: int}
     */
    public function reset(array $codes, User $actor, bool $dryRun = true): array
    {
        return DB::transaction(function () use ($codes, $actor, $dryRun): array {
            $nodes = DB::table('network_nodes')->whereIn('code', $codes)->lockForUpdate()->get(['id', 'code', 'parent_id']);
            $missing = array_diff($codes, $nodes->pluck('code')->all());
            if ($missing !== []) {
                throw new RuntimeException('Kode node tidak ditemukan: '.implode(', ', $missing));
            }
            $nodeIds = $nodes->pluck('id')->all();

            $foreignChildren = DB::table('network_nodes')->whereIn('parent_id', $nodeIds)->whereNotIn('id', $nodeIds)->pluck('code');
            if ($foreignChildren->isNotEmpty()) {
                throw new RuntimeException('Node memiliki anak yang tidak terdaftar untuk reset: '.$foreignChildren->implode(', '));
            }

            $legacySlotIds = DB::table('olt_slots')->whereIn('chassis_node_id', $nodeIds)->lockForUpdate()->pluck('id');
            if (DB::table('infrastructure_slots')->whereIn('legacy_olt_slot_id', $legacySlotIds)->whereNotIn('network_node_id', $nodeIds)->exists()) {
                throw new RuntimeException('Slot lama dirujuk posisi kanonis di luar reset.');
            }

            $nodePortIds = DB::table('network_node_ports')->whereIn('network_node_id', $nodeIds)->pluck('id');
            $slotIds = DB::table('infrastructure_slots')->whereIn('network_node_id', $nodeIds)->pluck('id');
            $portIds = DB::table('infrastructure_ports')->whereIn('infrastructure_slot_id', $slotIds)->pluck('id');

            $references = [
                'assignment pelanggan/sambungan port' => DB::table('network_port_assignments')
                    ->where(fn ($query) => $query->whereIn('network_node_port_id', $nodePortIds)->orWhereIn('target_node_id', $nodeIds)->orWhereIn('target_port_id', $nodePortIds))->exists(),
                'serial Gudang' => DB::table('inventory_serials')->where(fn ($query) => $query->whereIn('current_network_node_id', $nodeIds)
                    ->orWhereIn('current_infrastructure_slot_id', $slotIds)->orWhereIn('current_infrastructure_port_id', $portIds))->exists(),
                'ledger Gudang' => DB::table('inventory_transactions')->where(fn ($query) => $query->whereIn('to_network_node_id', $nodeIds)->orWhereIn('from_network_node_id', $nodeIds)
                    ->orWhereIn('to_infrastructure_slot_id', $slotIds)->orWhereIn('from_infrastructure_slot_id', $slotIds)
                    ->orWhereIn('to_infrastructure_port_id', $portIds)->orWhereIn('from_infrastructure_port_id', $portIds))->exists(),
                'saldo infrastruktur' => DB::table('infrastructure_balances')->where(fn ($query) => $query->whereIn('network_node_id', $nodeIds)
                    ->orWhereIn('infrastructure_slot_id', $slotIds)->orWhereIn('infrastructure_port_id', $portIds))->exists(),
                'koneksi infrastruktur' => DB::table('infrastructure_connections')->where(fn ($query) => $query->whereIn('target_network_node_id', $nodeIds)
                    ->orWhereIn('source_infrastructure_port_id', $portIds)->orWhereIn('target_infrastructure_port_id', $portIds))->exists(),
                'aksi laporan' => DB::table('task_infrastructure_actions')->where(fn ($query) => $query->whereIn('network_node_id', $nodeIds)
                    ->orWhereIn('work_infrastructure_slot_id', $slotIds)->orWhereIn('work_infrastructure_port_id', $portIds))->exists(),
                'uplink POP' => DB::table('pops')->whereIn('uplink_node_id', $nodeIds)->exists(),
                'keputusan mapping legacy' => DB::table('infrastructure_legacy_mappings')
                    ->where(fn ($query) => $query
                        ->where(fn ($nodes) => $nodes->whereIn('legacy_type', ['pon_port_node', 'aggregation_switch_node', 'odp_uplink', 'olt_card_node'])->whereIn('legacy_id', $nodeIds))
                        ->orWhere(fn ($slots) => $slots->where('legacy_type', 'olt_slot')->whereIn('legacy_id', $legacySlotIds)))
                    ->where(fn ($query) => $query->whereNotNull('decision')->orWhereNotNull('decided_by')->orWhereNotNull('decided_at')
                        ->orWhere('status', 'rejected')
                        ->orWhere(fn ($manual) => $manual->where('status', 'mapped')->whereNotIn('legacy_type', ['olt_card_node', 'olt_slot'])))
                    ->exists(),
                'riwayat audit node' => DB::table('audit_logs')
                    ->where('auditable_type', NetworkNode::class)->whereIn('auditable_id', $nodeIds)
                    ->whereNotIn('action', ['create', 'created'])->exists(),
                'riwayat audit posisi' => DB::table('audit_logs')
                    ->where(fn ($query) => $query
                        ->where(fn ($slots) => $slots->where('auditable_type', InfrastructureSlot::class)->whereIn('auditable_id', $slotIds))
                        ->orWhere(fn ($ports) => $ports->where('auditable_type', InfrastructurePort::class)->whereIn('auditable_id', $portIds)))
                    ->whereNotIn('action', ['create', 'created'])->exists(),
                'ujung koneksi laporan' => DB::table('task_infrastructure_actions')->where(fn ($query) => $query
                    ->whereIn('end_from_network_node_id', $nodeIds)->orWhereIn('end_to_network_node_id', $nodeIds)
                    ->orWhereIn('end_from_infrastructure_port_id', $portIds)->orWhereIn('end_to_infrastructure_port_id', $portIds))->exists(),
                'akar scope Task FOP' => DB::table('fop_tasks')->where(fn ($query) => $query->whereIn('scope_network_node_id', $nodeIds)
                    ->orWhereIn('scope_infrastructure_slot_id', $slotIds)->orWhereIn('scope_infrastructure_port_id', $portIds))->exists(),
            ];
            $blocking = array_keys(array_filter($references));
            if ($blocking !== []) {
                throw new RuntimeException('Reset ditolak — node menyentuh data nyata: '.implode(', ', $blocking).'.');
            }

            $plan = ['nodes' => $nodes->pluck('code')->sort()->values()->all(), 'slots' => $slotIds->count(), 'ports' => $portIds->count()];
            if ($dryRun) {
                return $plan;
            }

            DB::table('infrastructure_ports')->whereIn('id', $portIds)->delete();
            DB::table('infrastructure_slots')->whereIn('id', $slotIds)->delete();
            DB::table('olt_slots')->whereIn('card_node_id', $nodeIds)->update(['card_node_id' => null]);
            DB::table('olt_slots')->whereIn('id', $legacySlotIds)->delete();
            DB::table('infrastructure_legacy_mappings')->where('legacy_type', 'olt_slot')->whereIn('legacy_id', $legacySlotIds)->delete();
            DB::table('network_node_ports')->whereIn('id', $nodePortIds)->delete();
            DB::table('infrastructure_legacy_mappings')->whereIn('legacy_id', $nodeIds)
                ->whereIn('legacy_type', ['pon_port_node', 'aggregation_switch_node', 'odp_uplink', 'olt_card_node'])->delete();
            // Hapus dari daun ke akar supaya FK parent_id (restrict) aman.
            $remaining = $nodeIds;
            while ($remaining !== []) {
                $leaves = DB::table('network_nodes')->whereIn('id', $remaining)
                    ->whereNotIn('id', DB::table('network_nodes')->whereIn('parent_id', $remaining)->select('parent_id'))->pluck('id')->all();
                if ($leaves === []) {
                    throw new RuntimeException('Rantai parent melingkar pada node yang akan direset.');
                }
                DB::table('network_nodes')->whereIn('id', $leaves)->delete();
                $remaining = array_values(array_diff($remaining, $leaves));
            }

            AuditLog::create([
                'user_id' => $actor->id, 'module' => 'Node Jaringan', 'action' => 'dummy_topology_reset',
                'auditable_type' => 'network_topology', 'auditable_id' => 0,
                'old_values' => $plan, 'new_values' => null,
                'ip_address' => request()?->ip(), 'user_agent' => request()?->userAgent(), 'created_at' => now(),
            ]);

            return $plan;
        });
    }
}
