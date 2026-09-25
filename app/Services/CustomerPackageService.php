<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Customer;
use App\Models\CustomerPackageChange;
use App\Models\CustomerService;
use App\Models\InternetPackage;
use App\Models\Invoice;
use App\Models\RevenueCategory;
use App\Models\RevenueSubcategory;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Ganti paket internet pelanggan aktif — upgrade/downgrade (ADHOC-68).
 *
 * Rumus tunggal (docs/plan/billing/upgrade-downgrade/analisa-upgrade-downgrade-paket.md §1/§3):
 *
 *     total_invoice_periode = max(0, subtotal_paket - discount) x (1 + ppn%)
 *     sisa_tagih            = max(0, total_invoice_periode - sudah_dibayar)
 *     deposit_baru          = max(0, sudah_dibayar - total_invoice_periode)
 *
 * `subtotal_paket` dijumlah per-segmen hari (n segmen kalau paket diganti
 * berkali-kali dalam satu periode) — lihat buildSegments(). Diskon & PPN
 * TIDAK diprorate, dikenakan sekali dari nilai `customer_service` terbaru.
 *
 * `customer_services.other_fee` SENGAJA TIDAK ikut ditagih di sini —
 * konsisten dengan fix 2026-09-14 (`CustomerController::store()`, CID
 * C1X4ARQ000004): kolom itu bukan komponen tagihan bulanan berulang, cuma
 * dipakai sekali di Tagihan Awal, dan `GenerateMonthlyInvoicesCommand` juga
 * tidak pernah membacanya. Draft rancangan awal ADHOC-68 sempat menyimpulkan
 * sebaliknya ("sama seperti `total_monthly_bill` sekarang") berdasarkan
 * `CustomerPackageService::change()` versi LAMA yang ternyata belum ikut
 * dibenerin di fix 09-14 — kesimpulan itu salah, jangan diulang.
 */
class CustomerPackageService
{
    public function __construct(
        private readonly CustomerBalanceService $balanceService,
        private readonly InvoiceItemBuilder $itemBuilder,
        private readonly InitialInvoiceService $initialInvoiceService,
    ) {}

    /**
     * @throws InvalidArgumentException paket baru sama dengan yang berjalan
     * @throws RuntimeException layanan belum ada, atau upgrade diblok tunggakan
     */
    public function change(Customer $customer, InternetPackage $newPackage): CustomerService
    {
        $service = $customer->customerService;

        if (! $service) {
            throw new RuntimeException('Pelanggan belum punya layanan aktif — pakai form edit pelanggan untuk memilih paket pertama kali.');
        }

        if ($service->internet_package_id === $newPackage->id) {
            throw new InvalidArgumentException('Paket baru sama dengan paket yang sedang berjalan.');
        }

        $isUpgrade = (float) $newPackage->monthly_price > (float) $service->monthly_price;
        $currentPeriod = now()->format('Y-m');

        return DB::transaction(function () use ($customer, $service, $newPackage, $isUpgrade, $currentPeriod) {
            // Upgrade wajib lunasi tunggakan periode SEBELUMNYA dulu (§2.9).
            // Downgrade selalu boleh jalan — mengurangi beban tagihan pelanggan
            // ke depan, tidak ada alasan bisnis untuk diblok.
            if ($isUpgrade) {
                $this->assertNoOutstandingPreviousPeriod($customer);
            }

            $oldPackageId = $service->internet_package_id;
            $oldMonthlyPrice = (float) $service->monthly_price;

            // Invoice periode berjalan milik layanan ini dikunci dulu — race
            // dengan payment yang masuk bersamaan (recalculateFromPayments di
            // ujung method ini) harus konsisten satu sama lain.
            $invoice = Invoice::query()
                ->where('customer_service_id', $service->id)
                ->where('billing_period', $currentPeriod)
                ->whereIn('invoice_type', Invoice::SUBSCRIPTION_TYPES)
                ->where('invoice_status', '!=', InvoiceStatus::BATAL->value)
                ->lockForUpdate()
                ->first();

            $this->applyPackageToService($service, $newPackage);
            $customer->update(['internet_package_id' => $newPackage->id]);

            // Belum ada invoice periode berjalan (belum lewat tanggal generate,
            // atau baru aktivasi) — tidak ada yang perlu diprorate, efeknya
            // murni ke periode berikutnya, persis perilaku lama.
            if (! $invoice) {
                return $service->fresh();
            }

            $this->recomputeInvoiceForPackageChange(
                $customer, $service, $invoice, $currentPeriod,
                $oldPackageId, $oldMonthlyPrice, $newPackage,
            );

            return $service->fresh();
        });
    }

    /**
     * `total_monthly_bill` SENGAJA TIDAK ikutkan `other_fee` (konsisten
     * dengan fix 2026-09-14 di `CustomerController::store()`) —
     * `other_fee` bukan komponen tagihan bulanan berulang, cuma dipakai
     * sekali di Tagihan Awal. `GenerateMonthlyInvoicesCommand` juga tidak
     * pernah membaca kolom ini. Versi lama method ini sempat menambahkannya
     * lagi di sini (bug regresi terhadap fix 09-14) — jangan diulang.
     */
    private function applyPackageToService(CustomerService $service, InternetPackage $newPackage): void
    {
        $downLabel = $newPackage->download_speed_mbps !== null ? $newPackage->download_speed_mbps.' Mbps' : null;
        $upLabel = $newPackage->upload_speed_mbps !== null ? $newPackage->upload_speed_mbps.' Mbps' : null;

        $monthlyPrice = (float) $newPackage->monthly_price;
        $discount = (float) ($service->discount ?? 0);
        $ppnPercent = (float) ($service->ppn ?? 0);

        $discountedPrice = Money::atLeastZero(Money::sub($monthlyPrice, $discount));
        $totalBill = $discountedPrice * (1 + $ppnPercent / 100);

        $service->update([
            'internet_package_id' => $newPackage->id,
            'package_name_snapshot' => $newPackage->name,
            'download_speed_snapshot' => $downLabel,
            'upload_speed_snapshot' => $upLabel,
            'monthly_price' => $monthlyPrice,
            'total_monthly_bill' => $totalBill,
        ]);
    }

    /**
     * Upgrade diblok kalau ada invoice `belum_dibayar`/`sebagian` di periode
     * SEBELUM bulan berjalan. Tunggakan periode berjalan sendiri (yang justru
     * sedang diproses ulang oleh prorate) bukan blocker — lihat §2.9.
     */
    private function assertNoOutstandingPreviousPeriod(Customer $customer): void
    {
        $piutang = Invoice::query()
            ->where('customer_id', $customer->id)
            ->piutang()
            ->orderBy('billing_period')
            ->first();

        if ($piutang) {
            throw new RuntimeException(
                "Upgrade paket diblok — pelanggan masih punya tunggakan {$piutang->invoice_number} ".
                "periode {$piutang->billing_period}. Lunasi dulu sebelum upgrade."
            );
        }
    }

    /**
     * Jendela hari yang di-tagih invoice periode berjalan ini.
     *
     * BULANAN → sebulan kalender penuh (`billing_period`). AWAL → cuma dari
     * sehari SETELAH aktivasi sampai akhir bulan (hari aktivasi digratiskan,
     * konvensi legacy yang sama dipakai `InitialInvoiceService::calculate()`)
     * — kalau tidak, `days_in_period` di sini menyimpang dari nominal prorate
     * yang sudah ditagih ke pelanggan di invoice AWAL itu sendiri (§2.6).
     *
     * @return array{0: Carbon, 1: Carbon, 2: int}
     */
    private function resolvePeriodWindow(Invoice $invoice): array
    {
        if ($invoice->invoice_type?->value !== InvoiceType::AWAL->value) {
            $start = Carbon::parse($invoice->billing_period.'-01')->startOfDay();
            $end = $start->copy()->endOfMonth()->startOfDay();

            return [$start, $end, $start->daysInMonth];
        }

        $issue = Carbon::parse($invoice->issue_date)->startOfDay();
        $daysInMonth = $issue->daysInMonth;
        $prorateDays = $daysInMonth - $issue->day;

        if ($prorateDays <= 0) {
            // Aktivasi di hari terakhir bulan — ditagih sebulan penuh (lihat
            // InitialInvoiceService), jadi jendelanya balik ke sebulan penuh.
            $start = $issue->copy()->startOfMonth();
            $prorateDays = $daysInMonth;
        } else {
            $start = $issue->copy()->addDay();
        }

        $end = $issue->copy()->endOfMonth()->startOfDay();

        return [$start, $end, $prorateDays];
    }

    /**
     * Hitung ulang total invoice periode berjalan, simpan audit trail
     * `customer_package_changes`, lalu terapkan sisa tagih / deposit.
     */
    private function recomputeInvoiceForPackageChange(
        Customer $customer,
        CustomerService $service,
        Invoice $invoice,
        string $currentPeriod,
        int $oldPackageId,
        float $oldMonthlyPrice,
        InternetPackage $newPackage,
    ): void {
        [$periodStart, $periodEnd, $daysInPeriod] = $this->resolvePeriodWindow($invoice);

        $effectiveDate = Carbon::today()->startOfDay();
        if ($effectiveDate->lt($periodStart)) {
            $effectiveDate = $periodStart->copy();
        }
        if ($effectiveDate->gt($periodEnd)) {
            $effectiveDate = $periodEnd->copy();
        }

        $history = CustomerPackageChange::query()
            ->where('customer_service_id', $service->id)
            ->where('billing_period', $currentPeriod)
            ->orderBy('effective_date')
            ->orderBy('id')
            ->get();

        [$segments, $localOld] = $this->buildSegments(
            $history, $periodStart, $periodEnd, $oldPackageId, $oldMonthlyPrice, $effectiveDate, $newPackage,
        );

        $subtotalPaket = Money::sum(array_map(
            fn (array $segment) => ($segment['price'] / $daysInPeriod) * $segment['days'],
            $segments,
        ));

        // Biaya sekali-bayar yang sudah melekat di invoice AWAL (instalasi,
        // kabel, tiang, materai) TIDAK ikut diprorate — cuma porsi paket
        // internet yang berubah. Untuk invoice BULANAN, semua kolom ini nol.
        $oneTimeFees = Money::sum([
            $invoice->extra_installation_fee ?? 0,
            $invoice->extra_cable_fee ?? 0,
            $invoice->extra_pole_fee ?? 0,
            $invoice->other_fee ?? 0,
        ]);

        $fullSubtotal = Money::add($subtotalPaket, $oneTimeFees);

        $discount = (float) ($service->discount ?? 0);
        $ppnPercent = (float) ($service->ppn ?? 0);

        $afterDiscount = Money::atLeastZero(Money::sub($fullSubtotal, $discount));
        $totalInvoicePeriode = $afterDiscount * (1 + $ppnPercent / 100);

        $previouslyPaid = (float) $invoice->paid_amount;
        $depositBaru = Money::atLeastZero(Money::sub($previouslyPaid, $totalInvoicePeriode));

        $invoice->subtotal = $fullSubtotal;
        $invoice->internet_package_id = $newPackage->id;
        $invoice->total_amount = $totalInvoicePeriode;
        $invoice->save();

        $this->rebuildInvoiceItems($invoice, $subtotalPaket, $currentPeriod);

        $invoice->recalculateFromPayments();

        $depositMutationId = null;
        if (Money::greaterThan($depositBaru, 0)) {
            $mutation = $this->balanceService->creditWithoutPayment(
                $customer,
                $depositBaru,
                $invoice->pop_id,
                "Deposit dari downgrade paket — periode {$currentPeriod}, invoice {$invoice->invoice_number}",
            );
            $depositMutationId = $mutation->id;
        }

        CustomerPackageChange::create([
            'customer_id' => $customer->id,
            'customer_service_id' => $service->id,
            'old_internet_package_id' => $localOld['package_id'],
            'new_internet_package_id' => $newPackage->id,
            'old_monthly_price' => $localOld['price'],
            'new_monthly_price' => (float) $newPackage->monthly_price,
            'billing_period' => $currentPeriod,
            'effective_date' => $effectiveDate->toDateString(),
            'days_in_period' => $daysInPeriod,
            'days_old_used' => $localOld['days'],
            'days_new_used' => max(0, $effectiveDate->diffInDays($periodEnd) + 1),
            'prorate_old_amount' => Money::of(($localOld['price'] / $daysInPeriod) * $localOld['days']),
            'prorate_new_amount' => Money::of(($newPackage->monthly_price / $daysInPeriod) * max(0, $effectiveDate->diffInDays($periodEnd) + 1)),
            'total_recomputed' => $totalInvoicePeriode,
            'previously_paid' => $previouslyPaid,
            'resulting_invoice_id' => $invoice->id,
            'deposit_mutation_id' => $depositMutationId,
            'created_by' => auth()->id(),
        ]);
    }

    /**
     * Tulis ulang `invoice_items` supaya rincian tagihan ikut nyambung
     * dengan `subtotal` baru hasil prorate — tanpa ini, Detail Tagihan &
     * kwitansi tetap nampilin baris nominal lama walau totalnya sudah
     * berubah (`InvoiceItemBuilder::assertMatchesSubtotal()` bakal langsung
     * ketauan kalau jumlahnya menyimpang, jadi baris ini WAJIB persis sama
     * dengan `$invoice->subtotal` yang baru saja di-set).
     *
     * BULANAN cuma satu baris (langganan). AWAL pakai `InitialInvoiceService::lineSpecs()`
     * yang sama dipakai saat invoice ini pertama kali terbit — biaya sekali-bayar
     * (instalasi/kabel/tiang/materai) diambil dari kolom invoice yang sudah ada,
     * cuma porsi `prorate_amount`-nya yang diganti dengan `$subtotalPaket` baru.
     */
    private function rebuildInvoiceItems(Invoice $invoice, float $subtotalPaket, string $billingPeriod): void
    {
        if ($invoice->invoice_type?->value === InvoiceType::AWAL->value) {
            $billing = [
                'prorate_amount' => $subtotalPaket,
                'extra_installation_fee' => (float) ($invoice->extra_installation_fee ?? 0),
                'extra_cable_fee' => (float) ($invoice->extra_cable_fee ?? 0),
                'extra_pole_fee' => (float) ($invoice->extra_pole_fee ?? 0),
                'other_fee' => (float) ($invoice->other_fee ?? 0),
            ];

            $this->itemBuilder->rebuildFor($invoice, $this->initialInvoiceService->lineSpecs($billing));

            return;
        }

        $this->itemBuilder->rebuildFor($invoice, [[
            'category_code' => RevenueCategory::CODE_JASA_LAYANAN_INTERNET,
            'subcategory_code' => RevenueSubcategory::CODE_LANGGANAN_BULANAN,
            'description' => "Langganan {$billingPeriod} (prorata ganti paket)",
            'amount' => $subtotalPaket,
        ]]);
    }

    /**
     * Rekonstruksi seluruh segmen paket dalam periode ini dari riwayat
     * `customer_package_changes` + segmen baru yang baru terjadi — dipakai
     * jumlah total (§2.8, n-segmen), BUKAN cuma "paket sekarang vs sebelumnya".
     *
     * @param  Collection<int, CustomerPackageChange>  $history
     * @return array{0: list<array{price: float, start: Carbon, end: Carbon, days: int}>, 1: array{package_id: int, price: float, days: int}}
     */
    private function buildSegments(
        Collection $history,
        Carbon $periodStart,
        Carbon $periodEnd,
        int $oldPackageId,
        float $oldMonthlyPrice,
        Carbon $effectiveDate,
        InternetPackage $newPackage,
    ): array {
        $segments = [];

        $addSegment = function (float $price, Carbon $start, Carbon $end) use (&$segments) {
            $days = $end->gte($start) ? $start->diffInDays($end) + 1 : 0;
            $segments[] = ['price' => $price, 'start' => $start, 'end' => $end, 'days' => $days];
        };

        if ($history->isEmpty()) {
            $localOldStart = $periodStart;
            $localOldPackageId = $oldPackageId;
            $localOldPrice = $oldMonthlyPrice;
        } else {
            $first = $history->first();
            $addSegment((float) $first->old_monthly_price, $periodStart, Carbon::parse($first->effective_date)->subDay());

            for ($i = 0; $i < $history->count() - 1; $i++) {
                $current = $history[$i];
                $next = $history[$i + 1];
                $addSegment(
                    (float) $current->new_monthly_price,
                    Carbon::parse($current->effective_date),
                    Carbon::parse($next->effective_date)->subDay(),
                );
            }

            $last = $history->last();
            $localOldStart = Carbon::parse($last->effective_date);
            $localOldPackageId = $last->new_internet_package_id;
            $localOldPrice = (float) $last->new_monthly_price;
        }

        // Segmen lokal paket yang baru saja "ditutup" oleh perubahan ini.
        $addSegment($localOldPrice, $localOldStart, $effectiveDate->copy()->subDay());
        $localOldDays = $effectiveDate->copy()->subDay()->gte($localOldStart)
            ? $localOldStart->diffInDays($effectiveDate->copy()->subDay()) + 1
            : 0;

        // Segmen paket baru, dari hari efektif sampai akhir periode.
        $addSegment((float) $newPackage->monthly_price, $effectiveDate, $periodEnd);

        return [$segments, [
            'package_id' => $localOldPackageId,
            'price' => $localOldPrice,
            'days' => $localOldDays,
        ]];
    }
}
