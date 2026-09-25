<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentPeriodType;
use App\Enums\PaymentStatus;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\PeriodClosing;
use App\Models\Pop;
use App\Models\User;
use App\Support\BookPeriod;
use App\Support\Money;
use Carbon\Carbon;
use Closure;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Laporan Bulanan Admin Collector (ADHOC-90) — pengganti spreadsheet manual
 * `docs/plan/billing/LAPORAN ADMIN COLLECTOR 2026_DIAH.xlsx`.
 *
 * Satu baris = satu POP cabang/pusat; mini-POP dilipat ke induknya. Empat blok:
 * Tagihan, Piutang Bulan Lalu, Pelanggan, Uang Diterima.
 *
 * SEMUA rumus kolom ada di sini dan cuma di sini. Judul kolom di template
 * lama ambigu ("JuliTerkiri", "dimuka"), jadi rumus ini adalah tafsiran yang
 * harus dicocokkan dengan angka Excel — kalau berubah, cukup ubah satu tempat.
 * Definisi lengkap: docs/billing-pembayaran/README.md § Laporan Bulanan Admin Collector.
 *
 * Dua basis yang sengaja dicampur, jangan diratakan:
 *  - Blok 1–3 = basis TAGIHAN (invoice), dihitung "per akhir bulan P": bayar
 *    yang dihitung hanya `payment_date` < awal bulan berikutnya, supaya hasil
 *    hitung ulang periode lama sama dengan snapshot yang dibekukan saat tutup.
 *  - Blok 4 = basis KAS (`payment_date` di dalam P), sama seperti
 *    `/reports/payments` — uang piutang yang baru dibayar bulan ini masuk bulan ini.
 */
class CollectorMonthlyReportService
{
    /**
     * Batas bawah/atas setengah-terbuka [awal, awal bulan berikutnya) sebagai
     * string tanggal polos. Sengaja BUKAN Carbon/endOfDay: kolom `payment_date`
     * bertipe date, dan di sqlite nilainya bisa tersimpan 'Y-m-d' atau
     * 'Y-m-d 00:00:00' — perbandingan string dengan batas 'Y-m-d' benar untuk
     * keduanya, sedangkan `<= '2026-08-31 23:59:59'` atau `< '2026-08-01 00:00:00'`
     * salah menggolongkan tanggal yang persis di batas.
     *
     * @return array{0: string, 1: string}
     */
    public static function bounds(string $period): array
    {
        $start = self::parsePeriod($period);

        return [$start->toDateString(), $start->copy()->addMonth()->toDateString()];
    }

    /**
     * Payment dianggap sah PER TANGGAL `$asOf`: VALID, atau dikembalikan
     * (`ditolak`) pada/sesudah `$asOf`.
     *
     * Kenapa bukan `payment_status = valid` saja: sejak tutup buku permanen,
     * pembayaran bulan terkunci yang dikembalikan belakangan TIDAK boleh
     * menggeser angka bulan itu — di bulan itu uangnya memang (salah) tercatat
     * diterima. Pengembaliannya dibukukan di bulan terjadinya, sebagai kolom
     * `dikembalikan` Blok 4. Tanpa filter ini hitung-ulang bulan lama menyimpang
     * dari snapshot dan piutang pembuka bulan berikutnya tak lagi nyambung
     * dengan penutupan bulan sebelumnya.
     *
     * Baris legacy `ditolak` tanpa `rejected_at` → tidak pernah dihitung.
     */
    private static function countedAsOf(string $asOf, string $table = ''): Closure
    {
        $col = fn (string $column) => $table === '' ? $column : "{$table}.{$column}";

        return fn ($q) => $q->where($col('payment_status'), PaymentStatus::VALID->value)
            ->orWhere(fn ($reversed) => $reversed
                ->where($col('payment_status'), PaymentStatus::DITOLAK->value)
                ->where($col('rejected_at'), '>=', $asOf));
    }

    public static function parsePeriod(string $period): Carbon
    {
        $start = Carbon::createFromFormat('!Y-m', $period);

        if ($start === false || $start->format('Y-m') !== $period) {
            throw ValidationException::withMessages(['period' => 'Format periode harus YYYY-MM.']);
        }

        return $start->startOfMonth();
    }

    /**
     * POP cabang/pusat yang boleh dilihat user — satu baris laporan tiap POP.
     * Mini-POP tidak jadi baris sendiri (dilipat ke induknya di `figures()`).
     *
     * @return Collection<int, Pop>
     */
    public function branchesFor(User $user, ?int $popId = null): Collection
    {
        return Pop::query()
            ->forUser($user)
            ->whereIn('type', ['pusat', 'cabang'])
            ->when($popId, fn ($q) => $q->whereKey($popId))
            ->orderBy('id')
            ->get();
    }

    /**
     * pop_id → id POP cabang tempatnya dilipat. Mini-POP → induknya; POP
     * lain → dirinya sendiri.
     *
     * @return array<int, int>
     */
    public function branchMap(): array
    {
        return Pop::query()->get(['id', 'type', 'parent_id'])
            ->mapWithKeys(fn (Pop $pop) => [
                $pop->id => ($pop->type === 'mini_pop' && $pop->parent_id) ? (int) $pop->parent_id : (int) $pop->id,
            ])
            ->all();
    }

    /**
     * @return array<string, array<string, float|int>>
     */
    public static function emptyFigures(): array
    {
        return [
            'tagihan' => ['tagihan_terbit' => 0.0, 'dimuka' => 0.0, 'diskon' => 0.0, 'bulanan' => 0.0, 'total_pembayaran' => 0.0, 'piutang' => 0.0],
            'piutang_lalu' => ['pembuka' => 0.0, 'sudah_dibayar' => 0.0, 'belum_dibayar' => 0.0, 'tak_tertagih' => 0.0],
            'pelanggan' => ['total' => 0, 'dimuka' => 0, 'sudah_bayar' => 0, 'belum_bayar' => 0],
            // `dikembalikan` = pengurang: pembayaran bulan terkunci yang
            // di-Kembalikan bulan ini (lihat countedAsOf()). Snapshot lama
            // belum punya kunci ini — pembaca wajib `?? 0`.
            'uang_diterima' => ['bulanan' => 0.0, 'piutang' => 0.0, 'lebih_bayar' => 0.0, 'aktivasi' => 0.0, 'lainnya' => 0.0, 'dikembalikan' => 0.0, 'total' => 0.0],
        ];
    }

    /** Persentase; 0 (bukan #DIV/0! seperti Excel) kalau pembagi 0. */
    public static function percentage(mixed $bagian, mixed $total): float
    {
        if (Money::isZero($total)) {
            return 0.0;
        }

        return round((float) $bagian / (float) $total * 100, 1);
    }

    /**
     * Hitung LIVE dari invoices + payments untuk beberapa POP cabang sekaligus.
     *
     * @param  list<int>  $branchIds
     * @return array<int, array<string, array<string, float|int>>> keyed by id POP cabang
     */
    public function figures(string $period, array $branchIds): array
    {
        [$start, $nextStart] = self::bounds($period);

        $map = $this->branchMap();
        $popIds = array_keys(array_filter($map, fn (int $branch) => in_array($branch, $branchIds, true)));

        $figures = array_fill_keys($branchIds, self::emptyFigures());

        if ($popIds === []) {
            return $figures;
        }

        $this->fillTagihanDanPelanggan($figures, $map, $popIds, $period, $nextStart);
        $this->fillPiutangLalu($figures, $map, $popIds, $period, $start, $nextStart);
        $this->fillUangDiterima($figures, $map, $popIds, $period, $start, $nextStart);

        // Total Pembayaran = penutup persamaan Tagihan Terbit = Bulanan(tunai)
        // + Dimuka(saldo) + Diskon + Piutang. Dihitung di sini, bukan per
        // baris invoice — sama saja secara matematis (penjumlahan komutatif),
        // tapi satu titik lebih gampang diaudit daripada diam-diam nambah di
        // loop.
        foreach ($branchIds as $id) {
            $t = &$figures[$id]['tagihan'];
            $t['total_pembayaran'] = Money::sum([$t['bulanan'], $t['dimuka'], $t['diskon']]);
        }
        unset($t);

        return $figures;
    }

    /**
     * Blok 1 (Tagihan) + Blok 3 (Pelanggan): keduanya turunan invoice bulanan
     * periode P, dihitung per invoice supaya klasifikasi pelanggan konsisten
     * dengan nominalnya.
     *
     * Definisi (klarifikasi user 2026-09-22, menggantikan tafsiran admin-vs-
     * kolektor sebelumnya):
     *  - `tagihan_terbit` — total tagihan BULANAN yang diterbitkan periode P
     *    (dulu bernama kolom "Terkini" di template, gampang disalahartikan
     *    sebagai "sekarang"; nama internal & label UI diganti).
     *  - `bulanan` — bagian yang SUDAH DIBAYAR TUNAI (metode apa pun KECUALI
     *    `saldo`) — gabungan admin & kolektor, tak lagi dipecah.
     *  - `dimuka` — bagian yang dibayar dari SALDO PELANGGAN (uang yang
     *    dititipkan di muka untuk bulan-bulan mendatang, lalu terpakai
     *    otomatis begitu tagihan bulan ini terbit — lihat
     *    docs/plan/billing/analisa-rancangan-saldo-pelanggan.md, ADHOC-92,
     *    BELUM diimplementasikan). `PaymentMethod::SALDO` belum ada di enum,
     *    jadi filter string `'saldo'` di bawah ini SENGAJA tidak match apa
     *    pun sekarang — kolom ini benar bernilai 0 sampai ADHOC-92 jalan,
     *    lalu otomatis terisi tanpa perubahan kode laporan.
     *  - `total_pembayaran` — bulanan + dimuka + diskon (dihitung di
     *    `figures()` setelah loop ini).
     *  - Pelanggan: bucket `dimuka` = lunas SELURUHNYA lewat saldo (bukan
     *    "bayar ke admin" seperti tafsiran lama).
     *
     * @param  array<int, array<string, array<string, float|int>>>  $figures
     * @param  array<int, int>  $map
     * @param  list<int>  $popIds
     */
    private function fillTagihanDanPelanggan(array &$figures, array $map, array $popIds, string $period, string $nextStart): void
    {
        $rows = DB::table('invoices as i')
            ->leftJoinSub($this->paidPerInvoiceByMethod(null, $nextStart), 'p', 'p.invoice_id', '=', 'i.id')
            ->where('i.billing_period', $period)
            ->where('i.invoice_type', InvoiceType::BULANAN->value)
            ->where('i.invoice_status', '!=', InvoiceStatus::BATAL->value)
            ->whereIn('i.pop_id', $popIds)
            ->get(['i.pop_id', 'i.customer_id', 'i.total_amount', 'i.discount', 'p.cash_paid', 'p.saldo_paid']);

        $customers = [];

        foreach ($rows as $row) {
            $branch = $map[$row->pop_id];
            $t = &$figures[$branch]['tagihan'];
            $c = &$figures[$branch]['pelanggan'];

            $cashPaid = Money::of($row->cash_paid ?? 0);
            $saldoPaid = Money::of($row->saldo_paid ?? 0);
            $paid = Money::add($cashPaid, $saldoPaid);
            $remaining = Money::atLeastZero(Money::sub($row->total_amount, $paid));

            $t['tagihan_terbit'] = Money::add($t['tagihan_terbit'], $row->total_amount);
            $t['diskon'] = Money::add($t['diskon'], $row->discount ?? 0);
            $t['bulanan'] = Money::add($t['bulanan'], $cashPaid);
            $t['dimuka'] = Money::add($t['dimuka'], $saldoPaid);
            $t['piutang'] = Money::add($t['piutang'], $remaining);

            // Satu pelanggan dihitung sekali walau (lewat data legacy) punya
            // dua invoice bulanan di periode yang sama.
            if (isset($customers[$branch][$row->customer_id])) {
                continue;
            }
            $customers[$branch][$row->customer_id] = true;

            $c['total']++;

            if (Money::greaterThan($remaining, 0)) {
                $c['belum_bayar']++;
            } elseif (Money::greaterThan($saldoPaid, 0) && Money::isZero($cashPaid)) {
                $c['dimuka']++;
            } else {
                $c['sudah_bayar']++;
            }
        }

        unset($t, $c);
    }

    /**
     * Blok 2 (Piutang Bulan Lalu): invoice `billing_period` < P yang masih
     * punya sisa pada AWAL bulan P. Pembuka dihitung ulang dari payment
     * (`payment_date` < awal P), bukan dibaca dari snapshot bulan sebelumnya —
     * jadi periode pertama yang belum pernah ditutup pun tetap benar.
     *
     * @param  array<int, array<string, array<string, float|int>>>  $figures
     * @param  array<int, int>  $map
     * @param  list<int>  $popIds
     */
    private function fillPiutangLalu(array &$figures, array $map, array $popIds, string $period, string $start, string $nextStart): void
    {
        $before = DB::table('payments')
            ->where(self::countedAsOf($start))
            ->where('payment_date', '<', $start)
            ->groupBy('invoice_id')
            ->select('invoice_id', DB::raw('SUM(amount) AS paid_before'));

        $during = DB::table('payments')
            ->where(self::countedAsOf($nextStart))
            ->where('payment_date', '>=', $start)
            ->where('payment_date', '<', $nextStart)
            ->groupBy('invoice_id')
            ->select('invoice_id', DB::raw('SUM(amount) AS paid_during'));

        $rows = DB::table('invoices as i')
            ->leftJoinSub($before, 'b', 'b.invoice_id', '=', 'i.id')
            ->leftJoinSub($during, 'd', 'd.invoice_id', '=', 'i.id')
            ->where('i.billing_period', '<', $period)
            ->where('i.invoice_status', '!=', InvoiceStatus::BATAL->value)
            // Sudah dihapus buku SEBELUM awal P → bukan piutang pembuka P lagi.
            ->where(fn ($q) => $q->whereNull('i.written_off_at')->orWhere('i.written_off_at', '>=', $start))
            ->whereIn('i.pop_id', $popIds)
            ->get(['i.pop_id', 'i.total_amount', 'b.paid_before', 'd.paid_during']);

        foreach ($rows as $row) {
            $opening = Money::atLeastZero(Money::sub($row->total_amount, $row->paid_before ?? 0));

            if (Money::isZero($opening)) {
                continue;
            }

            $paidDuring = Money::min($opening, Money::of($row->paid_during ?? 0));
            $block = &$figures[$map[$row->pop_id]]['piutang_lalu'];

            $block['pembuka'] = Money::add($block['pembuka'], $opening);
            $block['sudah_dibayar'] = Money::add($block['sudah_dibayar'], $paidDuring);
            $block['belum_dibayar'] = Money::add($block['belum_dibayar'], Money::sub($opening, $paidDuring));
        }

        unset($block);

        $writeOffs = DB::table('invoices')
            ->where('written_off_at', '>=', $start)
            ->where('written_off_at', '<', $nextStart)
            ->whereIn('pop_id', $popIds)
            ->selectRaw('pop_id, SUM(written_off_amount) AS amount')
            ->groupBy('pop_id')
            ->get();

        foreach ($writeOffs as $row) {
            $block = &$figures[$map[$row->pop_id]]['piutang_lalu'];
            $block['tak_tertagih'] = Money::add($block['tak_tertagih'], $row->amount);
        }

        unset($block);
    }

    /**
     * Blok 4 (Uang Diterima): basis kas, semua payment VALID ber-`payment_date`
     * di dalam P, digolongkan menurut invoice yang dibayarnya.
     *
     * Invoice bulanan bertagihan periode SESUDAH P (bayar di muka sebulan
     * lebih awal) masuk kolom Bulanan — uangnya untuk langganan, bukan piutang.
     * `Lebih Bayar` = `overpay_amount` (uang di atas sisa tagihan) dan ikut
     * `Total`; `amount` sendiri tak pernah melebihi sisa, jadi jumlah keempat
     * kolom lain persis sama dengan total /reports/payments.
     *
     * @param  array<int, array<string, array<string, float|int>>>  $figures
     * @param  array<int, int>  $map
     * @param  list<int>  $popIds
     */
    private function fillUangDiterima(array &$figures, array $map, array $popIds, string $period, string $start, string $nextStart): void
    {
        $rows = DB::table('payments as pay')
            ->leftJoin('invoices as i', 'i.id', '=', 'pay.invoice_id')
            ->where(self::countedAsOf($nextStart, 'pay'))
            ->where('pay.payment_date', '>=', $start)
            ->where('pay.payment_date', '<', $nextStart)
            ->whereIn('pay.pop_id', $popIds)
            ->groupBy('pay.pop_id', 'i.invoice_type', 'i.billing_period')
            ->get([
                'pay.pop_id',
                'i.invoice_type',
                'i.billing_period',
                DB::raw('SUM(pay.amount) AS amount'),
                DB::raw('SUM(COALESCE(pay.overpay_amount, 0)) AS overpay'),
            ]);

        foreach ($rows as $row) {
            $block = &$figures[$map[$row->pop_id]]['uang_diterima'];

            $kolom = match ($row->invoice_type) {
                InvoiceType::AWAL->value => 'aktivasi',
                InvoiceType::BULANAN->value => ($row->billing_period !== null && $row->billing_period < $period) ? 'piutang' : 'bulanan',
                default => 'lainnya',
            };

            $block[$kolom] = Money::add($block[$kolom], $row->amount);
            $block['lebih_bayar'] = Money::add($block['lebih_bayar'], $row->overpay);
        }

        unset($block);

        // Pengembalian di bulan P atas pembayaran bertanggal SEBELUM P (bulan
        // yang sudah tutup buku). Pengembalian pembayaran bertanggal P sendiri
        // tidak masuk sini — pembayaran itu sudah tidak terhitung di atas.
        $reversals = DB::table('payments')
            ->where('payment_status', PaymentStatus::DITOLAK->value)
            ->where('rejected_at', '>=', $start)
            ->where('rejected_at', '<', $nextStart)
            ->where('payment_date', '<', $start)
            ->whereIn('pop_id', $popIds)
            ->groupBy('pop_id')
            ->get(['pop_id', DB::raw('SUM(amount + COALESCE(overpay_amount, 0)) AS amount')]);

        foreach ($reversals as $row) {
            $block = &$figures[$map[$row->pop_id]]['uang_diterima'];
            $block['dikembalikan'] = Money::add($block['dikembalikan'], $row->amount);
        }

        unset($block);

        foreach ($figures as &$perPop) {
            $b = &$perPop['uang_diterima'];
            $b['total'] = Money::sub(
                Money::sum([$b['bulanan'], $b['piutang'], $b['lebih_bayar'], $b['aktivasi'], $b['lainnya']]),
                $b['dikembalikan'],
            );
        }

        unset($perPop, $b);
    }

    /**
     * Subquery total bayar per invoice, dipecah tunai (metode apa pun kecuali
     * `saldo`) vs saldo pelanggan (`payment_method = 'saldo'` — ADHOC-92,
     * belum ada payment dengan metode ini, jadi `saldo_paid` selalu 0
     * sekarang; lihat docblock `fillTagihanDanPelanggan()`). Dibatasi sampai
     * awal bulan berikutnya — lihat catatan basis di docblock kelas.
     */
    private function paidPerInvoiceByMethod(?string $from, string $before): QueryBuilder
    {
        return DB::table('payments')
            ->where(self::countedAsOf($before))
            ->when($from, fn ($q) => $q->where('payment_date', '>=', $from))
            ->where('payment_date', '<', $before)
            ->groupBy('invoice_id')
            ->select(
                'invoice_id',
                DB::raw("SUM(CASE WHEN payment_method = 'saldo' THEN amount ELSE 0 END) AS saldo_paid"),
                DB::raw("SUM(CASE WHEN payment_method != 'saldo' THEN amount ELSE 0 END) AS cash_paid"),
            );
    }

    /**
     * Susun laporan untuk halaman & export: angka snapshot untuk POP yang
     * sudah ditutup, live untuk sisanya.
     *
     * `drift` = angka live sekarang beda dari snapshot (ada transaksi
     * bertanggal periode itu yang masuk SETELAH ditutup, mis. impor legacy).
     * Angka yang ditampilkan tetap snapshot — drift cuma peringatan.
     *
     * @param  Collection<int, Pop>  $branches
     * @return array{rows: list<array{pop: Pop, figures: array<string, array<string, float|int>>, closing: ?PeriodClosing, drift: bool}>, totals: array<string, array<string, float|int>>}
     */
    public function report(string $period, Collection $branches): array
    {
        $ids = $branches->pluck('id')->map(fn ($id) => (int) $id)->all();
        $live = $this->figures($period, $ids);

        $closings = PeriodClosing::query()
            ->where('period', $period)
            ->whereIn('pop_id', $ids)
            ->get()
            ->keyBy('pop_id');

        $rows = [];
        $totals = self::emptyFigures();

        foreach ($branches as $pop) {
            $closing = $closings->get($pop->id);
            $figures = $closing ? $closing->figures : $live[$pop->id];

            $rows[] = [
                'pop' => $pop,
                'figures' => $figures,
                'closing' => $closing,
                'drift' => $closing !== null && $this->differs($closing->figures, $live[$pop->id]),
            ];

            foreach ($figures as $block => $columns) {
                foreach ($columns as $column => $value) {
                    $totals[$block][$column] = is_int($totals[$block][$column])
                        ? $totals[$block][$column] + (int) $value
                        : Money::add($totals[$block][$column], $value);
                }
            }
        }

        return ['rows' => $rows, 'totals' => $totals];
    }

    /**
     * @param  array<string, array<string, float|int>>  $a
     * @param  array<string, array<string, float|int>>  $b
     */
    private function differs(array $a, array $b): bool
    {
        foreach ($b as $block => $columns) {
            foreach ($columns as $column => $value) {
                if (Money::cents($a[$block][$column] ?? 0) !== Money::cents($value)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Daftar valid (blok, kolom) yang punya rincian — dipakai controller
     * memvalidasi request sebelum memanggil `detail()`.
     *
     * @return array<string, list<string>>
     */
    public static function detailableColumns(): array
    {
        return [
            'tagihan' => ['tagihan_terbit', 'dimuka', 'diskon', 'bulanan', 'piutang'],
            'piutang_lalu' => ['pembuka', 'sudah_dibayar', 'belum_dibayar', 'tak_tertagih'],
            'pelanggan' => ['total', 'dimuka', 'sudah_bayar', 'belum_bayar'],
            'uang_diterima' => ['bulanan', 'piutang', 'lebih_bayar', 'aktivasi', 'lainnya', 'dikembalikan', 'total'],
        ];
    }

    /**
     * Rincian baris di balik satu sel angka laporan — "siapa saja yang
     * membentuk angka ini". SELALU dihitung LIVE dari DB (bukan dari
     * snapshot `period_closings`, yang cuma simpan angka teragregasi) —
     * kalau POP sudah ditutup dan ada drift, rincian di sini mengikuti
     * kondisi data TERKINI, bukan yang dibekukan; controller menandai ini
     * ke pengguna via flag `live`.
     *
     * Bentuk baris konsisten lintas kolom supaya satu modal/tabel dipakai
     * ulang: pelanggan, akun (CID/kode), referensi (no. invoice/pembayaran),
     * tanggal, nominal, keterangan.
     *
     * @return list<array{pelanggan: string, akun: string, referensi: string, tanggal: ?string, nominal: float, keterangan: string}>
     */
    public function detail(string $period, int $branchPopId, string $block, string $column): array
    {
        if (! in_array($column, self::detailableColumns()[$block] ?? [], true)) {
            throw new InvalidArgumentException("Kolom '{$column}' di blok '{$block}' tidak punya rincian.");
        }

        [$start, $nextStart] = self::bounds($period);
        $map = $this->branchMap();
        $popIds = array_keys(array_filter($map, fn (int $branch) => $branch === $branchPopId));

        if ($popIds === []) {
            return [];
        }

        return match ($block) {
            'tagihan' => $this->detailTagihan($popIds, $period, $nextStart, $column),
            'piutang_lalu' => $this->detailPiutangLalu($popIds, $period, $start, $nextStart, $column),
            'pelanggan' => $this->detailPelanggan($popIds, $period, $nextStart, $column),
            'uang_diterima' => $this->detailUangDiterima($popIds, $period, $start, $nextStart, $column),
            default => throw new InvalidArgumentException("Blok '{$block}' tidak dikenal."),
        };
    }

    /**
     * Query dasar invoice BULANAN periode P (dipakai `figures()` DAN
     * `detail()` blok Tagihan/Pelanggan — satu definisi baris, dua cara
     * pakai) + join pelanggan untuk tampilan.
     *
     * @param  list<int>  $popIds
     * @return QueryBuilder
     */
    private function tagihanRowsQuery(array $popIds, string $period, string $nextStart)
    {
        return DB::table('invoices as i')
            ->leftJoinSub($this->paidPerInvoiceByMethod(null, $nextStart), 'p', 'p.invoice_id', '=', 'i.id')
            ->join('customers as c', 'c.id', '=', 'i.customer_id')
            ->where('i.billing_period', $period)
            ->where('i.invoice_type', InvoiceType::BULANAN->value)
            ->where('i.invoice_status', '!=', InvoiceStatus::BATAL->value)
            ->whereIn('i.pop_id', $popIds)
            ->select([
                'i.invoice_number', 'i.total_amount', 'i.discount',
                'c.full_name', 'c.customer_code', 'c.cid',
                'p.cash_paid', 'p.saldo_paid',
            ]);
    }

    /**
     * @param  list<int>  $popIds
     * @return list<array{pelanggan: string, akun: string, referensi: string, tanggal: ?string, nominal: float, keterangan: string}>
     */
    private function detailTagihan(array $popIds, string $period, string $nextStart, string $column): array
    {
        // Bulanan & Dimuka = TRANSAKSI pembayaran nyata (siapa bayar berapa,
        // kapan) — bukan agregat per invoice, supaya "siapa saja yang sudah
        // membayar" langsung kelihatan barisnya. `PaymentMethod::SALDO`
        // belum ada (ADHOC-92) → filter 'saldo' sengaja tak pernah match,
        // 'Dimuka' akan kosong sampai fitur itu jalan.
        if (in_array($column, ['bulanan', 'dimuka'], true)) {
            $methodFilter = $column === 'dimuka' ? "= 'saldo'" : "!= 'saldo'";

            $rows = DB::table('payments as pay')
                ->join('invoices as i', 'i.id', '=', 'pay.invoice_id')
                ->join('customers as c', 'c.id', '=', 'pay.customer_id')
                ->where('i.billing_period', $period)
                ->where('i.invoice_type', InvoiceType::BULANAN->value)
                ->where('i.invoice_status', '!=', InvoiceStatus::BATAL->value)
                ->whereIn('i.pop_id', $popIds)
                ->where(self::countedAsOf($nextStart, 'pay'))
                ->where('pay.payment_date', '<', $nextStart)
                ->whereRaw("pay.payment_method {$methodFilter}")
                ->orderBy('pay.payment_date')
                ->get(['pay.id as payment_id', 'c.full_name', 'c.customer_code', 'c.cid', 'i.invoice_number', 'pay.payment_date', 'pay.amount', 'pay.payment_method', 'pay.note']);

            // Klasifikasi (ADHOC-84 §8.2) — cuma baris ini yang benar-benar
            // transaksi payment (bukan agregat invoice), jadi cuma di sini
            // "Jenis" bermakna. Di-batch, bukan query per baris — modal ini
            // dipaginasi per-sel, bukan seluruh dataset.
            $paymentsById = Payment::query()
                ->with('invoice:id,billing_period,total_amount')
                ->whereIn('id', $rows->pluck('payment_id'))
                ->get()
                ->keyBy('id');

            return $rows
                ->map(fn ($r) => $this->row(
                    $r->full_name, $r->cid ?: $r->customer_code, $r->invoice_number, $r->payment_date, (float) $r->amount,
                    trim(($r->payment_method ?? '').' '.($r->note ?? '')),
                    $paymentsById->get($r->payment_id)?->classification() ?? [],
                ))
                ->all();
        }

        $rows = $this->tagihanRowsQuery($popIds, $period, $nextStart)->get();

        return match ($column) {
            'tagihan_terbit' => $rows
                ->map(fn ($r) => $this->row($r->full_name, $r->cid ?: $r->customer_code, $r->invoice_number, null, (float) $r->total_amount, 'Tagihan bulanan periode ini'))
                ->all(),
            'diskon' => $rows->filter(fn ($r) => (float) $r->discount > 0)
                ->map(fn ($r) => $this->row($r->full_name, $r->cid ?: $r->customer_code, $r->invoice_number, null, (float) $r->discount, 'Diskon tagihan'))
                ->values()->all(),
            'piutang' => $rows
                ->map(fn ($r) => [$r, Money::atLeastZero(Money::sub($r->total_amount, Money::add($r->cash_paid ?? 0, $r->saldo_paid ?? 0)))])
                ->filter(fn ($pair) => Money::greaterThan($pair[1], 0))
                ->map(fn ($pair) => $this->row($pair[0]->full_name, $pair[0]->cid ?: $pair[0]->customer_code, $pair[0]->invoice_number, null, (float) $pair[1], 'Sisa tagihan belum dibayar'))
                ->values()->all(),
            default => [],
        };
    }

    /**
     * @param  list<int>  $popIds
     * @return list<array{pelanggan: string, akun: string, referensi: string, tanggal: ?string, nominal: float, keterangan: string}>
     */
    private function detailPiutangLalu(array $popIds, string $period, string $start, string $nextStart, string $column): array
    {
        if ($column === 'tak_tertagih') {
            return DB::table('invoices as i')
                ->join('customers as c', 'c.id', '=', 'i.customer_id')
                ->where('i.written_off_at', '>=', $start)
                ->where('i.written_off_at', '<', $nextStart)
                ->whereIn('i.pop_id', $popIds)
                ->orderBy('i.written_off_at')
                ->get(['c.full_name', 'c.customer_code', 'c.cid', 'i.invoice_number', 'i.written_off_at', 'i.written_off_amount', 'i.write_off_reason'])
                ->map(fn ($r) => $this->row($r->full_name, $r->cid ?: $r->customer_code, $r->invoice_number, $r->written_off_at, (float) $r->written_off_amount, (string) $r->write_off_reason))
                ->all();
        }

        $before = DB::table('payments')
            ->where(self::countedAsOf($start))
            ->where('payment_date', '<', $start)
            ->groupBy('invoice_id')
            ->select('invoice_id', DB::raw('SUM(amount) AS paid_before'));

        $rows = DB::table('invoices as i')
            ->leftJoinSub($before, 'b', 'b.invoice_id', '=', 'i.id')
            ->join('customers as c', 'c.id', '=', 'i.customer_id')
            ->where('i.billing_period', '<', $period)
            ->where('i.invoice_status', '!=', InvoiceStatus::BATAL->value)
            ->where(fn ($q) => $q->whereNull('i.written_off_at')->orWhere('i.written_off_at', '>=', $start))
            ->whereIn('i.pop_id', $popIds)
            ->get(['i.id', 'i.invoice_number', 'i.billing_period', 'i.total_amount', 'b.paid_before', 'c.full_name', 'c.customer_code', 'c.cid']);

        $openInvoiceIds = [];
        $result = [];

        foreach ($rows as $r) {
            $opening = Money::atLeastZero(Money::sub($r->total_amount, $r->paid_before ?? 0));
            if (Money::isZero($opening)) {
                continue;
            }
            $openInvoiceIds[$r->id] = ['row' => $r, 'opening' => $opening];
        }

        if ($column === 'pembuka' || $column === 'belum_dibayar') {
            $paidDuring = $openInvoiceIds === [] ? collect() : DB::table('payments')
                ->where(self::countedAsOf($nextStart))
                ->where('payment_date', '>=', $start)
                ->where('payment_date', '<', $nextStart)
                ->whereIn('invoice_id', array_keys($openInvoiceIds))
                ->groupBy('invoice_id')
                ->pluck(DB::raw('SUM(amount)'), 'invoice_id');

            foreach ($openInvoiceIds as $invoiceId => $entry) {
                $paid = Money::min($entry['opening'], Money::of($paidDuring[$invoiceId] ?? 0));
                $amount = $column === 'pembuka' ? $entry['opening'] : Money::sub($entry['opening'], $paid);

                if (Money::isZero($amount)) {
                    continue;
                }

                $r = $entry['row'];
                $result[] = $this->row($r->full_name, $r->cid ?: $r->customer_code, $r->invoice_number, null, (float) $amount, "Piutang periode {$r->billing_period}");
            }

            return $result;
        }

        // sudah_dibayar: transaksi pembayaran nyata bulan P atas invoice yang
        // AWALNYA piutang (opening > 0) — siapa yang melunasi tunggakan lamanya.
        if ($openInvoiceIds === []) {
            return [];
        }

        return DB::table('payments as pay')
            ->join('invoices as i', 'i.id', '=', 'pay.invoice_id')
            ->join('customers as c', 'c.id', '=', 'pay.customer_id')
            ->whereIn('pay.invoice_id', array_keys($openInvoiceIds))
            ->where(self::countedAsOf($nextStart, 'pay'))
            ->where('pay.payment_date', '>=', $start)
            ->where('pay.payment_date', '<', $nextStart)
            ->orderBy('pay.payment_date')
            ->get(['c.full_name', 'c.customer_code', 'c.cid', 'i.invoice_number', 'i.billing_period', 'pay.payment_date', 'pay.amount'])
            ->map(fn ($r) => $this->row($r->full_name, $r->cid ?: $r->customer_code, $r->invoice_number, $r->payment_date, (float) $r->amount, "Bayar piutang periode {$r->billing_period}"))
            ->all();
    }

    /**
     * @param  list<int>  $popIds
     * @return list<array{pelanggan: string, akun: string, referensi: string, tanggal: ?string, nominal: float, keterangan: string}>
     */
    private function detailPelanggan(array $popIds, string $period, string $nextStart, string $column): array
    {
        $rows = $this->tagihanRowsQuery($popIds, $period, $nextStart)->get();
        $seen = [];
        $result = [];

        foreach ($rows as $r) {
            // Satu baris per pelanggan — sinkron dengan dedup di
            // fillTagihanDanPelanggan() (invoice ganda di periode sama diabaikan).
            $key = $r->cid ?: $r->customer_code;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $cashPaid = Money::of($r->cash_paid ?? 0);
            $saldoPaid = Money::of($r->saldo_paid ?? 0);
            $remaining = Money::atLeastZero(Money::sub($r->total_amount, Money::add($cashPaid, $saldoPaid)));

            $bucket = match (true) {
                Money::greaterThan($remaining, 0) => 'belum_bayar',
                Money::greaterThan($saldoPaid, 0) && Money::isZero($cashPaid) => 'dimuka',
                default => 'sudah_bayar',
            };

            if ($column !== 'total' && $bucket !== $column) {
                continue;
            }

            $label = ['belum_bayar' => 'Belum bayar', 'dimuka' => 'Lunas dari saldo', 'sudah_bayar' => 'Sudah bayar'][$bucket];
            $nominal = $bucket === 'belum_bayar' ? (float) $remaining : (float) $r->total_amount;

            $result[] = $this->row($r->full_name, $key, $r->invoice_number, null, $nominal, $label);
        }

        return $result;
    }

    /**
     * @param  list<int>  $popIds
     * @return list<array{pelanggan: string, akun: string, referensi: string, tanggal: ?string, nominal: float, keterangan: string}>
     */
    private function detailUangDiterima(array $popIds, string $period, string $start, string $nextStart, string $column): array
    {
        // Pengembalian bulan P atas pembayaran bulan terkunci — sinkron dengan
        // `$reversals` di fillUangDiterima(). Di kolom Total tampil negatif
        // supaya jumlah rincian = angka Total.
        $reversals = DB::table('payments as pay')
            ->leftJoin('invoices as i', 'i.id', '=', 'pay.invoice_id')
            ->join('customers as c', 'c.id', '=', 'pay.customer_id')
            ->where('pay.payment_status', PaymentStatus::DITOLAK->value)
            ->where('pay.rejected_at', '>=', $start)
            ->where('pay.rejected_at', '<', $nextStart)
            ->where('pay.payment_date', '<', $start)
            ->whereIn('pay.pop_id', $popIds)
            ->orderBy('pay.rejected_at')
            ->get(['c.full_name', 'c.customer_code', 'c.cid', 'i.invoice_number', 'pay.payment_number', 'pay.payment_date', 'pay.rejected_at', 'pay.amount', 'pay.overpay_amount', 'pay.reject_reason'])
            ->map(fn ($r) => $this->row(
                $r->full_name,
                $r->cid ?: $r->customer_code,
                $r->invoice_number ?? $r->payment_number,
                $r->rejected_at,
                (float) Money::add($r->amount, $r->overpay_amount ?? 0),
                "Dikembalikan (bayar tgl {$r->payment_date}): ".($r->reject_reason ?? '-'),
            ));

        if ($column === 'dikembalikan') {
            return $reversals->values()->all();
        }

        $query = DB::table('payments as pay')
            ->leftJoin('invoices as i', 'i.id', '=', 'pay.invoice_id')
            ->join('customers as c', 'c.id', '=', 'pay.customer_id')
            ->where(self::countedAsOf($nextStart, 'pay'))
            ->where('pay.payment_date', '>=', $start)
            ->where('pay.payment_date', '<', $nextStart)
            ->whereIn('pay.pop_id', $popIds);

        if ($column === 'lebih_bayar') {
            return (clone $query)->where('pay.overpay_amount', '>', 0)
                ->orderBy('pay.payment_date')
                ->get(['c.full_name', 'c.customer_code', 'c.cid', 'i.invoice_number', 'pay.payment_date', 'pay.overpay_amount', 'pay.note'])
                ->map(fn ($r) => $this->row($r->full_name, $r->cid ?: $r->customer_code, $r->invoice_number ?? '-', $r->payment_date, (float) $r->overpay_amount, 'Lebih bayar'))
                ->all();
        }

        $rows = $query->orderBy('pay.payment_date')
            ->get(['c.full_name', 'c.customer_code', 'c.cid', 'i.invoice_number', 'i.invoice_type', 'i.billing_period', 'pay.payment_date', 'pay.amount', 'pay.note']);

        return $rows->filter(function ($r) use ($column, $period) {
            $kolom = match ($r->invoice_type) {
                InvoiceType::AWAL->value => 'aktivasi',
                InvoiceType::BULANAN->value => ($r->billing_period !== null && $r->billing_period < $period) ? 'piutang' : 'bulanan',
                default => 'lainnya',
            };

            return $column === 'total' || $kolom === $column;
        })->map(fn ($r) => $this->row($r->full_name, $r->cid ?: $r->customer_code, $r->invoice_number ?? '-', $r->payment_date, (float) $r->amount, (string) ($r->note ?? '')))
            ->when($column === 'total', fn ($list) => $list->concat(
                $reversals->map(fn (array $row) => [...$row, 'nominal' => -$row['nominal']])
            ))
            ->values()->all();
    }

    /**
     * @param  list<PaymentPeriodType>  $jenis  Klasifikasi (ADHOC-84
     *                                          §8.2) — cuma diisi baris yang benar-benar
     *                                          transaksi payment; default kosong untuk baris
     *                                          agregat invoice (tak ada payment tunggal untuk
     *                                          diklasifikasikan).
     * @return array{pelanggan: string, akun: string, referensi: string, tanggal: ?string, nominal: float, keterangan: string, jenis: list<PaymentPeriodType>}
     */
    private function row(string $pelanggan, string $akun, string $referensi, ?string $tanggal, float $nominal, string $keterangan, array $jenis = []): array
    {
        return [
            'pelanggan' => $pelanggan,
            'akun' => $akun,
            'referensi' => $referensi,
            'tanggal' => $tanggal,
            'nominal' => $nominal,
            'keterangan' => $keterangan,
            'jenis' => $jenis,
        ];
    }

    /**
     * Bekukan angka laporan periode yang sudah lewat untuk SEMUA POP
     * pusat/cabang — dijalankan scheduler `billing:close-period` tiap tanggal 1.
     *
     * Ini cuma snapshot angka, BUKAN kuncinya: periode terkunci ditentukan
     * kalender (`BookPeriod::isLocked()`), jadi periode lama tetap terkunci
     * walau scheduler telat/gagal jalan. Idempoten — POP yang sudah punya
     * snapshot dilewati, tidak ditimpa (snapshot pertama = angka resmi).
     * Tidak ada buka ulang: kunci periode permanen.
     *
     * @return int jumlah POP yang baru dibekukan
     */
    public function closePeriod(string $period): int
    {
        self::parsePeriod($period);

        if (! BookPeriod::isLocked($period)) {
            throw ValidationException::withMessages([
                'period' => 'Periode yang masih berjalan belum bisa ditutup. Tunggu bulan berganti.',
            ]);
        }

        $closed = 0;

        $branches = Pop::query()->whereIn('type', ['pusat', 'cabang'])->orderBy('id')->get();

        foreach ($branches as $branch) {
            $created = DB::transaction(function () use ($period, $branch): bool {
                if (PeriodClosing::query()->where('period', $period)->where('pop_id', $branch->id)->lockForUpdate()->exists()) {
                    return false;
                }

                $closing = PeriodClosing::create([
                    'period' => $period,
                    'pop_id' => $branch->id,
                    'figures' => $this->figures($period, [$branch->id])[$branch->id],
                    'closed_by' => null,
                    'closed_at' => now(),
                ]);

                $this->audit($closing, 'periode_ditutup', ['period' => $period, 'pop_id' => $branch->id]);

                return true;
            });

            $closed += (int) $created;
        }

        return $closed;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function audit(PeriodClosing $closing, string $action, array $values): void
    {
        AuditLog::create([
            // Ditutup sistem (scheduler), bukan orang.
            'user_id' => null,
            'module' => 'laporan',
            'action' => $action,
            'auditable_type' => PeriodClosing::class,
            'auditable_id' => $closing->id,
            'new_values' => $values,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }
}
