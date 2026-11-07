<?php

namespace App\Services;

use App\Enums\PengajuanBarangStatus;
use App\Models\PengajuanBarang;
use App\Models\Pop;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Surat Pengajuan Barang (gap #2 dari 3 feature report 2026-11-05) — lihat
 * docblock migration `create_pengajuan_barang_table`. Satu keputusan
 * approval: FOP approve/reject. Reject FINAL (keputusan user 2026-11-06) —
 * TIDAK ada revisi di dokumen yang sama.
 */
class PengajuanBarangService
{
    /**
     * @param  array<int, array{nama_barang: string, spesifikasi: ?string, jumlah: float, satuan: string, keterangan_link: ?string}>  $lines
     */
    public function create(Pop $pop, string $namaKegiatan, array $lines, User $pengaju): PengajuanBarang
    {
        if (count($lines) === 0) {
            throw new InvalidArgumentException('Pengajuan harus punya minimal 1 baris barang.');
        }

        return DB::transaction(function () use ($pop, $namaKegiatan, $lines, $pengaju) {
            $pengajuan = PengajuanBarang::create([
                'nomor_request' => app(NumberSequenceService::class)->pengajuanBarangNumber(),
                'pengaju_id' => $pengaju->id,
                'nama_kegiatan' => $namaKegiatan,
                'pop_id' => $pop->id,
                'status' => PengajuanBarangStatus::MENUNGGU_FOP,
            ]);

            foreach ($lines as $index => $line) {
                $pengajuan->items()->create([
                    'nama_barang' => $line['nama_barang'],
                    'spesifikasi' => $line['spesifikasi'] ?? null,
                    'jumlah' => $line['jumlah'],
                    'satuan' => $line['satuan'],
                    'keterangan_link' => $line['keterangan_link'] ?? null,
                    'urutan' => $index,
                ]);
            }

            return $pengajuan;
        });
    }

    public function approve(PengajuanBarang $pengajuan, User $actor): PengajuanBarang
    {
        $this->assertMenungguFop($pengajuan);
        $this->assertIsDesignatedApprover($actor);

        $pengajuan->update([
            'status' => PengajuanBarangStatus::DISETUJUI,
            'decided_by' => $actor->id,
            'decided_at' => now(),
        ]);

        return $pengajuan;
    }

    public function reject(PengajuanBarang $pengajuan, string $reason, User $actor): PengajuanBarang
    {
        $this->assertMenungguFop($pengajuan);
        $this->assertIsDesignatedApprover($actor);

        $pengajuan->update([
            'status' => PengajuanBarangStatus::DITOLAK,
            'decided_by' => $actor->id,
            'decided_at' => now(),
            'rejection_reason' => $reason,
        ]);

        return $pengajuan;
    }

    private function assertMenungguFop(PengajuanBarang $pengajuan): void
    {
        if ($pengajuan->status !== PengajuanBarangStatus::MENUNGGU_FOP) {
            throw new InvalidArgumentException("Pengajuan {$pengajuan->nomor_request} statusnya '{$pengajuan->status->label()}' — sudah diputuskan, tidak bisa diputuskan ulang.");
        }
    }

    /**
     * Semua FOP (role 'fop') ALL_POP — Scope POP gak bisa membedakan "siapa
     * di antara role yang sama" (itu gunanya buat cabang). Keputusan user
     * 2026-11-07: cuma 1 FOP tertentu yang berwenang approve/reject, 3 FOP
     * lain cuma create+view. Gate ini DI SERVICE (bukan cuma controller/view)
     * supaya berlaku dari semua jalur masuk (artisan, tinker, dll) — sama
     * prinsip Observer/Service di CLAUDE.md.
     */
    private function assertIsDesignatedApprover(User $actor): void
    {
        if ($actor->hasFullAccess()) {
            return;
        }

        if (! $actor->is_pengajuan_barang_approver) {
            throw new InvalidArgumentException('Anda belum ditunjuk sebagai approver Pengajuan Barang. Hubungi Admin untuk penunjukan lewat Kelola User.');
        }
    }
}
