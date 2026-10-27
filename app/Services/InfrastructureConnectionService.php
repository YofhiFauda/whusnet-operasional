<?php

namespace App\Services;

use App\Enums\InfrastructureOccupancy;
use App\Enums\InfrastructurePortKind;
use App\Enums\InfrastructureRecordStatus;
use App\Models\AuditLog;
use App\Models\InfrastructureConnection;
use App\Models\NetworkNode;
use App\Models\Pop;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Satu-satunya penulis `infrastructure_connections`. Sejak ADHOC-213 Slot/PON/Port
 * Switch adalah baris `network_nodes`, dan aturannya menyempit:
 *
 * - ODP -> PON TIDAK lewat sambungan: ODP tergantung PON lewat `parent_id`
 *   (uplink = induk, lihat `NetworkNode::uplinkPon()`). Konsep "uplink
 *   terverifikasi" sengaja dibuang supaya tidak ada dua sumber kebenaran
 *   uplink yang bisa menyimpang. Karena itu PON ditolak sebagai sumber.
 * - Sambungan hanya untuk Port Switch -> Port Switch / node (non-ODP) / label.
 *   Satu sambungan aktif per port; tujuan TIDAK PERNAH ODP — jalur Switch
 *   berhenti di Port. Koneksi juga tidak pernah mengubah `parent_id` node.
 * - Sumber & tujuan wajib satu Cabang; port rusak tidak bisa disambung.
 * - Pelepasan menyimpan histori (released_at, alasan), tidak menghapus baris.
 *
 * POP scope aktor dicek pemanggil (controller/laporan) lewat resolver lokasi.
 */
class InfrastructureConnectionService
{
    public function connect(
        NetworkNode $source,
        User $actor,
        ?NetworkNode $targetNode = null,
        ?NetworkNode $targetPort = null,
        ?string $targetLabel = null,
        bool $verified = false,
        ?string $notes = null,
    ): InfrastructureConnection {
        $targetLabel = $targetLabel === null ? null : trim($targetLabel);
        $targetLabel = $targetLabel === '' ? null : $targetLabel;

        if (count(array_filter([$targetNode, $targetPort, $targetLabel], static fn ($value) => $value !== null)) !== 1) {
            throw new InvalidArgumentException('Tujuan sambungan wajib tepat satu: node, port, atau label.');
        }

        return DB::transaction(function () use ($source, $actor, $targetNode, $targetPort, $targetLabel, $verified, $notes): InfrastructureConnection {
            $source = NetworkNode::query()->with('nodeType')->lockForUpdate()->findOrFail($source->id);
            if ($source->isPon()) {
                throw new InvalidArgumentException('ODP tersambung ke PON lewat induknya, bukan sambungan.');
            }
            if ($source->portKind() !== InfrastructurePortKind::SWITCH) {
                throw new InvalidArgumentException('Sumber sambungan harus Port Switch.');
            }
            $this->assertUsable($source);
            $sourceCabang = $this->cabangOfPort($source);

            if (InfrastructureConnection::query()->active()->where('source_infrastructure_port_id', $source->id)->exists()) {
                throw new InvalidArgumentException("{$source->displayName()} ini sudah punya sambungan aktif.");
            }

            if ($targetNode !== null) {
                $targetNode = NetworkNode::query()->with('nodeType')->lockForUpdate()->findOrFail($targetNode->id);
                if ($targetNode->nodeType?->code === 'odp') {
                    throw new InvalidArgumentException('Jalur Switch berhenti di Port — tujuan tidak boleh ODP.');
                }
                if ($sourceCabang === null || $sourceCabang !== Pop::resolveCabangId($targetNode->resolvePop()?->id)) {
                    throw new InvalidArgumentException('Tujuan sambungan berada di cabang berbeda.');
                }
                if (InfrastructureConnection::query()->active()->where('target_network_node_id', $targetNode->id)->exists()) {
                    throw new InvalidArgumentException('Node tujuan sudah punya sambungan aktif.');
                }
            }

            if ($targetPort !== null) {
                $targetPort = NetworkNode::query()->with('nodeType')->lockForUpdate()->findOrFail($targetPort->id);
                if ($targetPort->portKind() !== InfrastructurePortKind::SWITCH) {
                    throw new InvalidArgumentException('Port tujuan harus Port Switch.');
                }
                $this->assertUsable($targetPort);
                if ($targetPort->id === $source->id) {
                    throw new InvalidArgumentException('Port tidak dapat disambungkan ke dirinya sendiri.');
                }
                if ($sourceCabang === null || $sourceCabang !== $this->cabangOfPort($targetPort)) {
                    throw new InvalidArgumentException('Tujuan sambungan berada di cabang berbeda.');
                }
                if (InfrastructureConnection::query()->active()->where('target_infrastructure_port_id', $targetPort->id)->exists()) {
                    throw new InvalidArgumentException("{$targetPort->displayName()} tujuan sudah punya sambungan aktif.");
                }
            }

            try {
                $connection = InfrastructureConnection::create([
                    'source_infrastructure_port_id' => $source->id,
                    'target_network_node_id' => $targetNode?->id,
                    'target_infrastructure_port_id' => $targetPort?->id,
                    'target_label' => $targetLabel,
                    'verification_status' => $verified ? InfrastructureConnection::VERIFIED : InfrastructureConnection::UNVERIFIED,
                    'active_key' => 1,
                    'created_by' => $actor->id,
                    'verified_by' => $verified ? $actor->id : null,
                    'verified_at' => $verified ? now() : null,
                    'notes' => $notes,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw new InvalidArgumentException('Tujuan sambungan sudah punya sambungan aktif.');
            }

            $this->audit($connection, 'connect', $actor, null, $connection->only([
                'source_infrastructure_port_id', 'target_network_node_id', 'target_infrastructure_port_id', 'target_label', 'verification_status',
            ]));

            return $connection;
        });
    }

    public function verify(InfrastructureConnection $connection, User $actor): InfrastructureConnection
    {
        return DB::transaction(function () use ($connection, $actor): InfrastructureConnection {
            $connection = InfrastructureConnection::query()->lockForUpdate()->findOrFail($connection->id);
            if ($connection->active_key !== 1) {
                throw new InvalidArgumentException('Sambungan sudah dilepas, tidak bisa diverifikasi.');
            }
            if ($connection->isVerified()) {
                return $connection;
            }

            $connection->update([
                'verification_status' => InfrastructureConnection::VERIFIED,
                'verified_by' => $actor->id,
                'verified_at' => now(),
            ]);
            $this->audit($connection, 'verify', $actor, ['verification_status' => InfrastructureConnection::UNVERIFIED], ['verification_status' => InfrastructureConnection::VERIFIED]);

            return $connection;
        });
    }

    public function release(InfrastructureConnection $connection, User $actor, string $reason): InfrastructureConnection
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Alasan pelepasan sambungan wajib diisi.');
        }

        return DB::transaction(function () use ($connection, $actor, $reason): InfrastructureConnection {
            $connection = InfrastructureConnection::query()->lockForUpdate()->findOrFail($connection->id);
            if ($connection->active_key !== 1) {
                throw new InvalidArgumentException('Sambungan sudah dilepas.');
            }

            $connection->update([
                'active_key' => null,
                'released_by' => $actor->id,
                'released_at' => now(),
                'release_note' => trim($reason),
            ]);
            $this->audit($connection, 'release', $actor, ['active' => true], ['active' => false, 'release_note' => trim($reason)]);

            return $connection;
        });
    }

    public function occupancyOf(NetworkNode $port): InfrastructureOccupancy
    {
        if ($port->record_status === InfrastructureRecordStatus::RUSAK_NONAKTIF) {
            return InfrastructureOccupancy::RUSAK_NONAKTIF;
        }

        $active = InfrastructureConnection::query()->active()
            ->where(fn ($query) => $query->where('source_infrastructure_port_id', $port->id)
                ->orWhere('target_infrastructure_port_id', $port->id))
            ->get(['verification_status']);

        return $this->occupancyFrom($port, $active);
    }

    /**
     * Versi massal `occupancyOf()` untuk daftar Port Switch satu Slot (panel
     * Manajemen Node — Slot di Master Node): SATU query sambungan aktif untuk
     * semua port, bukan satu per port — Slot 48 port = 1 query. Aturannya
     * sengaja lewat `occupancyFrom()` yang sama supaya status di panel dan di
     * halaman Detail Port tidak pernah berbeda.
     *
     * @param  iterable<NetworkNode>  $ports
     * @return array<int, InfrastructureOccupancy>
     */
    public function occupanciesOf(iterable $ports): array
    {
        $ports = collect($ports)->keyBy('id');
        if ($ports->isEmpty()) {
            return [];
        }

        $ids = $ports->keys()->all();
        $connections = InfrastructureConnection::query()->active()
            ->where(fn ($query) => $query->whereIn('source_infrastructure_port_id', $ids)
                ->orWhereIn('target_infrastructure_port_id', $ids))
            ->get(['source_infrastructure_port_id', 'target_infrastructure_port_id', 'verification_status']);

        return $ports->map(fn (NetworkNode $port): InfrastructureOccupancy => $this->occupancyFrom($port, $connections->filter(
            fn (InfrastructureConnection $connection): bool => (int) $connection->source_infrastructure_port_id === $port->id
                || (int) $connection->target_infrastructure_port_id === $port->id,
        )))->all();
    }

    /**
     * @param  iterable<InfrastructureConnection>  $active  sambungan aktif yang menyentuh port ini
     */
    private function occupancyFrom(NetworkNode $port, iterable $active): InfrastructureOccupancy
    {
        if ($port->record_status === InfrastructureRecordStatus::RUSAK_NONAKTIF) {
            return InfrastructureOccupancy::RUSAK_NONAKTIF;
        }

        $active = collect($active);
        if ($active->isNotEmpty()) {
            return $active->contains(fn (InfrastructureConnection $connection): bool => $connection->isVerified())
                ? InfrastructureOccupancy::TERPAKAI_TERVERIFIKASI
                : InfrastructureOccupancy::TERPAKAI_BELUM_TERVERIFIKASI;
        }

        // Kolom record_status nullable di node: null diperlakukan belum didata (bukan kosong terverifikasi).
        return ($port->record_status ?? InfrastructureRecordStatus::BELUM_DIDATA) === InfrastructureRecordStatus::BELUM_DIDATA
            ? InfrastructureOccupancy::BELUM_DIDATA
            : InfrastructureOccupancy::KOSONG_TERVERIFIKASI;
    }

    private function assertUsable(NetworkNode $port): void
    {
        if ($port->record_status === InfrastructureRecordStatus::RUSAK_NONAKTIF) {
            throw new InvalidArgumentException("{$port->displayName()} berstatus rusak/nonaktif.");
        }
    }

    private function cabangOfPort(NetworkNode $port): ?int
    {
        // Cabang dari rantai parent_id (Port -> Slot -> perangkat -> anchor).
        return Pop::resolveCabangId($port->resolvePop()?->id);
    }

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    private function audit(InfrastructureConnection $connection, string $action, User $actor, ?array $old, ?array $new): void
    {
        AuditLog::create([
            'user_id' => $actor->id,
            'module' => 'Koneksi Infrastruktur',
            'action' => $action,
            'auditable_type' => InfrastructureConnection::class,
            'auditable_id' => $connection->id,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'created_at' => now(),
        ]);
    }
}
