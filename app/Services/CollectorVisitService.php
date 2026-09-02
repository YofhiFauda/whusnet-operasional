<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\VisitResult;
use App\Models\CollectorVisit;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Catatan kunjungan kolektor + laporan aging turunannya.
 *
 * Nilai tabel ini bukan pada baris "bayar" — itu sudah terekam di `payments`.
 * Nilainya ada pada baris yang TIDAK menghasilkan uang: tanpa itu, "tidak ada
 * baris" ambigu antara "belum didatangi" dan "didatangi lalu uangnya raib".
 *
 * docs/plan/kolektor/analisa-alur-kolektor-2.0.md §12.
 */
class CollectorVisitService
{
    /**
     * Kunjungan yang diinput kolektor sendiri — hasil TANPA uang.
     *
     * `bayar` ditolak di sini secara sengaja: hasil itu cuma boleh lahir dari
     * payment yang benar-benar tersimpan (recordPaid()). Kalau kolektor boleh
     * mengetiknya, dia bisa mengaku sudah menagih tanpa uang masuk — dan
     * catatan kunjungan berubah dari alat pengungkap jadi alat penutup.
     */
    public function logManualVisit(
        User $collector,
        Customer $customer,
        VisitResult $result,
        ?string $visitedAt = null,
        ?string $promisedDate = null,
        ?string $note = null,
    ): CollectorVisit {
        if (! in_array($result->value, VisitResult::manualValues(), true)) {
            throw new RuntimeException('Hasil "Bayar" tidak bisa diinput manual — catat pembayarannya, kunjungannya tercatat otomatis.');
        }

        $this->assertCustomerBelongsToCollector($collector, $customer);

        if ($result === VisitResult::JANJI_BAYAR && blank($promisedDate)) {
            throw new RuntimeException('Isi tanggal janji bayar.');
        }

        if ($result !== VisitResult::JANJI_BAYAR) {
            // Tanggal janji tak bermakna untuk hasil lain — dibuang supaya
            // laporan "janji jatuh tempo" tidak memungut baris yang bukan janji.
            $promisedDate = null;
        }

        $visitDate = $visitedAt ? Carbon::parse($visitedAt)->toDateString() : now()->toDateString();

        // Baris `bayar` TAK BOLEH ditimpa input manual.
        //
        // Tanpa guard ini, larangan "bayar tidak bisa diinput manual" cuma
        // separuh: kolektor memang tak bisa MEMBUAT-nya, tapi bisa
        // MENGHAPUS-nya — tagih pagi (tercatat bayar), lalu kirim
        // "tidak ada orang" sore hari untuk pelanggan yang sama, dan jejak
        // pembayarannya di laporan kunjungan lenyap. Justru itu jejak yang
        // paling ingin dihapus orang yang mengantongi uang.
        //
        // Kalau pembayarannya memang salah, jalurnya membatalkan payment
        // (yang punya jejak & aturannya sendiri), bukan mengetik ulang
        // kunjungan.
        $existing = $this->findVisit($collector->id, $customer->id, $visitDate);

        if ($existing && $existing->result === VisitResult::BAYAR) {
            throw new RuntimeException(
                "Kunjungan ke {$customer->full_name} hari itu sudah tercatat sebagai Bayar (dari pembayaran yang tersimpan). ".
                'Catatan kunjungan tidak bisa menimpanya — batalkan pembayarannya dulu kalau memang keliru.'
            );
        }

        return $this->upsertVisit($collector, $customer, $visitDate, [
            'result' => $result->value,
            'promised_date' => $promisedDate,
            'note' => $note,
            // Eksplisit dinolkan: baris ini bisa saja bekas hasil lain yang
            // pernah menunjuk payment. Meninggalkannya bikin baris kontradiktif
            // "Tidak Ada Orang • PAY-000123" di riwayat admin.
            'payment_id' => null,
        ]);
    }

    /**
     * Kunjungan berhasil — diturunkan dari payment, bukan diketik.
     *
     * Dipanggil sesudah payment tersimpan. Menimpa catatan "tidak ada orang"
     * di hari yang sama kalau ternyata kolektor balik lagi dan berhasil
     * menagih: yang berlaku adalah hasil akhir kunjungan hari itu.
     */
    public function recordPaid(User $collector, int $customerId, ?int $paymentId, ?string $collectedDate = null): ?CollectorVisit
    {
        $customer = Customer::find($customerId);

        if (! $customer) {
            return null;
        }

        $visitDate = $collectedDate ? Carbon::parse($collectedDate)->toDateString() : now()->toDateString();

        return $this->upsertVisit($collector, $customer, $visitDate, [
            'result' => VisitResult::BAYAR->value,
            'promised_date' => null,
            'payment_id' => $paymentId,
            // Catatan manual dari percobaan sebelumnya hari itu ("rumah
            // kosong") ikut dibuang — kalau ditinggal, riwayat menampilkan
            // baris Bayar dengan alasan gagal menempel di bawahnya.
            'note' => null,
        ]);
    }

    /**
     * Satu kunjungan = satu pintu, satu hari. Tanpa kunci ini, pelanggan yang
     * melunasi 3 tagihan sekaligus tercatat 3 kali "dikunjungi" dan laporan
     * aging jadi bohong ke arah sebaliknya.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function upsertVisit(User $collector, Customer $customer, string $visitDate, array $attributes): CollectorVisit
    {
        // SENGAJA `whereDate`, bukan updateOrCreate dengan `visited_at` di
        // kunci pencarian. Kolomnya DATE tapi atributnya di-cast `date`, jadi
        // Eloquent membandingkannya sebagai datetime penuh ('… 00:00:00') dan
        // tak pernah menemukan baris yang sudah ada — hasilnya insert kedua
        // yang menabrak unique index, dan seluruh transaksi pembayaran ikut
        // rollback. Ini yang bikin "bayar 3 tagihan sekaligus" gagal total.
        $visit = $this->findVisit($collector->id, $customer->id, $visitDate);

        if (! $visit) {
            $visit = new CollectorVisit([
                'collector_id' => $collector->id,
                'customer_id' => $customer->id,
                'visited_at' => $visitDate,
            ]);
        }

        $visit->fill(array_merge(['pop_id' => $customer->pop_id], $attributes));
        $visit->save();

        return $visit;
    }

    /**
     * `whereDate` (bukan `where`) karena kolomnya DATE tapi atributnya di-cast
     * `date` — perbandingan biasa jadi datetime penuh dan tak pernah ketemu.
     */
    private function findVisit(int $collectorId, int $customerId, string $visitDate): ?CollectorVisit
    {
        return CollectorVisit::query()
            ->where('collector_id', $collectorId)
            ->where('customer_id', $customerId)
            ->whereDate('visited_at', $visitDate)
            ->first();
    }

    /**
     * Dua lapis, dua-duanya wajib — sama seperti di Worklist & jalur bayar:
     * pelanggan harus milik kolektor ini DAN masuk POP scope efektifnya
     * sekarang (assign lama tak otomatis bersih saat kolektor pindah cabang).
     */
    private function assertCustomerBelongsToCollector(User $collector, Customer $customer): void
    {
        if ((int) $customer->collector_id !== $collector->id) {
            throw new RuntimeException("{$customer->full_name} bukan pelanggan tanggung jawab Anda.");
        }

        $inScope = Customer::query()
            ->applyUserScope($collector)
            ->whereKey($customer->id)
            ->exists();

        if (! $inScope) {
            throw new RuntimeException("{$customer->full_name} berada di luar scope POP Anda.");
        }
    }

    /**
     * @return Builder<CollectorVisit>
     */
    public function historyFor(User $collector, ?User $viewer = null): Builder
    {
        return CollectorVisit::query()
            ->applyUserScope($viewer)
            ->where('collector_id', $collector->id)
            ->with(['customer:id,full_name,customer_code,cid', 'payment:id,payment_number,amount'])
            ->orderByDesc('visited_at')
            ->orderByDesc('id');
    }

    /**
     * Laporan aging: pelanggan tertunggak milik kolektor ini, diurutkan dari
     * yang paling sering didatangi tanpa hasil.
     *
     * Inilah bentuk yang membuat pola mencurigakan kelihatan — "5× tidak ada
     * orang, tunggakan Rp600rb, terakhir dikunjungi kemarin". Satu barisnya
     * belum tentu berarti apa-apa; polanya yang layak diaudit.
     *
     * @return Builder<Customer>
     */
    public function agingFor(User $collector, ?User $viewer = null): Builder
    {
        $outstanding = [InvoiceStatus::BELUM_DIBAYAR->value, InvoiceStatus::SEBAGIAN->value];

        return Customer::query()
            ->applyUserScope($viewer)
            ->where('collector_id', $collector->id)
            ->whereHas('invoices', fn ($q) => $q->whereIn('invoice_status', $outstanding))
            ->withSum(
                ['invoices as tunggakan' => fn ($q) => $q->whereIn('invoice_status', $outstanding)],
                'remaining_amount'
            )
            ->withCount([
                'collectorVisits as kunjungan_gagal' => fn ($q) => $q
                    ->where('collector_id', $collector->id)
                    ->where('result', '!=', VisitResult::BAYAR->value),
                'collectorVisits as kunjungan_total' => fn ($q) => $q->where('collector_id', $collector->id),
            ])
            ->withMax(
                ['collectorVisits as terakhir_dikunjungi' => fn ($q) => $q->where('collector_id', $collector->id)],
                'visited_at'
            )
            ->orderByDesc('kunjungan_gagal')
            ->orderByDesc('tunggakan');
    }
}
