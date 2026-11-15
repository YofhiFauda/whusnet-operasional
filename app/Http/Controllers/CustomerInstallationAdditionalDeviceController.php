<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\Task;
use App\Services\AdditionalDeviceCustodyService;
use App\Support\SafeUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Langkah "Perangkat Tambahan" Laporan Pemasangan (ADHOC-190 task 4, spec
 * §4.2). Endpoint sendiri, terpisah dari storePemasangan().
 *
 * Sejak 2026-11-14 (keputusan user) langkah ini cuma menyimpan DRAFT POINTER
 * (`customer_installations.extra_device_serial_ids`), pola sama Barang
 * Dipasang Step 5: teknisi bisa simpan ulang & ganti pilihan berkali-kali.
 * Dulu langsung installSerial() — SN keluar dari custody begitu disimpan, jadi
 * salah pilih gak bisa dibetulkan lagi. Pemasangan sungguhan dieksekusi di
 * CustomerInstallationController::storeSpeedtest() (titik selesai tunggal).
 */
class CustomerInstallationAdditionalDeviceController extends Controller
{
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

        $installation = $customer->installations()->latest()->first();
        if (! $installation) {
            throw ValidationException::withMessages([
                'decisions' => 'Simpan Laporan Pemasangan (Step 5) terlebih dahulu sebelum memilih perangkat tambahan.',
            ]);
        }

        $eligibleSerials = app(AdditionalDeviceCustodyService::class)
            ->eligibleSerialsForExtraDevice($assignmentTask, $installation->selected_inventory_serial_id)
            ->keyBy('id');

        $validated = $request->validate([
            'decisions' => ['nullable', 'array'],
            'decisions.*.inventory_serial_id' => ['required', 'integer', 'distinct', Rule::in($eligibleSerials->keys())],
            'kekurangan_perangkat' => ['nullable', 'boolean'],
            'kekurangan_perangkat_alasan' => ['nullable', 'required_if:kekurangan_perangkat,1', 'string', 'max:500'],
        ], [
            'decisions.*.inventory_serial_id.in' => 'SN perangkat tambahan yang dipilih bukan bagian dari custody tim Anda saat ini.',
            'kekurangan_perangkat_alasan.required_if' => 'Alasan kekurangan perangkat wajib diisi.',
        ]);

        // Dropdown Status DIHAPUS (keputusan user 2026-11-14): perangkat yang
        // dipilih di langkah ini = AKAN dipasang ke pelanggan (dieksekusi di
        // storeSpeedtest()), tidak ada pilihan lain. Barang yang tidak jadi
        // dipasang cukup tidak dipilih — tetap di custody teknisi
        // (retur/rusak/hilang diurus lewat alur Gudang). Dulu
        // ada opsi Kembali Gudang/Rusak/Hilang yang cuma ditulis ke catatan
        // instalasi tanpa efek stok, jadi membingungkan.
        $decisions = collect($validated['decisions'] ?? [])->values();
        $shortage = (bool) ($validated['kekurangan_perangkat'] ?? false);
        $required = (int) ($customer->customerService?->internetPackage?->max_additional_devices ?? 0);

        // Perangkat dari Gudang yang SUDAH terpasang di submit sebelumnya
        // ikut dihitung — SN-nya sudah INSTALLED jadi tidak muncul lagi di
        // daftar custody; tanpa ini submit ulang (mis. revisi) bakal selalu
        // dianggap kurang.
        // Jenis perangkat TIDAK dibatasi per kategori — teknisi bebas pilih
        // perangkat ber-SN apa pun dari custody tim (sejak 2026-11-14 bukan
        // cuma yang ditandai `is_customer_extra_device`), selama total yang dipenuhi pas dengan
        // `max_additional_devices` paket. Ketentuan jenis barang bisa beda
        // di kemudian hari; cuma jumlahnya yang tetap (lihat cek $required
        // di bawah).
        $alreadyInstalled = $customer->additionalDevices()->installedFromWarehouse()->count();

        // Spec §4.2: keputusan wajib untuk SETIAP perangkat yang diatur paket
        // (SN yang dipilih = dipasang), bukan cuma minimal satu. Jalur kekurangan +
        // alasan = pengecualian untuk kejadian di luar kendali teknisi.
        if ($decisions->count() + $alreadyInstalled < $required && ! $shortage) {
            throw ValidationException::withMessages([
                'decisions' => "Paket pelanggan ini mengatur {$required} perangkat tambahan — pilih perangkat yang dipasang, atau centang \"Laporkan kekurangan\" dan isi alasannya.",
            ]);
        }

        // Batas paket tetap berlaku (pola sama CustomerAdditionalDeviceController::store());
        // lebih dari limit bikin ganti paket berikutnya keblok
        // (CustomerPackageService::assertAdditionalDevicesWithinLimit()).
        $toInstall = $decisions;
        if ($toInstall->isNotEmpty() && $customer->additionalDevices()->active()->count() + $toInstall->count() > $required) {
            throw ValidationException::withMessages([
                'decisions' => "Melebihi batas {$required} perangkat tambahan untuk paket pelanggan ini.",
            ]);
        }

        // Simpan ulang = GANTI daftar pilihan (bukan menambah) — sama perilaku
        // selected_inventory_serial_id Step 5. Belum ada ledger stok yang
        // ditulis, jadi aman diubah berkali-kali sampai Laporan Speedtest.
        $installation->extra_device_serial_ids = $decisions->isEmpty()
            ? null
            : $decisions->map(fn ($decision) => (int) $decision['inventory_serial_id'])->values()->all();
        $installation->extra_device_shortage = $shortage;
        $installation->extra_device_shortage_reason = $shortage ? $validated['kekurangan_perangkat_alasan'] : null;
        // Penanda gerbang storeSpeedtest() — cuma diset kalau cek
        // kelengkapan di atas sudah lolos.
        $installation->extra_devices_reported_at = now();
        $installation->save();

        return redirect()
            ->route('customers.installation.report', [
                'customer' => $customer,
                'return_to' => SafeUrl::resolveReturnTo($request->input('return_to'), 'verifications.queue'),
            ])
            ->with('success', 'Pilihan perangkat tambahan tersimpan — masih bisa diganti, baru tercatat terpasang saat Laporan Speedtest.');
    }
}
