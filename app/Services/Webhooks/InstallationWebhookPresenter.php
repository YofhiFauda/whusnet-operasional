<?php

namespace App\Services\Webhooks;

use App\Enums\TaskType;
use App\Models\Customer;
use App\Models\Task;
use App\Support\IndonesianDate;

/**
 * Satu sumber payload untuk webhook `installation.activated` (API 1,
 * docs/api/business-logic.md). Dirakit SEKALI, dirender DUA kali:
 * `for()` mengembalikan data terstruktur yang jadi body JSON `http_json`
 * langsung, `toTelegramText()` merender teks buat transport `telegram` di
 * atas data yang sama. Kalau ada field baru, tambahkan di sini — jangan
 * duplikasi rantai fallback SN/ODP di tempat lain (pola sama ReceiptPresenter,
 * app/Services/Receipts/ReceiptPresenter.php).
 *
 * Kelas ini sengaja tidak melakukan I/O sendiri (query dilakukan via eager
 * load pasif) supaya murah dipanggil ulang — job kirim Telegram merender
 * ulang teks dari `payload` tersimpan lewat kelas ini, bukan menyimpan teks
 * jadi di outbox (lihat SendWebhookOutboxJob).
 *
 * @phpstan-type InstallationWebhookData array{
 *     event: string,
 *     event_id: string,
 *     idempotency_key: string,
 *     occurred_at: string,
 *     data: array{
 *         customer: array{cid: string|null, nama: string},
 *         pop: array{code: string|null, name: string|null, type: string|null},
 *         desa: array{id: int|null, name: string|null, kecamatan: string|null, kota: string|null},
 *         paket: array{code: string|null, name: string|null, bandwidth: string|null, harga_bulanan: string},
 *         perangkat: array{sn: string|null, odp: string|null, odp_port: string|null, olt: string|null, vlan: string|null},
 *         task: array{number: string, started_at: string|null}|null
 *     }
 * }
 */
class InstallationWebhookPresenter
{
    /**
     * @return InstallationWebhookData
     */
    public function for(Customer $customer, string $eventId, string $idempotencyKey): array
    {
        $customer->loadMissing([
            'pop',
            'village.district.city',
            'internetPackage',
            'customerDevice',
            'customerTechnicalDetail',
        ]);

        return [
            'event' => 'installation.activated',
            'event_id' => $eventId,
            'idempotency_key' => $idempotencyKey,
            'occurred_at' => now()->toIso8601String(),
            'data' => [
                'customer' => $this->customerBlock($customer),
                'pop' => $this->popBlock($customer),
                'desa' => $this->desaBlock($customer),
                'paket' => $this->paketBlock($customer),
                'perangkat' => $this->perangkatBlock($customer),
                'task' => $this->taskBlock($customer),
            ],
        ];
    }

    /**
     * Teks Telegram Eksternal. Daftar putih ketat (docs/api/business-logic.md
     * "Isi pesan Telegram Eksternal"): cid, POP, desa, paket, SN, ODP, nomor
     * task, nomor aktivasi, waktu. TIDAK PERNAH: nomor HP, alamat, NIK,
     * koordinat, kredensial perangkat — field itu memang tidak pernah dibaca
     * kelas ini, jadi tidak bisa bocor lewat sini.
     *
     * @param  InstallationWebhookData  $data
     */
    public function toTelegramText(array $data, int $activationNumber): string
    {
        $d = $data['data'];

        $lines = [
            '🔌 <b>Pemasangan Diaktivasi</b>',
            $activationNumber > 1 ? "Aktivasi #{$activationNumber} — data perangkat diperbarui" : 'Aktivasi #1',
            '',
            'CID: '.($d['customer']['cid'] ?? '-'),
            'Nama: '.$d['customer']['nama'],
            'POP: '.($d['pop']['name'] ?? '-'),
            'Desa: '.($d['desa']['name'] ?? '-').', '.($d['desa']['kecamatan'] ?? '-'),
            'Paket: '.($d['paket']['name'] ?? '-'),
            'SN: '.($d['perangkat']['sn'] ?? '-'),
            'ODP: '.($d['perangkat']['odp'] ?? '-'),
            'Task: '.($d['task']['number'] ?? '-'),
            // Cuma di teks Telegram yang dibaca manusia. `occurred_at` di
            // payload TETAP ISO-8601 — itu yang dipakai konsumen mesin
            // (Website B) untuk menentukan urutan event; memformatnya jadi
            // "20 Agustus 2026" akan merusak parsing di sisi sana.
            'Waktu: '.IndonesianDate::dateTime($data['occurred_at']),
        ];

        return implode("\n", $lines);
    }

    private function customerBlock(Customer $customer): array
    {
        return [
            'cid' => $customer->cid ?: ($customer->customer_code ?: null),
            'nama' => $customer->full_name,
        ];
    }

    private function popBlock(Customer $customer): array
    {
        $pop = $customer->pop;

        return [
            'code' => $pop?->code,
            'name' => $pop?->name,
            'type' => $pop?->type,
        ];
    }

    private function desaBlock(Customer $customer): array
    {
        $village = $customer->village;
        $district = $village?->district;
        $city = $district?->city;

        return [
            'id' => $village?->id,
            'name' => $village?->name,
            'kecamatan' => $district?->name,
            'kota' => $city?->name,
        ];
    }

    private function paketBlock(Customer $customer): array
    {
        $package = $customer->internetPackage;

        return [
            'code' => $package?->package_code,
            'name' => $package?->name,
            'bandwidth' => $package?->bandwidth_label,
            // Sudah cast decimal:2 — cast ke string LANGSUNG jadi string
            // desimal ("150000.00"). Jangan number_format(), itu nambah
            // pemisah ribuan yang salah buat field ini.
            'harga_bulanan' => $package ? (string) $package->monthly_price : '0.00',
        ];
    }

    /**
     * SN/ODP tidak punya satu rumah — tersebar di customer_devices,
     * customer_technical_details, dan customers. Urutan fallback SENGAJA
     * "perangkat dulu": customer_devices/customer_technical_details diisi
     * teknisi di lapangan saat pemasangan, sementara customers.odp_code bisa
     * cuma rencana awal dari pendaftaran. Mengikuti pola
     * resources/views/customers/tabs/_device.blade.php:43. Untuk ODP, sumber
     * 1 (customer_devices.odp) hampir selalu kosong — controller yang menulis
     * baris ini tidak pernah mengisi kolom itu — jadi rantai fallback bukan
     * kemewahan defensif, ia jalur normal.
     */
    private function perangkatBlock(Customer $customer): array
    {
        $device = $customer->customerDevice;
        $tech = $customer->customerTechnicalDetail;

        $sn = $device?->serial_number ?: ($tech?->router_or_ont_serial ?: $customer->ont_sn);
        $odp = $device?->odp ?: ($tech?->odp_number ?: $customer->odp_code);
        $odpPort = $device?->odp_port ?: $tech?->odp_port;
        $vlan = $tech?->vlan ?: $customer->vlan_id;

        return [
            'sn' => $sn,
            'odp' => $odp,
            'odp_port' => $odpPort,
            'olt' => $this->oltString($customer),
            'vlan' => $vlan,
        ];
    }

    /**
     * olt_number/olt_slot/olt_port adalah tiga kolom terpisah, teknisi ketik
     * bebas — tidak ada preseden format gabungan di repo ini. Digabung
     * "{number}/{slot}/{port}", bagian kosong di-skip, biar tetap satu string
     * ringkas tanpa mengarang pemisah yang belum pernah dipakai di mana pun.
     * Fallback ke customers.olt_code kalau ketiganya kosong.
     */
    private function oltString(Customer $customer): ?string
    {
        $tech = $customer->customerTechnicalDetail;

        $parts = array_filter([
            $tech?->olt_number,
            $tech?->olt_slot,
            $tech?->olt_port,
        ], fn ($v) => $v !== null && $v !== '');

        if ($parts !== []) {
            return implode('/', $parts);
        }

        return $customer->olt_code;
    }

    /**
     * Task PEMASANGAN terbaru pelanggan ini — query sama yang dipakai
     * storePemasangan() sendiri, tapi TANPA filter status: pada saat Aktivasi
     * ditekan task bisa saja sudah berubah status di antara dua panggilan,
     * dan kita cuma butuh nomor/waktu mulainya, bukan menegakkan status.
     * Tidak ada task.completed_at — task PSB memang belum selesai di titik
     * ini (penyelesaiannya di step 6, storeSpeedtest()).
     */
    private function taskBlock(Customer $customer): ?array
    {
        $task = Task::where('customer_id', $customer->id)
            ->where('task_type', TaskType::PEMASANGAN->value)
            ->latest('id')
            ->first();

        if (! $task) {
            return null;
        }

        return [
            'number' => $task->task_number,
            'started_at' => $task->started_at?->toIso8601String(),
        ];
    }
}
