<?php

namespace App\Events;

use App\Models\Customer;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Satu-satunya event API 1 (webhook pemasangan, docs/api/business-logic.md).
 * Dipicu SATU tempat: CustomerInstallationController::storePemasangan(), saat
 * tombol "Aktivasi Laporan Speedtest" ditekan — bukan Mulai Pemasangan, bukan
 * penyelesaian laporan. Titik itu satu-satunya saat SN/ODP baru saja tersimpan
 * DAN pemasangan fisik baru rampung.
 *
 * BUKAN ShouldBroadcast — beda dari InstallationCompleted/InstallationStarted
 * yang melayani dashboard realtime FOP. Event ini murni memicu pipeline
 * webhook eksternal (listener → outbox → job kirim), jangan dicampur dengan
 * event dashboard yang bisa berubah arti kapan saja tanpa ada yang ingat
 * webhook ikut mendengarkan.
 */
class InstallationActivated
{
    use Dispatchable, SerializesModels;

    public function __construct(public Customer $customer) {}
}
