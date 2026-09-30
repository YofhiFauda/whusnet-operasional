<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\City;
use App\Models\Customer;
use App\Models\CustomerDevice;
use App\Models\CustomerSurvey;
use App\Models\CustomerTechnicalDetail;
use App\Models\District;
use App\Models\InternetPackage;
use App\Models\User;
use App\Models\Village;

/**
 * Edit data pelanggan LANGSUNG dari layar Verifikasi (Registrasi/Survey/
 * Pemasangan/Validasi Admin) — dulu CS harus keluar ke Edit Pelanggan biasa
 * buat benerin typo nama/HP/paket, padahal semua data itu sudah tampil di
 * layar yang sama (bug 2026-09-30, laporan user).
 *
 * Scope field SENGAJA sempit dan tetap — field jaringan (POP/Mini
 * POP/Distribusi/CID) dan data perangkat pemasangan TIDAK disentuh di sini.
 * Keduanya punya guard berat sendiri (lock pra-pemasangan, hierarki POP,
 * dampak ke CID) yang cuma benar kalau lewat NetworkAssignmentService/Edit
 * Pelanggan — menyalinnya jadi form cepat di sini gampang menyimpang dari
 * guard aslinya.
 */
class CustomerVerificationEditService
{
    /**
     * Data Diri — identitas & alamat dasar. TIDAK termasuk `customer_type`:
     * mengubahnya di tengah alur bisa memindahkan pelanggan ke jalur
     * verifikasi Business Development yang berbeda (lihat
     * CustomerVerificationController::finalVerify() — gate BD dibaca dari
     * field ini), dan sampai sekarang tidak ada satu pun form (Create/Edit)
     * yang mengizinkan field ini diedit — sengaja tidak dibuka pertama kali
     * lewat form cepat ini.
     *
     * `customer_addresses` (mirror historis yang dibaca List Pelanggan, Task
     * Saya, kartu FOP, invoice — lihat `CustomerController::update()`) ditulis
     * ulang di sini juga. Ini DUPLIKAT KECIL dari blok yang sama persis di
     * `CustomerController::update()`, sengaja tidak diekstrak jadi satu method
     * bersama supaya perbaikan ini tidak menyentuh file itu — beberapa
     * perbaikan lain sedang berjalan paralel di `CustomerController.php`
     * (2026-09-30), dan blok address-mirror ini kecil & stabil (jarang
     * berubah), jadi risiko duplikasi di sini masih lebih kecil daripada
     * risiko konflik menyentuh file yang sama.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateIdentity(Customer $customer, array $data, User $actor): Customer
    {
        $oldValues = $customer->only(array_keys($data));

        $customer->update($data);

        $cityName = ! empty($data['city_id']) ? City::where('id', $data['city_id'])->value('name') : null;
        $districtName = ! empty($data['district_id']) ? District::where('id', $data['district_id'])->value('name') : null;
        $villageName = ! empty($data['village_id']) ? Village::where('id', $data['village_id'])->value('name') : null;

        $customer->customerAddress()->updateOrCreate([], [
            'full_address' => $data['address'] ?? null,
            'city' => $cityName,
            'district' => $districtName,
            'village' => $villageName,
            'city_id' => $data['city_id'] ?? null,
            'district_id' => $data['district_id'] ?? null,
            'village_id' => $data['village_id'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
        ]);

        $this->logChange($customer, $actor, 'update_identity_verifikasi', $oldValues, $data);

        return $customer->refresh();
    }

    /**
     * Paket Internet — ganti paket + refresh harga dasar/snapshot. Diskon/PPN/
     * biaya lain DIPERTAHANKAN dari `customer_services` yang ada (CS tidak
     * dikasih kontrol nominal diskon/pajak lewat form ini) — formula sama
     * persis dengan koreksi paket di `CustomerSurveyController::store()`.
     */
    public function updatePackage(Customer $customer, int $internetPackageId, User $actor): Customer
    {
        if ($internetPackageId === (int) $customer->internet_package_id) {
            return $customer;
        }

        $package = InternetPackage::findOrFail($internetPackageId);
        $service = $customer->customerService()->first();

        $discount = (float) ($service->discount ?? 0);
        $ppn = (float) ($service->ppn ?? 0);
        $otherFee = (float) ($service->other_fee ?? 0);
        $monthlyPrice = (float) $package->monthly_price;
        $discountedPrice = max(0, $monthlyPrice - $discount);
        $totalBill = $discountedPrice * (1 + $ppn / 100) + $otherFee;

        $oldPackageId = $customer->internet_package_id;

        $customer->update(['internet_package_id' => $package->id]);
        $customer->customerService()->updateOrCreate([], [
            'internet_package_id' => $package->id,
            'package_name_snapshot' => $package->name,
            'download_speed_snapshot' => isset($package->download_speed_mbps) ? $package->download_speed_mbps.' Mbps' : null,
            'upload_speed_snapshot' => isset($package->upload_speed_mbps) ? $package->upload_speed_mbps.' Mbps' : null,
            'monthly_price' => $monthlyPrice,
            'total_monthly_bill' => $totalBill,
        ]);

        $this->logChange(
            $customer,
            $actor,
            'update_paket_verifikasi',
            ['internet_package_id' => $oldPackageId],
            ['internet_package_id' => $package->id]
        );

        return $customer->refresh();
    }

    /**
     * Data Survey — koreksi hasil laporan survey teknisi (ODP terdekat,
     * estimasi kabel, tanggal request pemasangan, catatan surveyor). Foto,
     * material, dan checklist alat kerja TIDAK diedit dari sini — itu bagian
     * laporan lapangan teknisi (`CustomerSurveyController`), bukan koreksi
     * administratif CS.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateSurveyData(CustomerSurvey $survey, array $data, User $actor): CustomerSurvey
    {
        $oldValues = $survey->only(array_keys($data));

        $survey->update($data);

        $this->logChange(
            $survey->customer,
            $actor,
            'update_survey_verifikasi',
            $oldValues,
            $data
        );

        return $survey->refresh();
    }

    /**
     * Data Pemasangan — Data Perangkat + Distribusi Jaringan (ODP/OLT) + catatan
     * pemasangan. Dibuka HANYA di tahap Validasi Admin (bug 2026-09-30 lanjutan,
     * permintaan eksplisit user) — bukan saat pemasangan masih berjalan (tim di
     * lapangan masih bisa mengubahnya lewat laporan sendiri).
     *
     * Field & cara tulis MENIRU PERSIS blok yang sama di
     * `CustomerController::update()` (customer_devices/customer_technical_details,
     * termasuk aturan `device_type` NOT NULL) — DUPLIKAT KECIL disengaja, lihat
     * alasan lengkap di docblock `updateIdentity()`.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateInstallationData(Customer $customer, array $data, User $actor): Customer
    {
        $installation = $customer->installations()->latest()->first();
        if ($installation && array_key_exists('installation_note', $data)) {
            $installation->update(['installation_note' => $data['installation_note']]);
        }

        $deviceFields = array_intersect_key([
            'device_type' => $data['device_type'] ?? null,
            'brand' => $data['brand'] ?? null,
            'model' => $data['model'] ?? null,
            'serial_number' => $data['serial_number'] ?? null,
            'mac_address' => $data['mac_address'] ?? null,
            'wifi_ssid' => $data['wifi_ssid'] ?? null,
            'wifi_password' => $data['wifi_password'] ?? null,
            'connection_mode' => $data['connection_mode'] ?? null,
            'pppoe_username' => $data['pppoe_username'] ?? null,
            'pppoe_password' => $data['pppoe_password'] ?? null,
        ], $data);

        $deviceExists = CustomerDevice::where('customer_id', $customer->id)->exists();
        if ($deviceExists && array_key_exists('device_type', $deviceFields) && blank($deviceFields['device_type'])) {
            unset($deviceFields['device_type']);
        } elseif (! $deviceExists && array_filter($deviceFields) && blank($deviceFields['device_type'] ?? null)) {
            $deviceFields['device_type'] = 'other';
        }
        if ($deviceFields !== [] && (array_filter($deviceFields) || $deviceExists)) {
            $customer->customerDevice()->updateOrCreate(['customer_id' => $customer->id], $deviceFields);
        }

        $technicalFields = array_intersect_key([
            'odp_number' => $data['odp_number'] ?? null,
            'odp_port' => $data['odp_port'] ?? null,
            'olt_number' => $data['olt_number'] ?? null,
            'olt_slot' => $data['olt_slot'] ?? null,
            'olt_port' => $data['olt_port'] ?? null,
            'vlan' => $data['vlan'] ?? null,
            'router_number' => $data['router_number'] ?? null,
            'initial_attenuation' => $data['initial_attenuation'] ?? null,
        ], $data);
        if ($technicalFields !== [] && (array_filter($technicalFields) || CustomerTechnicalDetail::where('customer_id', $customer->id)->exists())) {
            $customer->customerTechnicalDetail()->updateOrCreate(['customer_id' => $customer->id], $technicalFields);
        }

        $this->logChange($customer, $actor, 'update_pemasangan_verifikasi', [], $data);

        return $customer->refresh();
    }

    /**
     * Data Pengujian — hasil speedtest & kualitas sinyal. Dibuka HANYA di tahap
     * Validasi Admin, sama seperti Data Pemasangan. Beda dari field lain: ini
     * TIDAK divalidasi di `CustomerController::update()` sama sekali (cuma bisa
     * masuk lewat laporan teknisi/import) — jadi bukan menyalin blok yang sudah
     * ada, ini kemampuan edit BARU untuk field-field ini.
     *
     * `speed_conformity_percent` dihitung ulang dari `test_download` — formula
     * sama dengan `CustomerInstallationController::storePemasangan()`.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateTestReport(Customer $customer, array $data, User $actor): Customer
    {
        $package = $customer->internetPackage;
        $speedConformity = null;
        if ($package && $package->download_speed_mbps > 0 && ! empty($data['test_download'])) {
            $speedConformity = ($data['test_download'] / $package->download_speed_mbps) * 100;
        }

        $customer->customerTechnicalDetail()->updateOrCreate(['customer_id' => $customer->id], [
            'test_download' => $data['test_download'] ?? null,
            'test_upload' => $data['test_upload'] ?? null,
            'latency_ms' => $data['latency_ms'] ?? null,
            'jitter_ms' => $data['jitter_ms'] ?? null,
            'packet_loss_percent' => $data['packet_loss_percent'] ?? null,
            'actual_attenuation' => $data['actual_attenuation'] ?? null,
            'speed_conformity_percent' => $speedConformity,
        ]);

        $this->logChange($customer, $actor, 'update_pengujian_verifikasi', [], $data);

        return $customer->refresh();
    }

    private function logChange(Customer $customer, User $actor, string $action, array $oldValues, array $newValues): void
    {
        AuditLog::create([
            'user_id' => $actor->id,
            'module' => 'Data Pelanggan',
            'action' => $action,
            'auditable_type' => Customer::class,
            'auditable_id' => $customer->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'created_at' => now(),
        ]);
    }
}
