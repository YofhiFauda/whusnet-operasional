<?php

namespace Tests\Unit;

use App\Jobs\MatchPaymentReceipt;
use Tests\TestCase;

/**
 * Gejala yang dijaga: pembacaan kwitansi kembali menumpang antrean `default`.
 *
 * `default` memikul seluruh event broadcast (dashboard FOP, task teknisi,
 * status tagihan). Upload bulk 100 kwitansi menaruh 100 job lambat di depan
 * barisan dan layar realtime berhenti bergerak sampai tumpukan habis — tanpa
 * error, tanpa petunjuk di mana pun.
 *
 * Angka waktunya saling terkait dan gampang menyimpang sendiri-sendiri:
 * timeout job harus muat di dalam timeout supervisor-nya, dan keduanya harus
 * di bawah `retry_after` koneksi queue (REDIS_QUEUE_RETRY_AFTER=360 di
 * docker-compose). Kalau tidak, job yang masih berjalan diambil worker kedua
 * dan satu berkas dibaca dua kali.
 */
class MatchPaymentReceiptQueueTest extends TestCase
{
    public function test_dikirim_ke_antrean_kwitansi_bukan_default(): void
    {
        $this->assertSame('kwitansi', (new MatchPaymentReceipt(1))->queue);
    }

    public function test_ada_supervisor_horizon_yang_melayani_antrean_itu(): void
    {
        $queues = collect(config('horizon.defaults'))->flatMap(fn (array $s) => $s['queue'] ?? []);

        $this->assertTrue(
            $queues->contains('kwitansi'),
            'Job diarahkan ke antrean `kwitansi` tapi tak ada supervisor Horizon yang mengambilnya — job menumpuk diam-diam.'
        );
    }

    public function test_timeout_job_muat_di_dalam_timeout_supervisornya(): void
    {
        $supervisor = collect(config('horizon.defaults'))
            ->first(fn (array $s) => in_array('kwitansi', $s['queue'] ?? [], true));

        $this->assertLessThanOrEqual(
            $supervisor['timeout'],
            (new MatchPaymentReceipt(1))->timeout,
            'Supervisor memotong job sebelum job sempat menyerah sendiri.'
        );
    }
}
