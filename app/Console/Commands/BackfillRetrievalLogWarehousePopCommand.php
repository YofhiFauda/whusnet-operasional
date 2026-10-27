<?php

namespace App\Console\Commands;

use App\Enums\DeviceRetrievalSource;
use App\Models\AuditLog;
use App\Models\DeviceRetrievalLog;
use App\Models\Pop;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('warehouse:backfill-retrieval-log-pop {--apply : Tulis POP yang terverifikasi; default hanya pratinjau}')]
#[Description('Pratinjau atau isi warehouse_pop_id log CREQ_SWAP lama yang masih kosong')]
class BackfillRetrievalLogWarehousePopCommand extends Command
{
    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $allNull = DeviceRetrievalLog::whereNull('warehouse_pop_id')->count();
        $candidates = 0;
        $unresolved = 0;
        $written = 0;
        $preview = [];

        DeviceRetrievalLog::query()
            ->whereNull('warehouse_pop_id')
            ->where('source', DeviceRetrievalSource::CREQ_SWAP->value)
            ->with(['serial', 'task'])
            ->chunkById(100, function ($logs) use ($apply, &$candidates, &$unresolved, &$written, &$preview): void {
                foreach ($logs as $log) {
                    $popId = $this->resolveWarehousePopId($log);

                    if ($popId === null) {
                        $unresolved++;

                        continue;
                    }

                    $candidates++;
                    if (count($preview) < 20) {
                        $preview[] = [$log->id, $popId];
                    }

                    if (! $apply) {
                        continue;
                    }

                    DB::transaction(function () use ($log, &$written): void {
                        $fresh = DeviceRetrievalLog::query()->lockForUpdate()->findOrFail($log->id);

                        if ($fresh->warehouse_pop_id !== null) {
                            return;
                        }

                        $fresh->load(['serial', 'task']);
                        $popId = $this->resolveWarehousePopId($fresh);

                        if ($popId === null) {
                            return;
                        }

                        $fresh->update(['warehouse_pop_id' => $popId]);
                        AuditLog::create([
                            'module' => 'DeviceRetrievalLog',
                            'action' => 'backfill_warehouse_pop',
                            'auditable_type' => $fresh->getMorphClass(),
                            'auditable_id' => $fresh->id,
                            'old_values' => ['warehouse_pop_id' => null],
                            'new_values' => ['warehouse_pop_id' => $popId],
                            'created_at' => now(),
                        ]);
                        $written++;
                    });
                }
            });

        $this->info("Total log tanpa POP: {$allNull}; kandidat CREQ_SWAP: {$candidates}; belum jelas: {$unresolved}.");
        if ($preview !== []) {
            $this->table(['Log ID', 'POP gudang usulan'], $preview);
        }

        $this->info($apply
            ? "Baris diperbarui: {$written}."
            : 'Pratinjau saja; tidak ada data yang diubah. --apply memerlukan persetujuan operator.');

        return self::SUCCESS;
    }

    private function resolveWarehousePopId(DeviceRetrievalLog $log): ?int
    {
        $serialPopId = $log->serial?->issued_from_pop_id;
        $taskPopId = $log->task?->pop_id
            ? Pop::resolveCabangId($log->task->pop_id)
            : null;

        if ($serialPopId !== null && $taskPopId !== null && (int) $serialPopId !== (int) $taskPopId) {
            return null;
        }

        $popId = $serialPopId ?? $taskPopId;
        if ($popId === null || ! Pop::find($popId)?->isWarehouse()) {
            return null;
        }

        return (int) $popId;
    }
}
