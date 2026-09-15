<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\RevenueCategory;
use App\Models\RevenueSubcategory;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Penerbitan Tagihan Manual (ADHOC-60).
 *
 * Menggantikan `CustomerController::storeManualInvoice()` yang cuma bisa
 * menagih kombinasi kolom biaya tetap di `invoices`. Sekarang admin merinci
 * sendiri barisnya per kategori pendapatan, dan rinciannya yang jadi dasar
 * pemecahan angka di Laporan Tagihan & Laporan Pembayaran.
 *
 * Seluruh nominal dihitung ULANG di sini; angka kiriman klien statusnya cuma
 * preview — pola yang sudah ditegakkan `InitialInvoiceService`. Khusus baris
 * berkategori `jasa_layanan_internet`, nominalnya bahkan tidak dibaca sama
 * sekali dari request melainkan diambil dari `customer_services.monthly_price`:
 * harga langganan datang dari master layanan, bukan ketikan admin
 * (BUSINESS_RULES.md §7 "Bukan Manual dari Nol").
 */
class ManualInvoiceService
{
    public function __construct(
        private readonly InvoiceNumberGenerator $numbers,
        private readonly InvoiceItemBuilder $items,
    ) {}

    /**
     * @param  array{billing_period: string, issue_date: string, due_date: string, invoice_type: string, lines: list<array<string, mixed>>}  $validated
     *
     * @throws ValidationException
     */
    public function create(Customer $customer, array $validated): Invoice
    {
        $service = $customer->customerService;

        if (! $service) {
            throw ValidationException::withMessages([
                'customer_id' => 'Pelanggan belum punya layanan aktif — tagihan tidak bisa diterbitkan.',
            ]);
        }

        if (! in_array($customer->status, ['active', 'suspended'], true) && $customer->data_completeness_status !== 'siap_billing') {
            throw ValidationException::withMessages([
                'customer_id' => 'Tagihan hanya bisa dibuat untuk pelanggan berstatus aktif, suspend, atau siap billing.',
            ]);
        }

        $lines = $this->resolveLines($validated['lines'], (float) $service->monthly_price);
        $type = ! empty($validated['invoice_type'])
            ? InvoiceType::from($validated['invoice_type'])
            : $this->resolveTypeFromLines($customer, $lines);

        $this->assertTypeMatchesLines($type, $lines);
        $this->assertNotDuplicate($customer, $type, $validated['billing_period']);

        $subtotal = Money::sum(array_column($lines, 'amount'));

        // Diskon & PPN mengikuti layanan pelanggan, tidak diketik admin —
        // sama seperti GenerateMonthlyInvoicesCommand. Tagihan INSIDENTAL
        // sengaja TIDAK kena diskon langganan: diskon itu melekat ke harga
        // paket bulanan, bukan ke jasa perbaikan yang ditagih terpisah.
        $discount = $type === InvoiceType::INSIDENTAL
            ? 0.0
            : Money::atLeastZero($service->discount ?? 0);
        $ppnRate = max(0, (float) ($service->ppn ?? 0));

        $afterDiscount = Money::atLeastZero(Money::sub($subtotal, $discount));
        $ppnAmount = Money::of($afterDiscount * ($ppnRate / 100));
        $total = Money::add($afterDiscount, $ppnAmount);

        try {
            return DB::transaction(function () use ($customer, $service, $validated, $type, $lines, $subtotal, $discount, $ppnRate, $total) {
                $invoice = Invoice::create([
                    // Dipanggil DI DALAM transaksi — lockForUpdate() di
                    // generator baru bermakna selama transaksinya hidup.
                    'invoice_number' => $this->numbers->nextFor($validated['billing_period']),
                    'invoice_type' => $type->value,
                    'customer_id' => $customer->id,
                    'pop_id' => $customer->pop_id,
                    'customer_service_id' => $service->id,
                    'internet_package_id' => $service->internet_package_id,
                    'billing_period' => $validated['billing_period'],
                    'issue_date' => $validated['issue_date'],
                    'due_date' => $validated['due_date'],
                    'subtotal' => $subtotal,
                    'discount' => $discount,
                    'ppn' => $ppnRate,
                    'total_amount' => $total,
                    'paid_amount' => 0,
                    'remaining_amount' => $total,
                    'invoice_status' => InvoiceStatus::BELUM_DIBAYAR->value,
                    'created_by' => auth()->id(),
                ]);

                $this->items->rebuildFor($invoice, $lines);

                // Ditulis eksplisit karena `Invoice` cuma mengaudit `updated`
                // dan `deleted` — menambahkan `created` ke `$auditEvents` akan
                // ikut mencatat ribuan tagihan bulanan otomatis tiap bulan,
                // membanjiri audit log dengan baris tanpa aktor. Yang perlu
                // dijejaki di sini justru sebaliknya: tagihan yang diterbitkan
                // MANUSIA, lengkap dengan rinciannya (DEFINITION_OF_DONE.md —
                // audit log wajib untuk data penting).
                AuditLog::create([
                    'user_id' => auth()->id(),
                    'module' => 'Tagihan',
                    'action' => 'create',
                    'auditable_type' => $invoice::class,
                    'auditable_id' => $invoice->id,
                    'old_values' => null,
                    'new_values' => $invoice->toArray() + ['items' => $invoice->items()->get()->toArray()],
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'created_at' => now(),
                ]);

                return $invoice;
            });
        } catch (InvalidArgumentException $e) {
            // `InvoiceObserver::creating()` menolak duplikat burst & pelanggaran
            // satu-langganan-per-periode dengan InvalidArgumentException, dan
            // tidak ada handler global untuk itu — tanpa penangkapan di sini
            // double-submit form ini jadi HTTP 500, bukan pesan yang bisa
            // dibaca admin. Guard-nya TIDAK dilemahkan: form manual justru
            // jalur yang paling rawan submit ganda.
            throw ValidationException::withMessages([
                'lines' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Ubah baris kiriman form jadi spesifikasi baris untuk `InvoiceItemBuilder`.
     *
     * @param  list<array<string, mixed>>  $rawLines
     * @return list<array{category_code: string, subcategory_code: string|null, custom_name: string|null, description: string|null, amount: float}>
     */
    private function resolveLines(array $rawLines, float $monthlyPrice): array
    {
        $categories = RevenueCategory::all()->keyBy('id');
        $subcategories = RevenueSubcategory::all()->keyBy('id');
        $lines = [];

        foreach ($rawLines as $index => $raw) {
            $category = $categories->get((int) ($raw['revenue_category_id'] ?? 0));

            if (! $category) {
                throw ValidationException::withMessages([
                    "lines.{$index}.revenue_category_id" => 'Kategori pendapatan tidak valid.',
                ]);
            }

            // Harga langganan datang dari master layanan. Nominal kiriman
            // klien untuk baris ini SENGAJA diabaikan, bukan sekadar
            // di-`readonly` di form — readonly cuma penghalang UI.
            $amount = $category->isSubscription()
                ? $monthlyPrice
                : Money::atLeastZero($raw['amount'] ?? 0);

            if ($category->usesCustomName()) {
                $customName = trim((string) ($raw['custom_name'] ?? ''));

                if ($customName === '') {
                    throw ValidationException::withMessages([
                        "lines.{$index}.custom_name" => 'Nama kategori wajib diisi untuk baris berkategori "Lainnya".',
                    ]);
                }

                $lines[] = [
                    'category_code' => $category->code,
                    'subcategory_code' => null,
                    'custom_name' => $customName,
                    'description' => $this->description($raw),
                    'amount' => $amount,
                ];

                continue;
            }

            $subcategory = $subcategories->get((int) ($raw['revenue_subcategory_id'] ?? 0));

            if (! $subcategory || $subcategory->revenue_category_id !== $category->id) {
                throw ValidationException::withMessages([
                    "lines.{$index}.revenue_subcategory_id" => "Sub kategori wajib dipilih dan harus milik kategori {$category->name}.",
                ]);
            }

            $lines[] = [
                'category_code' => $category->code,
                'subcategory_code' => $subcategory->code,
                'custom_name' => null,
                'description' => $this->description($raw),
                'amount' => $amount,
            ];
        }

        $lines = array_values(array_filter($lines, fn (array $line) => Money::greaterThan($line['amount'], 0)));

        if ($lines === []) {
            throw ValidationException::withMessages([
                'lines' => 'Tagihan wajib punya minimal satu baris bernominal lebih dari nol.',
            ]);
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function description(array $raw): ?string
    {
        $description = trim((string) ($raw['description'] ?? ''));

        return $description === '' ? null : $description;
    }

    /**
     * Jenis tagihan dan isinya harus konsisten — ini yang menjaga guard
     * "satu tagihan langganan per periode" tidak bisa dilewati cuma dengan
     * memilih jenis INSIDENTAL untuk tagihan yang sebenarnya berisi langganan.
     *
     * @param  list<array{category_code: string, amount: float}>  $lines
     */
    private function assertTypeMatchesLines(InvoiceType $type, array $lines): void
    {
        $hasSubscriptionLine = in_array(
            RevenueCategory::CODE_JASA_LAYANAN_INTERNET,
            array_column($lines, 'category_code'),
            true
        );

        if ($hasSubscriptionLine && $type === InvoiceType::INSIDENTAL) {
            throw ValidationException::withMessages([
                'invoice_type' => 'Tagihan yang memuat baris Jasa Layanan Internet tidak boleh berjenis Insidental — pilih Bulanan atau Reaktivasi.',
            ]);
        }

        if (! $hasSubscriptionLine && $type !== InvoiceType::INSIDENTAL) {
            throw ValidationException::withMessages([
                'invoice_type' => "Tagihan tanpa baris Jasa Layanan Internet harus berjenis Insidental, bukan {$type->label()}.",
            ]);
        }
    }

    /**
     * Pengecekan ramah SEBELUM menyentuh DB, supaya admin dapat pesan yang
     * menyebut periodenya. `InvoiceObserver` tetap jadi jaring terakhir untuk
     * jalur lain (import, tinker) dan untuk balapan dua request bersamaan.
     *
     * Cakupannya dibuat PERSIS sama dengan `InvoiceObserver`: hanya jenis di
     * `Invoice::SUBSCRIPTION_TYPES`. INSIDENTAL dilewati karena beberapa
     * pekerjaan berbayar memang boleh ditagih di bulan yang sama, dan
     * REAKTIVASI juga dilewati karena pelanggan yang disuspend lalu aktif lagi
     * di bulan yang sama memang boleh punya record tambahan (lihat docblock
     * `Invoice::SUBSCRIPTION_TYPES`). Menyaring lebih ketat di sini akan
     * menolak kombinasi yang justru sudah dites boleh.
     */
    private function assertNotDuplicate(Customer $customer, InvoiceType $type, string $billingPeriod): void
    {
        if (! in_array($type->value, Invoice::SUBSCRIPTION_TYPES, true)) {
            return;
        }

        $exists = Invoice::where('customer_id', $customer->id)
            ->where('billing_period', $billingPeriod)
            ->where('invoice_status', '!=', InvoiceStatus::BATAL->value)
            ->whereIn('invoice_type', Invoice::SUBSCRIPTION_TYPES)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'billing_period' => "Pelanggan ini sudah punya tagihan langganan untuk periode {$billingPeriod}.",
            ]);
        }
    }

    /**
     * Tentukan jenis tagihan secara otomatis dari baris yang dipilih.
     *
     * @param  list<array{category_code: string, amount: float}>  $lines
     */
    public function resolveTypeFromLines(Customer $customer, array $lines): InvoiceType
    {
        $hasSubscriptionLine = in_array(
            RevenueCategory::CODE_JASA_LAYANAN_INTERNET,
            array_column($lines, 'category_code'),
            true
        );

        if ($hasSubscriptionLine) {
            return $customer->status === 'suspended'
                ? InvoiceType::REAKTIVASI
                : InvoiceType::BULANAN;
        }

        return InvoiceType::INSIDENTAL;
    }
}
