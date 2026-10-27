<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\NetworkNode;
use App\Models\NetworkNodePort;
use App\Models\NetworkPortAssignment;
use App\Models\Pop;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class NetworkPortAssignmentService
{
    public function assignCustomer(NetworkNodePort $port, Customer $customer, User $actor): NetworkPortAssignment
    {
        return $this->assign($port, $actor, customer: $customer);
    }

    public function assignNode(NetworkNodePort $port, NetworkNode $target, User $actor, ?NetworkNodePort $targetPort = null): NetworkPortAssignment
    {
        return $this->assign($port, $actor, target: $target, targetPort: $targetPort);
    }

    public function assignManual(NetworkNodePort $port, string $label, User $actor): NetworkPortAssignment
    {
        return $this->assign($port, $actor, label: $label);
    }

    public function release(NetworkNodePort $port, User $actor, string $reason): void
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Alasan pelepasan fisik wajib diisi.');
        }

        DB::transaction(function () use ($port, $actor, $reason): void {
            $port = NetworkNodePort::query()->lockForUpdate()->findOrFail($port->id);
            $active = $port->assignments()->where('active_key', 1)->lockForUpdate()->first();

            if ($active !== null) {
                $active->update([
                    'active_key' => null,
                    'released_at' => now(),
                    'released_by' => $actor->id,
                    'release_note' => trim($reason),
                ]);
            }

            if ($active?->target_port_id !== null) {
                NetworkNodePort::query()->whereKey($active->target_port_id)->update(['is_used' => false]);
            }
            if ($active?->customer_id !== null) {
                // Kolom legacy `customers.odp_code` dulu cuma keisi manual/laporan
                // teknisi — gak ikut ter-update jalur baru ini sebelum fix ini,
                // jadi Detail Pelanggan tetap nampilin ODP lama walau port sudah
                // dilepas. Disinkronkan di sini juga supaya release tanpa
                // assign ulang tidak membekukan nilai usang (temuan user 2026-10-22).
                Customer::query()->whereKey($active->customer_id)->update(['odp_code' => null]);
            }
            $port->update(['is_used' => false]);
        });
    }

    private function assign(
        NetworkNodePort $port,
        User $actor,
        ?Customer $customer = null,
        ?NetworkNode $target = null,
        ?NetworkNodePort $targetPort = null,
        ?string $label = null,
    ): NetworkPortAssignment {
        return DB::transaction(function () use ($port, $actor, $customer, $target, $targetPort, $label): NetworkPortAssignment {
            $port = NetworkNodePort::query()->with('node.nodeType')->lockForUpdate()->findOrFail($port->id);

            $active = $port->assignments()->where('active_key', 1)->lockForUpdate()->first();
            if ($customer !== null && $active?->customer_id === $customer->id) {
                return $active;
            }
            if ($port->is_used || $active !== null) {
                throw new InvalidArgumentException('Port sudah terpakai.');
            }

            $sourcePopId = Pop::resolveCabangId($port->node->resolvePop()?->id);
            if ($sourcePopId === null) {
                throw new InvalidArgumentException('Lokasi port tidak memiliki cabang yang valid.');
            }

            if ($customer !== null) {
                if ($port->node->nodeType?->code !== 'odp') {
                    throw new InvalidArgumentException('Pelanggan hanya boleh ditempatkan pada port ODP.');
                }
                if ($sourcePopId !== Pop::resolveCabangId($customer->pop_id)) {
                    throw new InvalidArgumentException('Pelanggan dan ODP berada di cabang berbeda.');
                }
                if (NetworkPortAssignment::query()->where('customer_id', $customer->id)->where('active_key', 1)->exists()) {
                    throw new InvalidArgumentException('Pelanggan sudah menempati port ODP lain.');
                }
            }

            if ($target !== null) {
                if ($target->parent_id !== $port->network_node_id) {
                    throw new InvalidArgumentException('Node tujuan harus anak langsung dari pemilik port.');
                }
                if ($sourcePopId !== Pop::resolveCabangId($target->resolvePop()?->id)) {
                    throw new InvalidArgumentException('Node tujuan berada di cabang berbeda.');
                }
                if ($target->id === $port->network_node_id) {
                    throw new InvalidArgumentException('Port tidak dapat diarahkan ke node pemiliknya sendiri.');
                }
                if (NetworkPortAssignment::query()->where('target_node_id', $target->id)->where('active_key', 1)->exists()) {
                    throw new InvalidArgumentException('Node tujuan sudah terhubung ke port lain.');
                }
            }

            if ($targetPort !== null) {
                if ($target === null || $targetPort->network_node_id !== $target->id) {
                    throw new InvalidArgumentException('Port tujuan bukan milik node tujuan.');
                }
                $targetPort = NetworkNodePort::query()->lockForUpdate()->findOrFail($targetPort->id);
                if ($targetPort->is_used || NetworkPortAssignment::query()
                    ->where('target_port_id', $targetPort->id)->where('active_key', 1)->exists()) {
                    throw new InvalidArgumentException('Port tujuan sudah terpakai.');
                }
            }

            $assignment = $port->assignments()->create([
                'customer_id' => $customer?->id,
                'target_node_id' => $target?->id,
                'target_port_id' => $targetPort?->id,
                'manual_label' => $label,
                'active_key' => 1,
                'assigned_at' => now(),
                'assigned_by' => $actor->id,
            ]);
            $port->update(['is_used' => true]);
            $targetPort?->update(['is_used' => true]);

            // `customers.odp_code` kolom legacy yang masih dibaca di beberapa
            // tempat (tab Paket & Layanan, CustomerDeviceProfileService
            // fallback) — tanpa ini, assign port ODP baru lewat Master Node/
            // Peta Infrastruktur gak kelihatan di sana, cuma di kartu "Lokasi
            // Jaringan" (bug temuan user 2026-10-22, Ardiyanto tetap nunjukin
            // ODP lama walau sudah di-assign ke ODP-JETIS-01 Port 1).
            if ($customer !== null) {
                $customer->update(['odp_code' => $port->node->code]);
            }

            return $assignment;
        });
    }
}
