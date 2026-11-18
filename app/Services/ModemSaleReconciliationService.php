<?php

namespace App\Services;

use App\Enums\CReqVerificationStatus;
use App\Enums\TaskType;
use App\Enums\WorkflowTransition;
use App\Models\InventorySerialSale;
use App\Models\InvoiceItem;
use App\Models\TaskBillingItem;
use App\Models\User;
use App\Support\LikeSearch;
use Carbon\Carbon;
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
     * Status yang bisa dipilih di filter — `billed` tidak termasuk karena
     * baris tertagih memang tidak pernah tampil di laporan ini.
     *
     * @return array<string, string>
     */
    public function statusOptions(): array
    {
        return collect([
            self::STATUS_ANOMALY,
            self::STATUS_WAITING_BD,
            self::STATUS_WAITING_CS_VERIFICATION,
            self::STATUS_WAITING_CREQ_APPROVAL,
        ])->mapWithKeys(fn (string $status) => [$status => $this->statusLabel($status)])->all();
    }

    /**
     * `$filters` (semua opsional): search (SN / nama / CID pelanggan),
     * pop_id, status, date_from & date_to (Y-m-d, tanggal jual). Filter
     * Cabang tetap dipotong POP scope di bawah — memilih Cabang luar scope
     * lewat URL hasilnya kosong, bukan bocor. Status dihitung per baris
     * (bukan kolom DB), jadi filternya diterapkan setelah map.
     *
     * @param  array{search?: string, pop_id?: int|string, status?: string, date_from?: string, date_to?: string}  $filters
     * @return Collection<int, array{sale: InventorySerialSale, status: string, status_label: string, matched_legacy: bool}>
     */
    public function rows(User $user, array $filters = []): Collection
    {
        $query = InventorySerialSale::query()
            ->with(['inventorySerial.item', 'customer.pop', 'fopTask.task.creqDetail', 'fopTask.task.maintenanceBillingDetail'])
            ->latest('created_at')
            ->latest('id');

        if (! $this->access->hasAllPopAccess($user)) {
            $allowedPopIds = $this->access->getAllowedPopIds($user);
            $query->whereHas('customer', fn ($q) => $q->whereIn('pop_id', $allowedPopIds));
        }

        $search = LikeSearch::sanitize((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->whereHas('inventorySerial', fn ($s) => $s->where('serial_number', 'like', "%{$search}%"))
                    ->orWhereHas('customer', fn ($c) => $c->where('full_name', 'like', "%{$search}%")
                        ->orWhere('cid', 'like', "%{$search}%")
                        ->orWhere('customer_code', 'like', "%{$search}%"));
            });
        }

        if (($filters['pop_id'] ?? '') !== '') {
            $query->whereHas('customer', fn ($q) => $q->where('pop_id', (int) $filters['pop_id']));
        }

        // Batas eksplisit startOfDay/endOfDay, bukan whereDate() yang
        // membungkus kolom dan mematikan index (pola InvoiceReportController).
        if ($dateFrom = $this->parseDate($filters['date_from'] ?? null)) {
            $query->where('created_at', '>=', $dateFrom->startOfDay());
        }
        if ($dateTo = $this->parseDate($filters['date_to'] ?? null)) {
            $query->where('created_at', '<=', $dateTo->endOfDay());
        }

        $statusFilter = array_key_exists($filters['status'] ?? '', $this->statusOptions()) ? $filters['status'] : null;

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

            [$status, $reason] = ($billedDirect || $matchedLegacy)
                ? [self::STATUS_BILLED, null]
                : $this->resolvePendingStatus($sale);

            return [
                'sale' => $sale,
                'status' => $status,
                'status_label' => $this->statusLabel($status),
                'reason' => $reason,
                'matched_legacy' => $matchedLegacy,
            ];
        })->reject(fn (array $row) => $row['status'] === self::STATUS_BILLED)
            ->when($statusFilter, fn (Collection $rows) => $rows->where('status', $statusFilter))
            ->values();
    }

    /**
     * @return array{total: int, anomaly: int, waiting: int, unbilled_amount: float}
     */
    public function summary(Collection $rows): array
    {
        $anomaly = $rows->where('status', self::STATUS_ANOMALY)->count();

        return [
            'total' => $rows->count(),
            'anomaly' => $anomaly,
            'waiting' => $rows->count() - $anomaly,
            'unbilled_amount' => (float) $rows->sum(fn (array $row) => (float) $row['sale']->price),
        ];
    }

    private function parseDate(?string $value): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        return Carbon::createFromFormat('Y-m-d', $value) ?: null;
    }

    /**
     * Status baris yang belum tertagih + keterangan singkat penyebabnya.
     * Keterangan HANYA diisi untuk Anomali — itu yang perlu ditindaklanjuti,
     * dan tiap cabang di bawah menunjuk ke hal berbeda yang harus dicek
     * admin (tagihan pelanggan vs keputusan CS di task). Baris "Menunggu …"
     * sudah cukup dijelaskan oleh label statusnya.
     *
     * @return array{0: string, 1: string|null}
     */
    private function resolvePendingStatus(InventorySerialSale $sale): array
    {
        $task = $sale->fopTask?->task;

        // Jalur C-REQ/MTN — sale selalu menempel ke Task yang punya detail
        // billing (creqDetail/maintenanceBillingDetail), sama anchor yang
        // dipakai TaskCreqBillingController. Verifikasi CS PENDING = wajar.
        if ($task && in_array($task->task_type, [TaskType::CREQ, TaskType::MAINTENANCE], true)) {
            $detail = $task->task_type === TaskType::CREQ ? $task->creqDetail : $task->maintenanceBillingDetail;
            $type = $task->task_type->value;

            if (! $detail) {
                return [self::STATUS_ANOMALY, "{$type} tidak punya data penagihan, padahal modem dijual."];
            }

            // Tidak berbayar dicek SEBELUM pending: `verification_status`
            // default-nya 'pending' dan cuma bermakna kalau is_billable=true.
            // Antrean approve CS (TaskCreqBillingController) hanya memuat task
            // berbayar — task tak berbayar tidak akan pernah diproses CS, jadi
            // dulu tertahan "Menunggu approve CS" selamanya padahal anomali.
            if (! $detail->is_billable) {
                return [self::STATUS_ANOMALY, "{$type} tidak ditandai berbayar, padahal modem dijual."];
            }

            if ($detail->verification_status === CReqVerificationStatus::PENDING) {
                return [self::STATUS_WAITING_CREQ_APPROVAL, null];
            }

            return [self::STATUS_ANOMALY, $detail->verification_status === CReqVerificationStatus::REJECTED
                ? "{$type} ditolak CS, padahal modem sudah terpasang."
                : "{$type} sudah disetujui CS, tapi modem tidak masuk tagihan."];
        }

        // Jalur Pemasangan — tidak ada detail billing C-REQ, statusnya
        // digantung ke status pelanggan saat ini.
        $customerStatus = $sale->customer?->status;

        if ($customerStatus === WorkflowTransition::WAITING_BUSINESS_DEVELOPMENT_VERIFICATION->value) {
            return [self::STATUS_WAITING_BD, null];
        }

        if ($customerStatus === WorkflowTransition::VERIFICATION_ADMIN->value) {
            return [self::STATUS_WAITING_CS_VERIFICATION, null];
        }

        return [self::STATUS_ANOMALY, 'Pelanggan sudah '.$this->customerStatusLabel($customerStatus).', tapi modem tidak ada di tagihan.'];
    }

    /**
     * Label status pelanggan untuk keterangan anomali. Belum ada label
     * terpusat di WorkflowTransition — status di luar peta ini jatuh ke
     * teks mentah yang dirapikan, bukan kosong.
     */
    private function customerStatusLabel(?string $status): string
    {
        return match ($status) {
            WorkflowTransition::ACTIVE->value => 'Aktif',
            WorkflowTransition::SUSPENDED->value => 'Isolir',
            WorkflowTransition::TERMINATED->value => 'Putus Langganan',
            WorkflowTransition::INSTALLED->value => 'Terpasang',
            WorkflowTransition::REJECTED->value => 'Gagal/Ditolak',
            null => 'tanpa status',
            default => 'berstatus "'.str_replace('_', ' ', $status).'"',
        };
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
