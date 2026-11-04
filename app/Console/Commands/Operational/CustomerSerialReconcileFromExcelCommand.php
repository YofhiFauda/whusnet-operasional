<?php

namespace App\Console\Commands\Operational;

use App\Enums\SerialStatus;
use App\Models\Customer;
use App\Models\CustomerTechnicalDetail;
use App\Models\InventorySerial;
use App\Models\Pop;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\SimpleExcel\SimpleExcelReader;

class CustomerSerialReconcileFromExcelCommand extends Command
{
    /**
     * Generalisasi dari koreksi manual Serial Number cabang Sambit
     * (2026-11-04, file "SN-SAMBIT-26 - rekap kegiatan.xlsx") — dipakai cabang
     * mana pun yang punya rekap Excel kolom {no, Kegiatan, Nama/ODP, Alamat,
     * Tgl, SN BARU, Jenis Modem, SN LAMA, Jenis Modem, CID, ...}. Kolom CID
     * dipakai sebagai kunci cocok (bukan Nama/ODP — pada baris "ganti modem"
     * kolom itu kadang isinya CID lagi, bukan nama orang), dengan 6 digit
     * BERURUTAN PERTAMA yang ditemukan di kolom CID (bukan di akhir — CID
     * rekap manual diakhiri `_DESA_NAMA`, bukan digit) sebagai pengenal stabil
     * karena prefix CID di rekap manual sering typo/beda format dengan CID
     * asli di sistem (temuan Sambit: "G1X1BRQ000006_BANCANGAN_..." di Excel
     * vs "G00RQ000006" di DB — 6 digit angka urutnya tetap sama, prefix-nya
     * saja yang menyimpang).
     *
     * Baris TIDAK dibedakan "psb" vs "ganti modem" — per pelanggan (dikelompokkan
     * oleh 6-digit CID), baris dengan Tgl TERBARU menang, SN BARU baris itu
     * jadi nilai final. Ini sengaja: baris psb juga Sumber SN yang valid kalau
     * tidak ada "ganti modem" sesudahnya, dan kejadian ganti modem berkali-kali
     * (rantai) harus diambil ujung rantai, bukan baris pertama yang cocok.
     *
     * SN yang sumbernya Inventori Gudang (InventorySerial status=installed)
     * TIDAK PERNAH ditimpa di sini — itu dikelola lewat alur Gudang
     * (Reassign/Transfer/dll, lihat CustomerDeviceProfileService), menimpa
     * langsung di sini akan membuat data Gudang & Detail Pelanggan menyimpang.
     * Baris begini dilaporkan sebagai skip, bukan diproses.
     *
     * Target tulis ikut urutan cadangan SAMA dengan CustomerDeviceProfileService
     * (TANPA Inventori Gudang, lihat atas): customer_devices.serial_number kalau
     * baris itu sudah ada, else customer_technical_details.router_or_ont_serial
     * kalau sudah ada, else fallback customers.ont_sn. Command ini TIDAK membuat
     * baris customer_devices baru — itu keputusan form Edit Pelanggan/instalasi,
     * bukan backfill data rekap lapangan.
     */
    protected $signature = 'app:customer-serial-reconcile-excel
                        {file : Path Excel rekap kegiatan SN (relatif ke base_path, atau absolut)}
                        {--pop-id= : ID Pop tujuan. Wajib kalau --pop-name tidak diisi}
                        {--pop-name= : Nama Pop tujuan, alternatif --pop-id}
                        {--config= : Path JSON {skip_cids: [...], manual_overrides: {cid: serial}} khusus cabang ini (opsional)}
                        {--apply : Tulis perubahan ke database. Tanpa flag ini cuma dry-run (tampilkan diff)}';

    // php artisan app:customer-serial-reconcile-excel "docs/database/excel/serial-number/SN-SAMBIT-26 - rekap kegiatan.xlsx" --pop-name=Sambit --apply
    protected $description = 'Selaraskan Serial Number pelanggan satu Pop sesuai rekap Excel kegiatan lapangan (dry-run default)';

    public function handle(): int
    {
        $pop = $this->resolvePop();
        if (! $pop) {
            $this->error('Pop tidak ditemukan. Isi --pop-id atau --pop-name.');

            return self::FAILURE;
        }

        $filePath = $this->argument('file');
        $fullPath = str_starts_with($filePath, '/') ? $filePath : base_path($filePath);

        if (! file_exists($fullPath)) {
            $this->error("File Excel tidak ditemukan: {$fullPath}");

            return self::FAILURE;
        }

        [$skipCids, $manualOverrides] = $this->loadConfig();
        if ($skipCids === null) {
            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $this->info($apply ? 'MODE APPLY — perubahan akan ditulis ke database.' : 'MODE DRY-RUN — tidak ada perubahan ditulis. Jalankan dengan --apply untuk eksekusi.');

        // Kelompokkan baris per 6-digit CID, ambil baris Tgl TERBARU per grup.
        $latestByDigits = [];
        foreach (SimpleExcelReader::create($fullPath)->getRows() as $row) {
            $cidRaw = trim((string) ($row['CID'] ?? ''));
            $snBaru = trim((string) ($row['SN BARU'] ?? ''));

            if ($snBaru === '' || ! preg_match('/(\d{6})/', $cidRaw, $m)) {
                continue;
            }

            $digits = $m[1];
            $tgl = $row['Tgl'] ?? null;
            $tgl = $tgl instanceof \DateTimeInterface ? Carbon::instance($tgl) : Carbon::parse((string) $tgl);

            if (! isset($latestByDigits[$digits]) || $tgl->greaterThanOrEqualTo($latestByDigits[$digits]['tgl'])) {
                $latestByDigits[$digits] = ['tgl' => $tgl, 'serial' => $snBaru, 'cid' => $cidRaw];
            }
        }

        $changed = [];
        $unchanged = 0;
        $skipped = [];

        foreach ($latestByDigits as $digits => $entry) {
            if (in_array($digits, $skipCids, true) || in_array($entry['cid'], $skipCids, true)) {
                $skipped[] = [$entry['cid'], 'ditandai skip di config'];

                continue;
            }

            $candidates = Customer::query()
                ->where('pop_id', $pop->id)
                ->where('cid', 'like', '%'.$digits)
                ->get();

            if ($candidates->count() !== 1) {
                $skipped[] = [$entry['cid'], $candidates->count() === 0
                    ? 'tidak ada pelanggan di Pop ini dengan 6 digit CID itu'
                    : "ada {$candidates->count()} pelanggan dengan 6 digit CID sama di Pop ini — perlu dicek manual"];

                continue;
            }

            $result = $this->applyOrDryRun($candidates->first(), $entry['serial'], $apply);

            match ($result['status']) {
                'skip' => $skipped[] = [$entry['cid'], $result['reason']],
                'unchanged' => $unchanged++,
                'changed' => $changed[] = [$candidates->first()->id, $candidates->first()->full_name, $result['old'], $result['new'], 'Excel'],
            };
        }

        foreach ($manualOverrides as $cid => $serial) {
            $customer = Customer::query()->where('pop_id', $pop->id)->where('cid', $cid)->first();

            if (! $customer) {
                $skipped[] = ["[manual_override] {$cid}", 'CID ini tidak ditemukan di Pop yang dipilih'];

                continue;
            }

            $result = $this->applyOrDryRun($customer, (string) $serial, $apply);

            match ($result['status']) {
                'skip' => $skipped[] = [$customer->full_name." ({$cid})", $result['reason']],
                'unchanged' => $unchanged++,
                'changed' => $changed[] = [$customer->id, $customer->full_name, $result['old'], $result['new'], 'Manual Override'],
            };
        }

        if ($changed !== []) {
            $this->table(['Customer ID', 'Nama', 'SN Lama', 'SN Baru', 'Sumber'], $changed);
        }

        if ($skipped !== []) {
            $this->newLine();
            $this->warn('Dilewati (butuh review manual):');
            $this->table(['CID', 'Alasan'], $skipped);
        }

        $this->newLine();
        $this->info(sprintf(
            '%s: %d diubah, %d sudah cocok (tidak disentuh), %d dilewati.',
            $apply ? 'Selesai' : 'Dry-run selesai',
            count($changed),
            $unchanged,
            count($skipped),
        ));

        return self::SUCCESS;
    }

    /**
     * @return array{status: 'skip'|'unchanged'|'changed', reason?: string, old?: string, new?: string}
     */
    private function applyOrDryRun(Customer $customer, string $targetSerial, bool $apply): array
    {
        $hasInstalledInventorySerial = InventorySerial::where('customer_id', $customer->id)
            ->where('status', SerialStatus::INSTALLED->value)
            ->exists();

        if ($hasInstalledInventorySerial) {
            return ['status' => 'skip', 'reason' => 'SN berasal dari Inventori Gudang — selesaikan lewat alur Gudang, bukan command ini'];
        }

        $device = $customer->customerDevice;
        $tech = CustomerTechnicalDetail::where('customer_id', $customer->id)->first();

        $current = $device?->serial_number ?: ($tech?->router_or_ont_serial ?: $customer->ont_sn);

        if (mb_strtoupper(trim((string) $current)) === mb_strtoupper(trim($targetSerial))) {
            return ['status' => 'unchanged'];
        }

        if ($apply) {
            DB::transaction(function () use ($device, $tech, $customer, $targetSerial) {
                if ($device) {
                    $device->serial_number = $targetSerial;
                    $device->save();
                } elseif ($tech) {
                    $tech->router_or_ont_serial = $targetSerial;
                    $tech->save();
                } else {
                    $customer->ont_sn = $targetSerial;
                    $customer->save();
                }
            });
        }

        return ['status' => 'changed', 'old' => (string) $current, 'new' => $targetSerial];
    }

    private function resolvePop(): ?Pop
    {
        if ($this->option('pop-id')) {
            return Pop::find((int) $this->option('pop-id'));
        }

        if ($this->option('pop-name')) {
            return Pop::where('name', $this->option('pop-name'))->first();
        }

        return null;
    }

    /**
     * @return array{0: list<string>|null, 1: array<string, string>}
     */
    private function loadConfig(): array
    {
        $path = $this->option('config');
        if (! $path) {
            return [[], []];
        }

        $fullPath = str_starts_with($path, '/') ? $path : base_path($path);
        if (! file_exists($fullPath)) {
            $this->error("File config tidak ditemukan: {$fullPath}");

            return [null, []];
        }

        $data = json_decode((string) file_get_contents($fullPath), true);
        if (! is_array($data)) {
            $this->error("File config bukan JSON valid: {$fullPath}");

            return [null, []];
        }

        return [
            $data['skip_cids'] ?? [],
            $data['manual_overrides'] ?? [],
        ];
    }
}
