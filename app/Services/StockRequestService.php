<?php

namespace App\Services;

use App\Enums\StockRequestStatus;
use App\Models\Pop;
use App\Models\StockRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Permintaan Stok Cabang→Pusat (2026-09-03) — lihat docblock migration
 * `create_stock_requests_table` buat konteks lengkap. SENGAJA tipis: ini
 * cuma tiket komunikasi, bukan Service pergerakan stok — gak ada method di
 * sini yang nyentuh `InventoryBalance`/`inventory_transactions` sama sekali.
 */
class StockRequestService
{
    /**
     * @param  list<array{item_id:int, qty_requested:float, lot_no?:?string}>  $lines
     */
    public function create(Pop $cabang, array $lines, User $actor, ?string $notes = null): StockRequest
    {
        if (! $cabang->isCabang()) {
            throw new InvalidArgumentException("Permintaan Stok cuma boleh diajukan ATAS NAMA Gudang Cabang — {$cabang->name} bertipe '{$cabang->type}'.");
        }

        if ($lines === []) {
            throw new InvalidArgumentException('Permintaan Stok wajib py minimal 1 baris barang.');
        }

        return DB::transaction(function () use ($cabang, $lines, $actor, $notes) {
            $request = StockRequest::create([
                'reference_number' => $this->generateReferenceNumber(),
                'cabang_pop_id' => $cabang->id,
                'status' => StockRequestStatus::PENDING,
                'notes' => $notes,
                'requested_by' => $actor->id,
            ]);

            foreach ($lines as $line) {
                $qty = (float) $line['qty_requested'];

                if ($qty <= 0) {
                    throw new InvalidArgumentException('Qty yang diminta harus lebih besar dari nol.');
                }

                $request->items()->create([
                    'item_id' => $line['item_id'],
                    'qty_requested' => $qty,
                    'lot_no' => $line['lot_no'] ?? null,
                ]);
            }

            return $request;
        });
    }

    /**
     * "Tandai Cukup / Selesai" — nutup tiket jadi FULFILLED LANGSUNG, TANPA
     * ngecek apakah `qty_fulfilled` tiap baris udah pas 100% atau belum
     * (dipakai kalau Pusat udah anggap cukup meski cuma terkirim sebagian,
     * atau kalau admin males catat detail per baris dan langsung tau
     * semuanya udah kekirim). TIDAK memindahkan barang apa pun — Transfer
     * sungguhan (ledger) tetap dibikin TERPISAH lewat
     * `WarehouseTransferController`.
     *
     * Beda dari `recordDelivery()`: itu APPEND qty per baris & status
     * otomatis (PARTIAL/FULFILLED tergantung sisa), method ini SELALU
     * langsung FULFILLED apa pun sisa qty-nya.
     */
    public function fulfill(StockRequest $request, User $actor, ?string $notes = null): StockRequest
    {
        $this->assertOpenForFulfillment($request);

        $request->update([
            'status' => StockRequestStatus::FULFILLED,
            'decided_by' => $actor->id,
            'decided_at' => now(),
            'decision_notes' => $notes,
        ]);

        return $request->fresh();
    }

    /**
     * "Catat Pengiriman" — nambah `qty_fulfilled` per baris item (dipanggil
     * SETELAH admin bikin Transfer sungguhan di layar lain, buat sinkronin
     * progress tiket ini). Status otomatis: semua baris pas 100% → FULFILLED,
     * ada yang kekirim tapi belum semua → PARTIAL. Ini SATU-SATUNYA method
     * yang bisa transisi PENDING→PARTIAL.
     *
     * @param  array<int, float>  $quantities  keyed by StockRequestItem->id
     */
    public function recordDelivery(StockRequest $request, array $quantities, User $actor, ?string $notes = null): StockRequest
    {
        $this->assertOpenForFulfillment($request);

        return DB::transaction(function () use ($request, $quantities, $actor, $notes) {
            $request->load('items');
            $anyDelivered = false;

            foreach ($request->items as $item) {
                $qty = (float) ($quantities[$item->id] ?? 0);

                if ($qty <= 0) {
                    continue;
                }

                $qty = min($qty, $item->remaining()); // clamp — gak boleh nyatet lebih dari sisa diminta
                $item->update(['qty_fulfilled' => (float) $item->qty_fulfilled + $qty]);
                $anyDelivered = true;
            }

            if (! $anyDelivered) {
                throw new InvalidArgumentException('Isi minimal 1 qty pengiriman lebih dari nol.');
            }

            $request->refresh()->load('items');
            $allFulfilled = $request->items->every(fn ($item) => $item->isFullyFulfilled());

            $request->update([
                'status' => $allFulfilled ? StockRequestStatus::FULFILLED : StockRequestStatus::PARTIAL,
                'decided_by' => $actor->id,
                'decided_at' => now(),
                'decision_notes' => $notes,
            ]);

            return $request->fresh();
        });
    }

    public function reject(StockRequest $request, string $reason, User $actor): StockRequest
    {
        $this->assertCanRejectOrCancel($request);

        if (trim($reason) === '') {
            throw new InvalidArgumentException('Alasan penolakan wajib diisi.');
        }

        $request->update([
            'status' => StockRequestStatus::REJECTED,
            'decided_by' => $actor->id,
            'decided_at' => now(),
            'decision_notes' => $reason,
        ]);

        return $request->fresh();
    }

    /**
     * Pengaju sendiri batalin request-nya (mis. salah ketik / gak jadi
     * butuh). Beda dari `reject()` — itu keputusan Pusat, ini keputusan
     * pengaju sendiri, otorisasi "punya sendiri" dicek di Controller.
     */
    public function cancel(StockRequest $request, User $actor): StockRequest
    {
        $this->assertCanRejectOrCancel($request);

        $request->update([
            'status' => StockRequestStatus::CANCELLED,
            'decided_by' => $actor->id,
            'decided_at' => now(),
        ]);

        return $request->fresh();
    }

    /**
     * Guard buat `fulfill()`/`recordDelivery()` — PENDING & PARTIAL dua-
     * duanya masih boleh diproses lanjut.
     */
    private function assertOpenForFulfillment(StockRequest $request): void
    {
        if (! $request->status->isOpen()) {
            throw new InvalidArgumentException("Permintaan {$request->reference_number} udah berstatus {$request->status->label()} — gak bisa diubah lagi.");
        }
    }

    /**
     * Guard buat `reject()`/`cancel()` — CUMA PENDING murni, begitu udah
     * PARTIAL (barang mulai bergerak) dua aksi ini gak masuk akal lagi.
     */
    private function assertCanRejectOrCancel(StockRequest $request): void
    {
        if (! $request->status->canRejectOrCancel()) {
            throw new InvalidArgumentException("Permintaan {$request->reference_number} udah berstatus {$request->status->label()} — gak bisa ditolak/dibatalkan lagi.");
        }
    }

    /**
     * `PST-YYYYMMDD-NNNNNN` — prefix Permintaan Stok, sengaja bukan `REQ`
     * biar gak ambigu sama istilah generik "request" (request ID/log HTTP,
     * dll — rancangan-layout.md §5.1). Pola nomor sama
     * `InventoryReceiveService::generateReferenceNumber()` dkk, reset
     * counter per bulan.
     */
    private function generateReferenceNumber(): string
    {
        $yearMonth = date('Ym');
        $today = date('Ymd');

        $lastNum = StockRequest::where('reference_number', 'like', "PST-{$yearMonth}%")
            ->pluck('reference_number')
            ->map(fn ($number) => (int) substr($number, strrpos($number, '-') + 1))
            ->max() ?? 0;

        return sprintf('PST-%s-%06d', $today, $lastNum + 1);
    }
}
