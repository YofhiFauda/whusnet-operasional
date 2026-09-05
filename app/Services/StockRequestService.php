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
     * Admin Pusat nandain request udah diurus — TIDAK memindahkan barang
     * apa pun. Transfer sungguhan (ledger) tetap dibikin TERPISAH lewat
     * `WarehouseTransferController` seperti biasa — method ini murni update
     * status tiket, dipanggil SETELAH (atau bareng workflow) admin bikin
     * Transfer-nya di layar lain.
     */
    public function fulfill(StockRequest $request, User $actor, ?string $notes = null): StockRequest
    {
        $this->assertPending($request);

        $request->update([
            'status' => StockRequestStatus::FULFILLED,
            'decided_by' => $actor->id,
            'decided_at' => now(),
            'decision_notes' => $notes,
        ]);

        return $request->fresh();
    }

    public function reject(StockRequest $request, string $reason, User $actor): StockRequest
    {
        $this->assertPending($request);

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
        $this->assertPending($request);

        $request->update([
            'status' => StockRequestStatus::CANCELLED,
            'decided_by' => $actor->id,
            'decided_at' => now(),
        ]);

        return $request->fresh();
    }

    private function assertPending(StockRequest $request): void
    {
        if (! $request->status->isOpen()) {
            throw new InvalidArgumentException("Permintaan {$request->reference_number} udah berstatus {$request->status->label()} — gak bisa diubah lagi.");
        }
    }

    /**
     * `REQ-YYYYMMDD-NNNNNN` — pola sama `InventoryReceiveService::generateReferenceNumber()`
     * dkk, reset counter per bulan.
     */
    private function generateReferenceNumber(): string
    {
        $yearMonth = date('Ym');
        $today = date('Ymd');

        $lastNum = StockRequest::where('reference_number', 'like', "REQ-{$yearMonth}%")
            ->pluck('reference_number')
            ->map(fn ($number) => (int) substr($number, strrpos($number, '-') + 1))
            ->max() ?? 0;

        return sprintf('REQ-%s-%06d', $today, $lastNum + 1);
    }
}
