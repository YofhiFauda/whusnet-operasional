<?php

namespace App\Http\Controllers;

use App\Enums\DeviceAcquisitionSource;
use App\Enums\DeviceOwnership;
use App\Enums\DeviceRetrievalOutcome;
use App\Enums\OwnershipMode;
use App\Enums\SerialStatus;
use App\Enums\TaskType;
use App\Enums\TrackingType;
use App\Models\InventorySerial;
use App\Models\Item;
use App\Models\Task;
use App\Models\TaskDeviceRetrieval;
use App\Services\FileUploadService;
use App\Services\InventoryReassignService;
use App\Services\LegacyDeviceHintService;
use App\Services\TaskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Form laporan khusus task Ambil Modem (DEAC) — ADHOC-86. Sebelumnya DEAC
 * jatuh ke form Maintenance generik yang menuntut foto OPM & speedtest dan
 * tidak punya isian SN, sehingga tidak ada bukti alat apa yang dibawa pulang.
 * Rancangan: docs/plan/warehouse/analisa-ambil-modem-deac-ke-gudang.md.
 *
 * Satu request = simpan laporan + gerak inventori (SN → RETURNED) +
 * `TaskService::complete()`, dalam satu transaksi. Modem baru masuk stok
 * gudang setelah staf cabang menerima (WarehouseReturnReceiveController).
 */
class TaskDeviceRetrievalController extends Controller
{
    public function report(Task $task, LegacyDeviceHintService $hints): View|RedirectResponse
    {
        $this->authorize('statusComplete', $task);

        if ($redirect = $this->guardTask($task)) {
            return $redirect;
        }

        $task->loadMissing(['customer.customerTechnicalDetail', 'deviceRetrieval']);

        // Petunjuk SN buat teknisi: SN yang tercatat terpasang di pelanggan ini
        // (jalur normal) dan SN dari data lama pelanggan (modem legacy).
        // SN `ownership=customer` (sudah dijual — Beli dari Gudang/Beli Sendiri)
        // DIKECUALIKAN: itu bukan aset perusahaan lagi, tidak boleh ditagih balik.
        $installedSerials = InventorySerial::query()
            ->where('customer_id', $task->customer_id)
            ->where('status', SerialStatus::INSTALLED->value)
            ->where('ownership', DeviceOwnership::COMPANY->value)
            ->with('item')
            ->get();
        $legacyHint = $hints->forCustomer($task->customer);
        $legacySerial = (string) $legacyHint['serial'];

        // Banner "tidak perlu ditarik" — kondisinya lihat nothingToRetrieve().
        $showOwnershipSkipBanner = $this->nothingToRetrieve($task);

        $items = $this->serialItems();
        $accessoryOptions = TaskDeviceRetrieval::ACCESSORY_OPTIONS;
        $outcomes = DeviceRetrievalOutcome::cases();

        return view('tasks.device-retrieval-report', compact('task', 'installedSerials', 'legacySerial', 'legacyHint', 'items', 'accessoryOptions', 'outcomes', 'showOwnershipSkipBanner'));
    }

    public function store(Request $request, Task $task, TaskService $taskService, InventoryReassignService $inventory): RedirectResponse
    {
        $this->authorize('statusComplete', $task);

        if ($redirect = $this->guardTask($task)) {
            return $redirect;
        }

        // Baris SN repeatable: buang baris kosong (biasanya baris terakhir
        // yang tak diisi) SEBELUM validasi, supaya `min:1` menghitung SN
        // sungguhan, bukan baris kosong.
        $request->merge([
            'serials' => collect($request->input('serials', []))
                ->filter(fn ($row) => is_array($row) && trim((string) ($row['serial_number'] ?? '')) !== '')
                ->values()
                ->all(),
        ]);

        // Sama seperti report(): kalau tidak ada modem perusahaan yang bisa
        // ditarik, longgarkan guard `serials` khusus kondisi ini (lihat
        // nothingToRetrieve() untuk alasan lengkap kenapa campuran
        // company+customer TIDAK masuk sini).
        $allCustomerOwned = $this->nothingToRetrieve($task);

        $validated = $request->validate([
            'outcome' => ['required', Rule::enum(DeviceRetrievalOutcome::class)],
            'serials' => [
                Rule::requiredIf(fn () => $request->input('outcome') === DeviceRetrievalOutcome::DIAMBIL->value && ! $allCustomerOwned),
                'nullable', 'array',
            ],
            'serials.*.serial_number' => ['required', 'string', 'max:100'],
            'serials.*.item_id' => ['nullable', 'integer', 'exists:items,id'],
            'condition_photo' => ['required_if:outcome,'.DeviceRetrievalOutcome::DIAMBIL->value, 'nullable', 'image', 'max:2048'],
            'accessories' => ['nullable', 'array'],
            'accessories.*' => ['string', Rule::in(array_keys(TaskDeviceRetrieval::ACCESSORY_OPTIONS))],
            // Alasan wajib kalau alat TIDAK diambil — satu-satunya jejak kenapa
            // task DEAC selesai tanpa modem (FOP mereview dari sini).
            'notes' => [
                'nullable',
                'string',
                'max:1000',
                Rule::requiredIf(fn () => $request->input('outcome') !== DeviceRetrievalOutcome::DIAMBIL->value),
            ],
        ], [
            'serials.required' => 'Isi minimal satu nomor seri (SN) modem yang dibawa.',
            'condition_photo.required_if' => 'Foto kondisi alat wajib diunggah kalau alat berhasil diambil.',
            'notes.required' => 'Alasan wajib diisi kalau alat tidak berhasil diambil.',
        ]);

        $outcome = DeviceRetrievalOutcome::from($validated['outcome']);
        $rows = $outcome->isRetrieved() ? $validated['serials'] : [];

        $duplicate = collect($rows)->map(fn ($row) => mb_strtolower(trim($row['serial_number'])))->duplicates()->first();
        if ($duplicate !== null) {
            throw ValidationException::withMessages(['serials' => "SN {$duplicate} diisi lebih dari sekali."]);
        }

        // Semua SN pelanggan ini sudah milik pelanggan (bukan aset
        // perusahaan) → catatan auto, jangan andalkan input user yang
        // mungkin kosong atau tidak relevan di kondisi ini.
        if ($allCustomerOwned && $outcome->isRetrieved() && empty($rows)) {
            $validated['notes'] = 'Modem milik pelanggan, tidak perlu ditarik';
        }

        $technician = $request->user();
        $task->loadMissing(['customer', 'pop', 'fopTask']);

        try {
            DB::transaction(function () use ($request, $task, $taskService, $inventory, $technician, $outcome, $rows, $validated) {
                foreach ($rows as $row) {
                    $item = ! empty($row['item_id']) ? Item::find($row['item_id']) : null;

                    $inventory->pickupSerialFromCustomer($row['serial_number'], $item, $task->customer, $task, $technician);
                }

                // Foto diunggah SETELAH gerak inventori: kegagalan yang paling
                // mungkin (SN konflik / gudang tujuan tak ketemu) sudah lewat,
                // jadi tidak meninggalkan file yatim.
                $photoPath = $request->hasFile('condition_photo')
                    ? FileUploadService::uploadDeviceRetrievalPhoto($request->file('condition_photo'), $task->customer)
                    : $task->deviceRetrieval?->condition_photo;

                $task->deviceRetrieval()->updateOrCreate(['task_id' => $task->id], [
                    'outcome' => $outcome,
                    'condition_photo' => $photoPath,
                    'accessories' => $outcome->isRetrieved() ? ($validated['accessories'] ?? []) : [],
                    'notes' => $validated['notes'] ?? null,
                ]);

                $taskService->complete($task, $technician);
            });
        } catch (InvalidArgumentException $e) {
            // Pesan dari InventoryReassignService sudah berbahasa pengguna
            // (SN konflik, gudang tujuan tak ketemu) — tampilkan di field SN.
            throw ValidationException::withMessages(['serials' => $e->getMessage()]);
        }

        return redirect()->route('tasks.show', $task)->with('success', $outcome->isRetrieved()
            ? 'Laporan pengambilan alat tersimpan. Modem menunggu diterima Gudang cabang.'
            : 'Laporan tersimpan. Alat belum diambil — tombol Ambil Alat akan muncul lagi di List Putus Langganan.');
    }

    /**
     * Form ini khusus DEAC dan hanya untuk task yang sedang dikerjakan.
     */
    /**
     * "Tidak ada modem perusahaan yang perlu ditarik dari pelanggan ini" —
     * satu sumber untuk banner di report() dan pelonggaran guard di store().
     *
     * Dua kondisi (rancangan ownership modem §6):
     *  (a) Pelanggan punya SN terpasang tapi SEMUANYA `ownership=customer`.
     *      Kalau campuran (ada company + customer), SN company tetap wajib
     *      ditarik — TIDAK masuk sini.
     *  (b) Pelanggan tidak pernah punya baris `inventory_serials` sama sekali
     *      DAN modemnya tercatat `acquisition_source=beli_sendiri` — modem
     *      dibeli di luar gudang, memang tidak ada yang bisa ditarik. Tanpa
     *      cabang ini teknisi dipaksa lewat jalur "tidak berhasil diambil +
     *      alasan" padahal tidak ada yang gagal.
     */
    private function nothingToRetrieve(Task $task): bool
    {
        $customer = $task->customer;

        $hasCompanyInstalled = InventorySerial::query()
            ->where('customer_id', $task->customer_id)
            ->where('status', SerialStatus::INSTALLED->value)
            ->where('ownership', DeviceOwnership::COMPANY->value)
            ->exists();

        if ($hasCompanyInstalled) {
            return false;
        }

        if ($customer->inventorySerials()->where('status', SerialStatus::INSTALLED->value)->exists()) {
            return true;
        }

        return ! $customer->inventorySerials()->exists()
            && $customer->customerDevice?->acquisition_source === DeviceAcquisitionSource::BELI_SENDIRI;
    }

    private function guardTask(Task $task): ?RedirectResponse
    {
        if ($task->task_type !== TaskType::AMBIL_MODEM) {
            return redirect()->route('tasks.show', $task)->with('error', 'Form ini khusus task Ambil Modem (DEAC).');
        }

        if (! $task->status->acceptsReport()) {
            return redirect()->route('tasks.show', $task)->with('error', 'Status task tidak valid untuk pelaporan pengambilan alat.');
        }

        if (! $task->customer_id) {
            return redirect()->route('tasks.show', $task)->with('error', 'Task ini tidak terhubung ke pelanggan.');
        }

        return null;
    }

    /**
     * Pilihan model untuk SN yang belum pernah tercatat (modem legacy): barang
     * ber-SN yang bisa dipasang ke pelanggan.
     */
    private function serialItems()
    {
        return Item::active()
            ->where('tracking_type', TrackingType::SERIALIZED->value)
            ->where('ownership_mode', OwnershipMode::INSTALLABLE->value)
            ->orderBy('name')
            ->get();
    }
}
