<?php

namespace App\Services;

use App\Enums\CustodyStatus;
use App\Enums\InventoryTransactionType;
use App\Enums\RollStatus;
use App\Enums\SerialStatus;
use App\Models\InventoryRoll;
use App\Models\InventorySerial;
use App\Models\InventoryTransaction;
use App\Models\Pop;
use App\Models\TechnicianCustody;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Auto-expire barang di tangan teknisi (laporan user 2026-11-05, gap #1 dari
 * 3 feature report "Barang di Tangan Teknisi akan hilang dalam 24 jam atau
 * ganti hari") — custody QUANTITY (`technician_custody`), unit SERIALIZED
 * (`inventory_serials` status ISSUED), dan Roll kabel (`inventory_rolls`
 * status ISSUED/IN_USE) yang sudah GANTI HARI sejak diambil AUTO-DIKREDIT ke
 * stok Gudang Cabang asal, TANPA konfirmasi admin/teknisi (keputusan user
 * 2026-11-06 — opsi "auto presumptif", menolak opsi "tunggu konfirmasi" yang
 * dipertimbangkan sebelumnya karena gak masuk akal minta PIC/teknisi tap
 * tombol tengah malam).
 *
 * "24 jam ATAU ganti hari" (kalimat laporan user) secara matematis SELALU
 * resolve ke "ganti hari": tengah malam setelah waktu ambil SELALU jatuh
 * dalam ≤24 jam dan SELALU lebih dulu — satu rule "tanggal lokal saat
 * diambil < tanggal lokal sekarang" sudah mencakup DUA requirement itu
 * sekaligus.
 *
 * SN & Roll yang lewat jalur ini SENGAJA TIDAK dipaksa "belum dicek fisik"
 * (keputusan user 2026-11-06, koreksi atas desain awal): beda dari barang
 * yang balik lewat DEACT/ganti modem (SUDAH terpasang/dipakai di lokasi
 * pelanggan, kondisinya genuinely gak diketahui, WAJIB dicek staf fisik dulu
 * — jalur itu gak lewat service ini sama sekali), kandidat di sini masih
 * berstatus ISSUED/IN_USE: belum pernah naik ke INSTALLED/dipotong habis,
 * artinya belum pernah benar-benar dipakai di lapangan — cuma nganggur lama
 * di tangan teknisi abis kerjaan beres. Gak ada alasan nyuruh cek ulang
 * barang yang belum pernah disentuh buat kerjaan.
 *
 * KONSEKUENSI YANG DITERIMA SADAR (bukan auto-kredit SEMBARANGAN): beda dari
 * jalur RETURN manual (resign/cuti/DEAC) yang selalu ada manusia yang
 * menyaksikan fisik barang, baris ini TIDAK — stok Cabang bisa SEMENTARA
 * meleset dari fisik sampai ketauan Stock Opname berikutnya. Mitigasi:
 *   1. Ledger ditandai jelas (`reason` = `auto_expired_presumptive`, BUKAN
 *      `resign`/`cuti`/dll manual) — gampang dibedakan & diaudit terpisah,
 *      selisihnya nanti masuk `shrinkage_on_return` kalau opname nemu beda.
 *   2. Lewat `InventoryReassignService::returnToWarehouse()`/
 *      `returnSerialToWarehouse()`/`returnRollToWarehouse()` yang SUDAH ADA
 *      (bukan nulis ledger sendiri) — satu jalur kredit stok, bukan jalur
 *      ketiga yang gampang menyimpang dari yang sudah ada.
 *   3. Satu baris gagal (mis. cabang bukan warehouse) TIDAK boleh
 *      menggagalkan baris lain di batch yang sama — tiap baris diproses
 *      dalam transaksi SENDIRI, kegagalan dicatat `Log::warning()` dan
 *      dilewati, bukan melempar exception yang menghentikan seluruh job.
 */
class TechnicianCustodyExpiryService
{
    private const REASON = 'auto_expired_presumptive';

    private const NOTES = 'Otomatis: lewat 24 jam/ganti hari, belum dikembalikan/dipakai teknisi. Dikreditkan presumptif ke stok Cabang tanpa konfirmasi fisik — verifikasi lewat Stock Opname kalau ada selisih.';

    /**
     * @return array{custody: int, serial: int, roll: int} jumlah baris yang berhasil di-auto-return
     */
    public function expireOverdue(): array
    {
        return [
            'custody' => $this->autoReturnOverdueCustody(),
            'serial' => $this->autoReturnOverdueSerials(),
            'roll' => $this->autoReturnOverdueRolls(),
        ];
    }

    private function autoReturnOverdueCustody(): int
    {
        $candidates = TechnicianCustody::query()
            ->active()
            ->whereIn('status', [CustodyStatus::ISSUED->value, CustodyStatus::PARTIALLY_USED->value])
            ->whereDate('issued_at', '<', now()->toDateString())
            ->get();

        $count = 0;
        foreach ($candidates as $custody) {
            try {
                DB::transaction(function () use ($custody): void {
                    $custody->update(['expired_at' => now()]);
                    $cabang = Pop::findOrFail($custody->issued_from_pop_id);
                    app(InventoryReassignService::class)->returnToWarehouse(
                        $custody, $cabang, self::REASON, $this->systemActor(), self::NOTES,
                    );
                });
                $count++;
            } catch (Throwable $e) {
                Log::warning('Auto-expire custody gagal, dilewati.', ['custody_id' => $custody->id, 'error' => $e->getMessage()]);
            }
        }

        return $count;
    }

    /**
     * SN tidak punya kolom "issued_at" sendiri (lihat migration
     * `create_inventory_serials_table`) — "sejak kapan dipegang" diturunkan
     * dari transaksi ISSUE TERAKHIR di ledger per SN (teknik sama dengan
     * `WarehouseCustodyController::$serialIssuers`), bukan `updated_at` yang
     * gampang basi oleh update lain yang gak terkait custody.
     */
    private function autoReturnOverdueSerials(): int
    {
        $candidateIds = InventorySerial::query()
            ->where('status', SerialStatus::ISSUED->value)
            ->whereNotNull('current_technician_id')
            ->pluck('id');

        if ($candidateIds->isEmpty()) {
            return 0;
        }

        $expiredIds = $this->filterOverdueByLatestIssue($candidateIds, 'serial_id');

        $count = 0;
        foreach ($expiredIds as $serialId) {
            $serial = InventorySerial::query()->find($serialId);
            if ($serial === null) {
                continue;
            }

            try {
                DB::transaction(function () use ($serial): void {
                    $serial->update(['expired_at' => now()]);
                    $cabang = Pop::findOrFail($serial->issued_from_pop_id ?? $serial->current_pop_id);
                    app(InventoryReassignService::class)->returnSerialToWarehouse(
                        $serial, $cabang, self::REASON, $this->systemActor(), self::NOTES,
                    );
                });
                $count++;
            } catch (Throwable $e) {
                Log::warning('Auto-expire SN gagal, dilewati.', ['serial_id' => $serial->id, 'error' => $e->getMessage()]);
            }
        }

        return $count;
    }

    /**
     * Roll gak punya "issued_at" sendiri juga — sama perlakuan dengan SN,
     * diturunkan dari ledger ISSUE terakhir per roll.
     */
    private function autoReturnOverdueRolls(): int
    {
        $candidateIds = InventoryRoll::query()
            ->whereIn('status', [RollStatus::ISSUED->value, RollStatus::IN_USE->value])
            ->whereNotNull('current_technician_id')
            ->pluck('id');

        if ($candidateIds->isEmpty()) {
            return 0;
        }

        $expiredIds = $this->filterOverdueByLatestIssue($candidateIds, 'roll_id');

        $count = 0;
        foreach ($expiredIds as $rollId) {
            $roll = InventoryRoll::query()->find($rollId);
            if ($roll === null) {
                continue;
            }

            try {
                DB::transaction(function () use ($roll): void {
                    $roll->update(['expired_at' => now()]);
                    $cabang = Pop::findOrFail($roll->issued_from_pop_id ?? $roll->current_pop_id);
                    app(InventoryReassignService::class)->returnRollToWarehouse(
                        $roll, $cabang, self::REASON, $this->systemActor(), self::NOTES,
                    );
                });
                $count++;
            } catch (Throwable $e) {
                Log::warning('Auto-expire roll gagal, dilewati.', ['roll_id' => $roll->id, 'error' => $e->getMessage()]);
            }
        }

        return $count;
    }

    /**
     * @param  Collection<int, int>  $candidateIds
     * @return Collection<int, int> id kandidat yang ISSUE terakhirnya < hari ini
     */
    private function filterOverdueByLatestIssue($candidateIds, string $foreignKey)
    {
        $latestIssueIds = InventoryTransaction::query()
            ->selectRaw('MAX(id) as id')
            ->where('type', InventoryTransactionType::ISSUE->value)
            ->whereIn($foreignKey, $candidateIds)
            ->groupBy($foreignKey);

        $issuedAtById = InventoryTransaction::query()
            ->whereIn('id', $latestIssueIds)
            ->get([$foreignKey, 'created_at'])
            ->pluck('created_at', $foreignKey);

        $today = now()->toDateString();

        return $issuedAtById
            ->filter(fn ($createdAt) => $createdAt !== null && $createdAt->toDateString() < $today)
            ->keys();
    }

    /**
     * Pelaku tercatat di ledger buat aksi otomatis tanpa user login — pola
     * sama `TerminatedCustomerWriteOffService::systemActor()`.
     */
    private function systemActor(): User
    {
        $actor = User::query()->find(1) ?? User::query()->orderBy('id')->first();

        if (! $actor) {
            throw new RuntimeException('Tidak ada user untuk dicatat sebagai pelaku auto-expire custody.');
        }

        return $actor;
    }
}
