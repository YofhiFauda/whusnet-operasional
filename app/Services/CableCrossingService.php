<?php

namespace App\Services;

use App\Enums\CableCrossingStatus;
use App\Models\Customer;
use App\Models\CustomerCableCrossing;
use App\Models\CustomerInstallation;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Rute kabel melintang jalan (ADHOC-205).
 *
 * Dua titik masuk, dua aturan:
 *  - Pemasangan: rute dicatat sebagai draft pointer (sama seperti roll kabel &
 *    SN di storePemasangan) — disinkronkan ulang setiap kali form disimpan,
 *    jadi isi form yang dikirim ulang tidak menggandakan rute.
 *  - Deact (task DEAC): setiap rute `terpasang` pelanggan WAJIB diberi hasil.
 *    Task DEAC tidak boleh ditutup selama masih ada rute `terpasang`.
 *
 * Penarikan kabel TIDAK mencatat mutasi stok (kabel yang sudah keluar/dibeli
 * tidak kembali ke gudang). Jangan tambahkan InventoryService di sini.
 */
class CableCrossingService
{
    /**
     * Aturan validasi form Pemasangan. Kalau checkbox "Kabel melintang jalan?"
     * tidak dicentang, rute diabaikan sepenuhnya — tidak ada aturan yang
     * berlaku, supaya baris kosong sisa form tidak memicu error.
     *
     * @return array<string, array<int, mixed>>
     */
    public function pemasanganRules(bool $crossingOn): array
    {
        if (! $crossingOn) {
            return ['cable_crossing' => ['nullable', 'boolean']];
        }

        return [
            'cable_crossing' => ['nullable', 'boolean'],
            'cable_crossings' => ['array', 'min:1'],
            'cable_crossings.*.id' => ['nullable', 'integer'],
            'cable_crossings.*.nama_jalan' => ['required', 'string', 'max:191'],
            'cable_crossings.*.jenis_jalan' => ['nullable', 'string', 'max:100'],
            'cable_crossings.*.meter_terpakai' => ['nullable', 'numeric', 'min:0.01'],
            'cable_crossings.*.foto' => ['nullable', 'image', 'max:4096'],
            'cable_crossings.*.points' => ['required', 'array', 'min:2'],
            'cable_crossings.*.points.*.label' => ['nullable', 'string', 'max:150'],
            'cable_crossings.*.points.*.latitude' => ['required', 'numeric', 'between:-90,90'],
            'cable_crossings.*.points.*.longitude' => ['required', 'numeric', 'between:-180,180'],
        ];
    }

    /**
     * Aturan validasi form Deact untuk setiap rute `terpasang` pelanggan. Dibangun
     * dari daftar rute yang masih terbuka, jadi rute yang belum diisi langsung
     * tertangkap di sini.
     *
     * @return array<string, array<int, mixed>>
     */
    public function deactRules(Customer $customer, Request $request): array
    {
        $rules = [];

        foreach ($this->pendingFor($customer) as $crossing) {
            $key = "cable_crossings.{$crossing->id}";
            $statusInput = "cable_crossings.{$crossing->id}.status";

            $rules["{$key}.status"] = ['required', Rule::in([CableCrossingStatus::DITARIK->value, CableCrossingStatus::TIDAK_BISA_DITARIK->value])];
            $rules["{$key}.foto"] = [
                Rule::requiredIf(fn () => $request->input($statusInput) === CableCrossingStatus::DITARIK->value),
                'image',
                'max:4096',
            ];
            $rules["{$key}.alasan"] = [
                Rule::requiredIf(fn () => $request->input($statusInput) === CableCrossingStatus::TIDAK_BISA_DITARIK->value),
                'string',
                'max:500',
            ];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cable_crossings.min' => 'Isi minimal satu rute kabel melintang jalan.',
            'cable_crossings.*.nama_jalan.required' => 'Nama jalan yang dilintasi wajib diisi.',
            'cable_crossings.*.foto.image' => 'Foto bukti harus berupa gambar.',
            'cable_crossings.*.foto.required' => 'Foto bukti penarikan wajib diunggah kalau kabel ditarik.',
            'cable_crossings.*.points.required' => 'Lokasi rute wajib diisi.',
            'cable_crossings.*.points.min' => 'Lokasi rute minimal 2 titik: titik awal dan rumah pelanggan.',
            'cable_crossings.*.points.*.latitude.required' => 'Latitude setiap titik wajib diisi.',
            'cable_crossings.*.points.*.latitude.between' => 'Latitude harus antara -90 dan 90.',
            'cable_crossings.*.points.*.longitude.required' => 'Longitude setiap titik wajib diisi.',
            'cable_crossings.*.points.*.longitude.between' => 'Longitude harus antara -180 dan 180.',
            'cable_crossings.*.status.required' => 'Pilih hasil penarikan untuk setiap rute kabel melintang.',
            'cable_crossings.*.alasan.required' => 'Alasan wajib diisi kalau kabel tidak bisa ditarik.',
        ];
    }

    /**
     * Foto bukti wajib untuk rute BARU. Rute yang sudah ada (ada `id` yang cocok
     * dengan pemasangan ini) boleh tanpa foto baru — foto lama dipertahankan.
     * Dipanggil sebelum transaksi dan sebelum upload, supaya gagal validasi tidak
     * meninggalkan file yatim.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function assertInstallationPhotos(CustomerInstallation $installation, array $rows): void
    {
        $existingIds = $this->terpasangForInstallation($installation)->pluck('id')->all();

        foreach ($rows as $row) {
            $isExisting = in_array((int) ($row['id'] ?? 0), $existingIds, true);

            if (! $isExisting && ! ($row['foto'] ?? null) instanceof UploadedFile) {
                throw ValidationException::withMessages([
                    'cable_crossings' => 'Foto bukti wajib diunggah untuk setiap rute kabel melintang baru.',
                ]);
            }
        }
    }

    /**
     * Sinkron rute dari form Pemasangan. Dipanggil di dalam transaksi
     * storePemasangan(). Rute yang tidak lagi ada di form dihapus (hanya yang
     * masih `terpasang` dari pemasangan ini).
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function syncFromPemasangan(CustomerInstallation $installation, Customer $customer, User $recordedBy, bool $crossingOn, array $rows, ?int $inventoryRollId): void
    {
        $existing = $this->terpasangForInstallation($installation)->keyBy('id');
        $keptIds = [];

        if ($crossingOn) {
            foreach ($rows as $row) {
                $crossing = $existing->get((int) ($row['id'] ?? 0))
                    ?? new CustomerCableCrossing([
                        'customer_id' => $customer->id,
                        'customer_installation_id' => $installation->id,
                        'dicatat_oleh' => $recordedBy->id,
                        'dicatat_at' => now(),
                    ]);

                $crossing->fill([
                    'inventory_roll_id' => $inventoryRollId,
                    'nama_jalan' => $row['nama_jalan'],
                    'jenis_jalan' => $row['jenis_jalan'] ?? null,
                    'meter_terpakai' => $row['meter_terpakai'] ?? null,
                    'status' => CableCrossingStatus::TERPASANG,
                ]);

                if (($row['foto'] ?? null) instanceof UploadedFile) {
                    $crossing->foto_bukti = FileUploadService::uploadCableCrossingPhoto($row['foto'], $customer, 'bukti');
                }

                $crossing->save();

                // Titik ikut ditulis ulang tiap sinkron: urutan di form adalah
                // sumber kebenaran, dan tidak ada data titik yang dicatat sendiri.
                $crossing->points()->delete();
                foreach (array_values($row['points']) as $index => $point) {
                    $crossing->points()->create([
                        'urutan' => $index + 1,
                        'label' => $point['label'] ?? null,
                        'latitude' => $point['latitude'],
                        'longitude' => $point['longitude'],
                    ]);
                }

                $keptIds[] = $crossing->id;
            }
        }

        $existing
            ->reject(fn (CustomerCableCrossing $crossing) => in_array($crossing->id, $keptIds, true))
            ->each(function (CustomerCableCrossing $crossing) {
                $crossing->points()->delete();
                $crossing->delete();
            });
    }

    /**
     * Bentuk baris form Pemasangan dari data tersimpan, sama persis dengan
     * struktur input `cable_crossings` supaya Alpine bisa memakai keduanya.
     *
     * @param  Collection<int, CustomerCableCrossing>  $crossings
     * @return array<int, array<string, mixed>>
     */
    public function formRows(Collection $crossings): array
    {
        return $crossings
            ->map(fn (CustomerCableCrossing $crossing) => [
                'id' => $crossing->id,
                'nama_jalan' => $crossing->nama_jalan,
                'jenis_jalan' => $crossing->jenis_jalan,
                'meter_terpakai' => $crossing->meter_terpakai,
                'points' => $crossing->points->map(fn ($point) => [
                    'label' => $point->label,
                    'latitude' => (string) $point->latitude,
                    'longitude' => (string) $point->longitude,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Jumlah rute yang belum selesai (`terpasang` + `tidak_bisa_ditarik`) —
     * badge Detail Pelanggan. Rute `tidak_bisa_ditarik` tetap dihitung karena
     * kabelnya masih ada di jalan.
     */
    public function countOpenFor(Customer $customer): int
    {
        return CustomerCableCrossing::query()
            ->where('customer_id', $customer->id)
            ->where('status', '!=', CableCrossingStatus::DITARIK->value)
            ->count();
    }

    /**
     * Rute `terpasang` pelanggan — dipakai form Deact, badge Detail Pelanggan,
     * dan penghalang penutupan task DEAC.
     *
     * @return Collection<int, CustomerCableCrossing>
     */
    public function pendingFor(Customer $customer): Collection
    {
        return CustomerCableCrossing::query()
            ->where('customer_id', $customer->id)
            ->where('status', CableCrossingStatus::TERPASANG->value)
            ->with('points')
            ->orderBy('id')
            ->get();
    }

    /**
     * Tutup semua rute `terpasang` pelanggan lewat task DEAC. Dipanggil di dalam
     * transaksi store() DEAC, sebelum task di-complete. Upload foto dilakukan
     * di sini, jadi validasi request sudah harus lolos (lihat deactRules()).
     *
     * @param  array<int|string, array<string, mixed>>  $rows
     */
    public function resolveForDeact(Customer $customer, Task $task, array $rows): void
    {
        $pending = $this->pendingFor($customer);

        // Invariant: jangan sampai task DEAC tertutup dengan rute yang belum
        // punya hasil. Form bisa saja sudah usang (rute baru muncul setelah
        // form dibuka), jadi dicek ulang di sini, bukan cuma di request.
        $missing = $pending->first(fn (CustomerCableCrossing $crossing) => ! isset($rows[$crossing->id]['status']));
        if ($missing !== null) {
            throw ValidationException::withMessages([
                'cable_crossings' => 'Ada rute kabel melintang yang belum diisi hasil penarikannya. Muat ulang halaman lalu isi semuanya.',
            ]);
        }

        DB::transaction(function () use ($pending, $customer, $task, $rows) {
            foreach ($pending as $crossing) {
                $row = $rows[$crossing->id];

                if ($row['status'] === CableCrossingStatus::DITARIK->value) {
                    $crossing->foto_penarikan = FileUploadService::uploadCableCrossingPhoto($row['foto'], $customer, 'penarikan');
                    $crossing->alasan_tidak_ditarik = null;
                    $crossing->status = CableCrossingStatus::DITARIK;
                } else {
                    $crossing->foto_penarikan = null;
                    $crossing->alasan_tidak_ditarik = $row['alasan'];
                    $crossing->status = CableCrossingStatus::TIDAK_BISA_DITARIK;
                }

                $crossing->deact_task_id = $task->id;
                $crossing->diselesaikan_at = now();
                $crossing->save();
            }
        });
    }

    /**
     * Rute `terpasang` yang berasal dari satu pemasangan (dipakai form Rute `terpasang` yang berasal dari satu pemasangan. sinkronisasi).
     *
     * @return Collection<int, CustomerCableCrossing>
     */
    public function terpasangForInstallation(CustomerInstallation $installation): Collection
    {
        return CustomerCableCrossing::query()
            ->where('customer_installation_id', $installation->id)
            ->where('status', CableCrossingStatus::TERPASANG->value)
            ->with('points')
            ->get();
    }
}
