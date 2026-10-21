<?php

namespace App\Http\Controllers;

use App\Enums\DeviceOwnership;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\CustomerAdditionalDevice;
use App\Models\InventorySerial;
use App\Models\Task;
use App\Models\User;
use App\Services\AdditionalDeviceCustodyService;
use App\Services\InventoryService;
use App\Services\TaskMaterialService;
use App\Support\SafeUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Langkah "Perangkat Tambahan" Laporan Pemasangan (ADHOC-190 task 4, spec
 * §4.2). Endpoint sendiri, terpisah dari storePemasangan(), karena langkah
 * ini MENULIS ledger stok seketika (installSerial) sementara step 5 cuma
 * menyimpan draft pointer yang bisa disubmit ulang berkali-kali.
 */
class CustomerInstallationAdditionalDeviceController extends Controller
{
    private const OUTCOME_LABELS = [
        'terpasang' => 'Terpasang',
        'kembali_gudang' => 'Kembali ke Gudang',
        'rusak' => 'Rusak',
        'hilang' => 'Hilang',
    ];

    public function store(Request $request, Customer $customer): RedirectResponse
    {
        // Permission SAMA dengan storePemasangan() — bukan
        // customers.detail.devices.update: role teknisi/pic_gudang (pengisi
        // laporan ini) memang tidak punya permission devices.update.
        abort_unless(auth()->user()->hasPermission('customers.detail.installation.update'), 403);

        abort_unless(
            in_array($customer->status, ['installation_in_progress', 'revision_installation'], true),
            403,
            'Data pemasangan pelanggan ini sudah melewati tahap pemasangan dan tidak dapat diubah oleh role Anda.'
        );

        $assignmentTask = Task::where('customer_id', $customer->id)
            ->where('task_type', TaskType::PEMASANGAN->value)
            ->whereIn('status', TaskStatus::reportableValues())
            ->latest('id')
            ->first();

        abort_unless(
            auth()->user()->hasFullAccess()
                || ($assignmentTask && $assignmentTask->teamMembers->pluck('user_id')->contains(auth()->id())),
            403,
            'Anda bukan anggota tim yang ditugaskan untuk pemasangan pelanggan ini.'
        );

        $eligibleSerials = app(AdditionalDeviceCustodyService::class)->eligibleSerialsForTask($assignmentTask)->keyBy('id');

        $validated = $request->validate([
            'decisions' => ['nullable', 'array'],
            'decisions.*.inventory_serial_id' => ['required', 'integer', 'distinct', Rule::in($eligibleSerials->keys())],
            // NULLABLE, bukan required (review fix 2): daftar custody berisi
            // SEMUA AP/STB yang dibawa tim, bisa termasuk barang untuk
            // pelanggan LAIN di hari yang sama. Kalau wajib diisi, teknisi
            // dipaksa mencatat status palsu ("kembali gudang") untuk barang
            // yang sebenarnya masih mau dipasang di lokasi berikutnya. Baris
            // kosong = belum ada keputusan → dibuang sebelum hitung agregat.
            'decisions.*.outcome' => ['nullable', Rule::in(array_keys(self::OUTCOME_LABELS))],
            'kekurangan_perangkat' => ['nullable', 'boolean'],
            'kekurangan_perangkat_alasan' => ['nullable', 'required_if:kekurangan_perangkat,1', 'string', 'max:500'],
        ], [
            'decisions.*.inventory_serial_id.in' => 'SN perangkat tambahan yang dipilih bukan bagian dari custody tim Anda saat ini.',
            'kekurangan_perangkat_alasan.required_if' => 'Alasan kekurangan perangkat wajib diisi.',
        ]);

        $decisions = collect($validated['decisions'] ?? [])
            ->filter(fn (array $decision) => filled($decision['outcome'] ?? null))
            ->values();
        $shortage = (bool) ($validated['kekurangan_perangkat'] ?? false);
        $required = (int) ($customer->customerService?->internetPackage?->max_additional_devices ?? 0);

        // Perangkat dari Gudang yang SUDAH terpasang di submit sebelumnya
        // ikut dihitung — SN-nya sudah INSTALLED jadi tidak muncul lagi di
        // daftar custody; tanpa ini submit ulang (mis. revisi) bakal selalu
        // dianggap kurang.
        $alreadyInstalled = $customer->additionalDevices()->installedFromWarehouse()->count();
        $requirements = $customer->customerService?->internetPackage?->additionalDeviceRequirements() ?? [];
        if ($requirements !== []) {
            $selectedByCategory = $decisions->countBy(
                fn (array $decision) => $eligibleSerials[(int) $decision['inventory_serial_id']]->item?->category?->code
            );
            $installedByCategory = $customer->additionalDevices()
                ->installedFromWarehouse()
                ->with('inventorySerial.item.category')
                ->get()
                ->countBy(fn (CustomerAdditionalDevice $device) => $device->inventorySerial?->item?->category?->code);

            foreach ($requirements as $categoryCode => $count) {
                $reported = ($selectedByCategory[$categoryCode] ?? 0) + ($installedByCategory[$categoryCode] ?? 0);
                if ($reported > $count || (! $shortage && $reported < $count)) {
                    throw ValidationException::withMessages([
                        'decisions' => 'Paket ini membutuhkan '.($requirements['access_point'] ?? 0).' AP dan '.($requirements['customer_router'] ?? 0).' router pelanggan sebagai perangkat tambahan.',
                    ]);
                }
            }
        }

        // Spec §4.2: keputusan wajib untuk SETIAP perangkat yang diatur paket
        // (apa pun outcome-nya), bukan cuma minimal satu. Jalur kekurangan +
        // alasan = pengecualian untuk kejadian di luar kendali teknisi.
        if ($decisions->count() + $alreadyInstalled < $required && ! $shortage) {
            throw ValidationException::withMessages([
                'decisions' => "Paket pelanggan ini mengatur {$required} perangkat tambahan — beri keputusan untuk setiap perangkat, atau centang \"Laporkan kekurangan\" dan isi alasannya.",
            ]);
        }

        // Batas paket tetap berlaku (pola sama CustomerAdditionalDeviceController::store());
        // lebih dari limit bikin ganti paket berikutnya keblok
        // (CustomerPackageService::assertAdditionalDevicesWithinLimit()).
        $toInstall = $decisions->where('outcome', 'terpasang');
        if ($toInstall->isNotEmpty() && $customer->additionalDevices()->count() + $toInstall->count() > $required) {
            throw ValidationException::withMessages([
                'decisions' => "Melebihi batas {$required} perangkat tambahan untuk paket pelanggan ini.",
            ]);
        }

        $installFopTask = app(TaskMaterialService::class)->resolveTaskFor($customer, TaskType::PEMASANGAN);
        if ($toInstall->isNotEmpty() && ! $installFopTask) {
            throw ValidationException::withMessages([
                'decisions' => 'Task FOP Pemasangan pelanggan ini tidak ditemukan — perangkat tidak bisa dicatat terpasang.',
            ]);
        }

        $teamTechnicians = User::whereIn('id', $assignmentTask?->teamMembers->pluck('user_id') ?? [])->get();

        try {
            DB::transaction(function () use ($customer, $decisions, $eligibleSerials, $installFopTask, $teamTechnicians, $shortage, $validated) {
                $notLines = [];

                foreach ($decisions as $decision) {
                    /** @var InventorySerial $serial */
                    $serial = $eligibleSerials[(int) $decision['inventory_serial_id']];

                    if ($decision['outcome'] !== 'terpasang') {
                        // TIDAK di-install: SN tetap ISSUED di custody teknisi —
                        // itu sendiri sinyal ke Gudang (retur/rusak/hilang
                        // diproses lewat alur Gudang existing, spec §5).
                        // Keputusannya cukup dicatat di catatan instalasi.
                        $notLines[] = "[Perangkat Tambahan] SN {$serial->serial_number} ({$serial->item?->category?->name}): ".self::OUTCOME_LABELS[$decision['outcome']];

                        continue;
                    }

                    // returnExistingSerial WAJIB false — true akan meretur ONT
                    // utama pelanggan (risiko utama ADHOC-190).
                    app(InventoryService::class)->installSerial(
                        $serial,
                        $customer,
                        $installFopTask,
                        $teamTechnicians,
                        auth()->user(),
                        returnExistingSerial: false,
                        ownership: DeviceOwnership::COMPANY,
                    );

                    CustomerAdditionalDevice::create([
                        'customer_id' => $customer->id,
                        'inventory_serial_id' => $serial->id,
                        'device_type' => $serial->item?->category?->name ?? 'Perangkat Tambahan',
                        'brand' => $serial->item?->brand,
                        'model' => $serial->item?->model,
                        'serial_number' => $serial->serial_number,
                        'status' => 'terpasang',
                    ]);
                }

                $installation = $customer->installations()->latest()->first();
                if ($installation) {
                    if ($notLines !== []) {
                        $installation->installation_note = trim(($installation->installation_note ? $installation->installation_note."\n" : '').implode("\n", $notLines));
                    }

                    $installation->extra_device_shortage = $shortage;
                    $installation->extra_device_shortage_reason = $shortage ? $validated['kekurangan_perangkat_alasan'] : null;
                    // Penanda gerbang storeSpeedtest() — cuma diset kalau
                    // cek kelengkapan di atas sudah lolos.
                    $installation->extra_devices_reported_at = now();
                    $installation->save();
                }
            });
        } catch (InvalidArgumentException $e) {
            // Guard installSerial() (status/custody/cabang dicek ulang di
            // bawah lock) — tampilkan sebagai error form, bukan 500.
            throw ValidationException::withMessages(['decisions' => $e->getMessage()]);
        }

        return redirect()
            ->route('customers.installation.report', [
                'customer' => $customer,
                'return_to' => SafeUrl::resolveReturnTo($request->input('return_to'), 'verifications.queue'),
            ])
            ->with('success', 'Laporan perangkat tambahan tersimpan.');
    }
}
