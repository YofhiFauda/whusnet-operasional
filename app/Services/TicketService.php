<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Enums\TicketHistoryAction;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\FopTask;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * TicketService — pembuatan tiket internal perusahaan + auto-sync ke FopTask.
 *
 * Alur: user non-FOP (helpdesk/NOC/sales/admin) submit tiket → tiket tersimpan
 * sebagai catatan pengirim → satu FopTask ikut dibuat dengan status Draft
 * (BELUM ada teknisi & belum ada Task eksekusi). FOP yang nanti assign teknisi
 * lewat /fop-tasks; saat itulah Task eksekusi dibuat oleh FopTaskController.
 */
class TicketService
{
    /**
     * @param  array  $data  Data tervalidasi dari TicketController::store()
     * @param  UploadedFile[]  $attachments
     */
    public function create(array $data, User $actor, array $attachments = []): Ticket
    {
        return DB::transaction(function () use ($data, $actor, $attachments) {
            /** @var Customer $customer */
            $customer = Customer::query()
                ->applyUserScope($actor)
                ->with(['internetPackage', 'customerDevice'])
                ->whereKey($data['customer_id'])
                ->firstOrFail();

            // tickets.pop_id & fop_tasks butuh POP buat scoping — pelanggan tanpa
            // POP bakal bikin insert gagal dengan error FK yang gak kebaca user.
            if (!$customer->pop_id) {
                throw ValidationException::withMessages([
                    'customer_id' => 'Pelanggan ini belum punya POP/Cabang — lengkapi dulu data pelanggannya sebelum buat tiket.',
                ]);
            }

            $type = TaskType::from($data['type']);

            $ticket = new Ticket();
            $ticket->ticket_number = $this->generateTicketNumber();
            $ticket->type = $type;
            $ticket->customer_id = $customer->id;
            $ticket->pop_id = $customer->pop_id;
            $this->snapshotCustomer($ticket, $customer);
            $ticket->detail_keluhan = $data['detail_keluhan'];
            $ticket->catatan_teknis = $data['catatan_teknis'] ?? null;
            $ticket->priority = $data['priority'];
            $ticket->created_by = $actor->id;
            $ticket->save();

            $fopTask = $this->syncToFopTask($ticket, $customer, $actor);

            $ticket->fop_task_id = $fopTask->id;
            $ticket->save();

            foreach ($attachments as $file) {
                $this->storeAttachment($ticket, $file, $actor);
            }

            $ticket->histories()->create([
                'action'      => TicketHistoryAction::DIBUAT,
                'to_status'   => $fopTask->status->value,
                'actor_id'    => $actor->id,
                'happened_at' => now(),
            ]);

            if (class_exists(AuditLog::class)) {
                AuditLog::log($ticket, 'create', null, $ticket->fresh()->toArray());
            }

            return $ticket->load(['customer', 'creator', 'fopTask', 'attachments']);
        });
    }

    /**
     * Bekukan data pelanggan ke kolom ticket saat itu juga (lihat rationale
     * di migration 2026_07_24_000001). ODP: prioritaskan denormalisasi di
     * customers.odp_code, fallback ke customer_devices.odp kalau kosong —
     * sama persis urutan yang dipakai TicketController::customerPayload()
     * buat panel live-preview form (sengaja diduplikasi, bukan dipanggil
     * silang controller↔service).
     */
    private function snapshotCustomer(Ticket $ticket, Customer $customer): void
    {
        $device = $customer->customerDevice;

        $ticket->customer_name = $customer->full_name;
        $ticket->customer_address = $customer->address;
        $ticket->customer_phone = $customer->primary_phone ?: $customer->phone;
        $ticket->customer_odp = $customer->odp_code ?: $device?->odp;
        $ticket->customer_package = $customer->internetPackage?->name;
        $ticket->customer_device = $this->deviceSummary($device);
        $ticket->customer_latitude = $customer->latitude;
        $ticket->customer_longitude = $customer->longitude;
    }

    /**
     * Cuma field non-sensitif — SN/MAC/PPPoE sengaja gak ikut, itu dikunci
     * permission customers.detail.devices.view_sensitive di modul Pelanggan.
     */
    private function deviceSummary(?\App\Models\CustomerDevice $device): ?string
    {
        if (!$device) {
            return null;
        }

        $parts = array_filter([$device->brand, $device->model, $device->device_type]);

        return $parts ? implode(' ', $parts) : null;
    }

    /**
     * Bikin FopTask kembar dari tiket. Sengaja gak lewat FopTaskController::store()
     * — controller itu mewajibkan minimal 1 teknisi dan langsung bikin Task
     * eksekusi, sedangkan tiket dari perusahaan masuk sebagai antrean mentah
     * yang penugasannya jadi keputusan FOP.
     */
    private function syncToFopTask(Ticket $ticket, Customer $customer, User $actor): FopTask
    {
        $fopTask = new FopTask();
        $fopTask->task_number = $this->generateFopTaskNumber();
        $fopTask->task_date = now();
        $fopTask->category = $ticket->type;
        $fopTask->tugas = $ticket->type->label() . ': ' . $customer->full_name;
        $fopTask->village_id = $customer->village_id;
        $fopTask->pop_id = $customer->pop_id;
        $fopTask->customer_id = $customer->id;
        $fopTask->issue = mb_substr($ticket->detail_keluhan, 0, 255);
        $fopTask->notes = $this->composeFopNotes($ticket, $actor);
        $fopTask->status = TaskStatus::DRAFT;
        $fopTask->priority = $ticket->priority;
        $fopTask->save();

        if (class_exists(AuditLog::class)) {
            AuditLog::log($fopTask, 'create', null, $fopTask->fresh()->toArray());
        }

        return $fopTask;
    }

    /**
     * Jejak asal-usul di FopTask — biar FOP yang cuma lihat /fop-tasks tetap
     * tahu tiket ini datang dari siapa tanpa harus buka modul Ticketing.
     */
    private function composeFopNotes(Ticket $ticket, User $actor): string
    {
        $lines = [
            "[Ticket {$ticket->ticket_number} — dikirim oleh {$actor->name}]",
        ];

        if (filled($ticket->catatan_teknis)) {
            $lines[] = '';
            $lines[] = 'Catatan Teknis:';
            $lines[] = $ticket->catatan_teknis;
        }

        return implode("\n", $lines);
    }

    private function storeAttachment(Ticket $ticket, UploadedFile $file, User $actor): void
    {
        // Disk 'local' (privat), bukan 'public' — lampiran bisa memuat data
        // pelanggan, jadi aksesnya harus lewat TicketController::download()
        // yang ngecek permission + scope POP, bukan URL tebakan.
        $path = $file->store("tickets/{$ticket->id}", 'local');

        $ticket->attachments()->create([
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $actor->id,
        ]);
    }

    private function generateTicketNumber(): string
    {
        $year = date('Y');
        $lastNum = Ticket::where('ticket_number', 'like', "TKT-{$year}-%")
            ->pluck('ticket_number')
            ->map(fn ($number) => (int) substr($number, strrpos($number, '-') + 1))
            ->max() ?? 0;

        return sprintf('TKT-%s-%04d', $year, $lastNum + 1);
    }

    /**
     * Format nomor harus identik sama FopTaskController::generateTaskNumber()
     * — dua-duanya nulis ke deret yang sama (TFOP-{tahun}-{urut}).
     */
    private function generateFopTaskNumber(): string
    {
        $year = date('Y');
        $lastNum = FopTask::where('task_number', 'like', "TFOP-{$year}-%")
            ->pluck('task_number')
            ->map(fn ($taskNumber) => (int) substr($taskNumber, strrpos($taskNumber, '-') + 1))
            ->max() ?? 0;

        return sprintf('TFOP-%s-%04d', $year, $lastNum + 1);
    }
}
