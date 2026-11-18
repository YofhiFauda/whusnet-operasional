<?php

namespace App\Enums;

/**
 * Status review FOP atas laporan DEAC yang GAGAL (modem tidak berhasil
 * diambil). Laporan `diambil` tidak pernah punya status ini (NULL) — modem
 * yang kembali diverifikasi gudang lewat Terima Retur (keputusan user K3).
 *
 * docs/plan/warehouse/analisa-review-penarikan-deac.md §3.1
 */
enum DeviceRetrievalReviewStatus: string
{
    case MENUNGGU = 'menunggu';
    case DITOLAK = 'ditolak';
    case DISETUJUI = 'disetujui';

    /**
     * Laporan yang masih MENUNGGU saat kasusnya berhenti tanpa keputusan FOP:
     * modem diantar pelanggan ke gudang, atau pelanggan Langganan Lagi.
     * Ditutup otomatis (DeviceRetrievalReviewService::closeOpenCase()) —
     * kalau dibiarkan MENUNGGU, laporan itu bisa ditolak (task DEAC untuk
     * modem yang sudah di gudang) atau jadi yatim (tidak tampil di halaman
     * review, tapi review generik di Detail Task juga diblokir).
     */
    case DITUTUP = 'ditutup';

    public function label(): string
    {
        return match ($this) {
            self::MENUNGGU => 'Menunggu Review',
            self::DITOLAK => 'Ditolak — Deact Diulang',
            self::DISETUJUI => 'Disetujui — Dibebaskan',
            self::DITUTUP => 'Ditutup — Kasus Berhenti',
        };
    }

    /**
     * Nilai `tasks.fop_review_status` yang mencerminkan keputusan ini
     * (keputusan user #5-A, 2026-11-18): task DEAC tidak tertahan di
     * `pending` setelah diputuskan, dan TaskObserver mencatat satu baris
     * riwayat FOP. Ditutup otomatis dianggap selesai (`approved`) — tidak
     * ada lagi yang perlu direview.
     */
    public function taskReviewStatus(): string
    {
        return match ($this) {
            self::MENUNGGU => 'pending',
            self::DITOLAK => 'rejected',
            self::DISETUJUI, self::DITUTUP => 'approved',
        };
    }
}
