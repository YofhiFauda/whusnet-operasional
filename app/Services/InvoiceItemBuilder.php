<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\RevenueCategory;
use App\Models\RevenueSubcategory;
use App\Support\Money;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Satu-satunya penulis baris `invoice_items` (ADHOC-60).
 *
 * Tiga jalur menerbitkan tagihan — `InitialInvoiceService` (AWAL),
 * `GenerateMonthlyInvoicesCommand` (BULANAN), `ManualInvoiceService` (manual) —
 * dan sebelumnya masing-masing merakit rincian biayanya sendiri lewat kolom
 * tetap di `invoices`. Dikumpulkan di sini supaya rumusnya tidak bercabang,
 * dan supaya invariannya cuma perlu ditegakkan di satu tempat:
 *
 *     SUM(invoice_items.amount) == invoices.subtotal
 *
 * Baris adalah komponen SUBTOTAL (DPP — sebelum diskon & PPN). Diskon dan PPN
 * berlaku di level tagihan dan sengaja TIDAK dipecah per baris: PPN bukan
 * pendapatan, melainkan titipan pajak. Percobaan pertama (ADHOC-58, di-rewind)
 * menempelkan PPN cuma ke bagian langganan sehingga jumlah baris tidak pernah
 * sama dengan angka mana pun di tagihan — laporan per kategori jadi tak bisa
 * direkonsiliasi dan tak ada assert yang bisa menjaganya.
 *
 * Master di-cache per-instance: satu tagihan bisa punya banyak baris di
 * kategori yang sama, dan tanpa cache tiap baris jadi satu query sendiri.
 */
class InvoiceItemBuilder
{
    /** @var Collection<string, RevenueCategory>|null */
    private ?Collection $categories = null;

    /** @var Collection<string, RevenueSubcategory>|null */
    private ?Collection $subcategories = null;

    /**
     * Tulis ulang seluruh baris rincian sebuah tagihan.
     *
     * Tiap baris: `category_code` wajib; `subcategory_code` wajib KECUALI untuk
     * kategori `lainnya` yang justru memakai `custom_name` (nama ketikan admin).
     * Baris bernominal nol dilewati — biaya tambahan yang tidak dipakai tidak
     * perlu jadi baris kosong di kwitansi pelanggan.
     *
     * @param  list<array{category_code: string, subcategory_code?: string|null, custom_name?: string|null, description?: string|null, amount: mixed}>  $lines
     * @return Collection<int, InvoiceItem>
     */
    public function rebuildFor(Invoice $invoice, array $lines): Collection
    {
        $rows = [];
        $sortOrder = 0;

        foreach ($lines as $line) {
            $amount = Money::of($line['amount'] ?? 0);

            if (Money::compare($amount, 0) <= 0) {
                continue;
            }

            $rows[] = $this->buildRow($line, $amount, $sortOrder += 10);
        }

        if ($rows === []) {
            throw new InvalidArgumentException('Tagihan wajib punya minimal satu baris rincian bernominal lebih dari nol.');
        }

        $this->assertMatchesSubtotal($invoice, $rows);

        $invoice->items()->delete();

        foreach ($rows as $row) {
            InvoiceItem::create($row + ['invoice_id' => $invoice->id]);
        }

        return $invoice->items()->get();
    }

    /**
     * @param  array{category_code: string, subcategory_code?: string|null, custom_name?: string|null, description?: string|null, amount: mixed}  $line
     * @return array<string, mixed>
     */
    private function buildRow(array $line, float $amount, int $sortOrder): array
    {
        $category = $this->category($line['category_code']);

        if ($category->usesCustomName()) {
            $customName = trim((string) ($line['custom_name'] ?? ''));

            if ($customName === '') {
                throw new InvalidArgumentException("Baris kategori {$category->name} wajib diisi nama kategorinya.");
            }

            return [
                'revenue_category_id' => $category->id,
                'revenue_subcategory_id' => null,
                'category_name_snapshot' => $category->name,
                'subcategory_name_snapshot' => $customName,
                'description' => $line['description'] ?? null,
                'amount' => $amount,
                'sort_order' => $sortOrder,
            ];
        }

        $subcategory = $this->subcategory((string) ($line['subcategory_code'] ?? ''));

        if ($subcategory->revenue_category_id !== $category->id) {
            throw new InvalidArgumentException("Sub kategori {$subcategory->name} bukan milik kategori {$category->name}.");
        }

        return [
            'revenue_category_id' => $category->id,
            'revenue_subcategory_id' => $subcategory->id,
            'category_name_snapshot' => $category->name,
            'subcategory_name_snapshot' => $subcategory->name,
            'description' => $line['description'] ?? null,
            'amount' => $amount,
            'sort_order' => $sortOrder,
        ];
    }

    /**
     * Penjaga invarian. Dilanggar = bug pemanggil, bukan salah input admin —
     * karena itu `InvalidArgumentException`, bukan `ValidationException`:
     * yang harus diperbaiki adalah kodenya, bukan isian formnya.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function assertMatchesSubtotal(Invoice $invoice, array $rows): void
    {
        $sum = Money::sum(array_column($rows, 'amount'));
        $subtotal = Money::of($invoice->subtotal);

        if (Money::compare($sum, $subtotal) !== 0) {
            throw new InvalidArgumentException(
                "Jumlah baris rincian ({$sum}) tidak sama dengan subtotal tagihan ({$subtotal}). ".
                'Baris rincian adalah komponen subtotal — diskon & PPN tidak boleh ikut dipecah per baris.'
            );
        }
    }

    private function category(string $code): RevenueCategory
    {
        $this->categories ??= RevenueCategory::all()->keyBy('code');

        return $this->categories->get($code)
            ?? throw new InvalidArgumentException("Kategori pendapatan `{$code}` tidak ditemukan.");
    }

    /**
     * Sengaja TIDAK memfilter `is_active`: sub kategori yang dinonaktifkan
     * admin harus tetap bisa dirujuk oleh jalur otomatis dan backfill, kalau
     * tidak penerbitan tagihan bulanan bisa mati total cuma karena satu master
     * dimatikan. Yang menyaring pilihan aktif adalah form-nya.
     */
    private function subcategory(string $code): RevenueSubcategory
    {
        $this->subcategories ??= RevenueSubcategory::all()->keyBy('code');

        return $this->subcategories->get($code)
            ?? throw new InvalidArgumentException("Sub kategori pendapatan `{$code}` tidak ditemukan.");
    }
}
