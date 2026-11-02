<?php

namespace App\Services;

use App\Enums\CReqVerificationStatus;
use App\Enums\TaskType;
use App\Enums\WorkflowTransition;
use App\Models\InventorySerialSale;
use App\Models\InvoiceItem;
use App\Models\TaskBillingItem;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Laporan "SN terjual belum ditagih" — rancangan 2026-10-31, `docs/plan/
 * warehouse/rancangan-modem-terjual-visibilitas-dan-rekonsiliasi.md` §5.
 *
 * Setiap baris `inventory_serial_sales` (SN modem yang dijual ke pelanggan,
 * "Beli dari Gudang") WAJIB berujung ke satu baris tagihan — baik di
 * `invoice_items` (jalur Pemasangan, lewat `InitialInvoiceService`) atau
 * `task_billing_items` (jalur C-REQ/MTN, lewat `TaskCreqBillingController`).
 * Sale yang tidak punya penaut mana pun dan tidak sedang menunggu langkah
 * wajar (BD, approve CS) adalah ANOMALI — modem sudah keluar gudang tapi
 * tidak pernah tertagih.
 *
 * Baris "wajar" (Menunggu BD / Menunggu approve CS / Menunggu verifikasi CS)
 * TETAP ditampilkan supaya jeda kelihatan, tapi TIDAK dihitung sebagai
 * anomali di ringkasan — lihat `summary()`.
 */
class ModemSaleReconciliationService
{
    public const STATUS_BILLED = 'billed';

    public const STATUS_WAITING_BD = 'waiting_bd';

    public const STATUS_WAITING_CREQ_APPROVAL = 'waiting_creq_approval';

    public const STATUS_WAITING_CS_VERIFICATION = 'waiting_cs_verification';

    public const STATUS_ANOMALY = 'anomaly';

    public function __construct(private readonly EffectiveAccessService $access) {}

    /**
     * @return Collection<int, array{sale: InventorySerialSale, status: string, status_label: string, matched_legacy: bool}>
     */
    public function rows(User $user): Collection
    {
        $query = InventorySerialSale::query()
            ->with(['inventorySerial.item', 'customer.pop', 'fopTask.task.creqDetail', 'fopTask.task.maintenanceBillingDetail']);

        if (! $this->access->hasAllPopAccess($user)) {
            $allowedPopIds = $this->access->getAllowedPopIds($user);
            $query->whereHas('customer', fn ($q) => $q->whereIn('pop_id', $allowedPopIds));
        }

        // Dua sale untuk SN yang sama secara teori bisa terjadi (tukar modem
        // Beli dari Gudang dua kali) — hitung dulu per inventory_serial_id
        // SEBELUM query utama, dipakai guard fallback legacy di bawah (K:
        // fallback HANYA dipakai kalau pelanggan ini cuma punya SATU sale,
        // supaya tidak salah tebak sale mana yang sudah ditagih).
        $salesPerCustomer = InventorySerialSale::query()
            ->selectRaw('customer_id, COUNT(*) as total')
            ->groupBy('customer_id')
            ->pluck('total', 'customer_id');

        $billedInvoiceItemSaleIds = InvoiceItem::query()->whereNotNull('inventory_serial_sale_id')
            ->pluck('inventory_serial_sale_id')->unique()->flip();
        $billedTaskBillingSaleIds = TaskBillingItem::query()->whereNotNull('inventory_serial_sale_id')
            ->pluck('inventory_serial_sale_id')->unique()->flip();

        // Fallback data lama (sebelum kolom `invoice_items.inventory_serial_sale_id`
        // ada, §5.4) — sale tanpa penaut, pelanggannya punya TEPAT SATU sale,
        // dan invoice AWAL-nya sudah punya baris jual_modem (penaut apa pun).
        $legacyBilledCustomerIds = InvoiceItem::query()
            ->whereHas('subcategory', fn ($q) => $q->where('code', 'jual_modem'))
            ->whereHas('invoice', fn ($q) => $q->where('invoice_type', 'awal'))
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->pluck('invoices.customer_id')->unique()->flip();

        return $query->get()->map(function (InventorySerialSale $sale) use (
            $billedInvoiceItemSaleIds,
            $billedTaskBillingSaleIds,
            $legacyBilledCustomerIds,
            $salesPerCustomer,
        ) {
            $billedDirect = $billedInvoiceItemSaleIds->has($sale->id) || $billedTaskBillingSaleIds->has($sale->id);
            $matchedLegacy = ! $billedDirect
                && ($salesPerCustomer[$sale->customer_id] ?? 0) === 1
                && $legacyBilledCustomerIds->has($sale->customer_id);

            $status = ($billedDirect || $matchedLegacy)
                ? self::STATUS_BILLED
                : $this->resolvePendingStatus($sale);

            return [
                'sale' => $sale,
                'status' => $status,
                'status_label' => $this->statusLabel($status),
                'matched_legacy' => $matchedLegacy,
            ];
        })->reject(fn (array $row) => $row['status'] === self::STATUS_BILLED)->values();
    }

    /**
     * @return array{total: int, anomaly: int}
     */
    public function summary(Collection $rows): array
    {
        return [
            'total' => $rows->count(),
            'anomaly' => $rows->where('status', self::STATUS_ANOMALY)->count(),
        ];
    }

    private function resolvePendingStatus(InventorySerialSale $sale): string
    {
        $task = $sale->fopTask?->task;

        // Jalur C-REQ/MTN — sale selalu menempel ke Task yang punya detail
        // billing (creqDetail/maintenanceBillingDetail), sama anchor yang
        // dipakai TaskCreqBillingController. Verifikasi CS PENDING = wajar.
        if ($task && in_array($task->task_type, [TaskType::CREQ, TaskType::MAINTENANCE], true)) {
            $detail = $task->task_type === TaskType::CREQ ? $task->creqDetail : $task->maintenanceBillingDetail;

            if ($detail?->verification_status === CReqVerificationStatus::PENDING) {
                return self::STATUS_WAITING_CREQ_APPROVAL;
            }

            return self::STATUS_ANOMALY;
        }

        // Jalur Pemasangan — tidak ada detail billing C-REQ, statusnya
        // digantung ke status pelanggan saat ini.
        $customerStatus = $sale->customer?->status;

        if ($customerStatus === WorkflowTransition::WAITING_BUSINESS_DEVELOPMENT_VERIFICATION->value) {
            return self::STATUS_WAITING_BD;
        }

        if ($customerStatus === WorkflowTransition::VERIFICATION_ADMIN->value) {
            return self::STATUS_WAITING_CS_VERIFICATION;
        }

        return self::STATUS_ANOMALY;
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_WAITING_BD => 'Menunggu BD',
            self::STATUS_WAITING_CREQ_APPROVAL => 'Menunggu approve CS',
            self::STATUS_WAITING_CS_VERIFICATION => 'Menunggu verifikasi CS',
            self::STATUS_ANOMALY => 'Anomali',
            default => $status,
        };
    }
}
