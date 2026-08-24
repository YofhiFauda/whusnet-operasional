<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerService;

/**
 * S3-T007 — Validasi Kelengkapan Data Pelanggan
 *
 * Handles validation logic for customer data completeness.
 * Determines whether a customer's profile meets the minimum
 * requirements to enter the active billing pipeline.
 *
 * Field wajib agar siap billing (sesuai docs/ACCEPTANCE_CRITERIA.md):
 *   1.  Nama lengkap           → full_name
 *   2.  Nomor HP               → primary_phone / phone
 *   3.  Alamat lengkap         → address
 *   4.  Desa/Kelurahan         → village_id
 *   5.  Kecamatan              → district_id
 *   6.  Kota/Kabupaten         → city_id
 *   7.  POP/Cabang             → pop_id
 *   8.  Paket internet         → internet_package_id
 *   9.  Harga bulanan          → customerService.monthly_price
 *  10.  Tanggal aktivasi       → customerService.activation_date
 *  11.  Tanggal jatuh tempo    → customerService.due_date
 *  12.  Status layanan         → status (customer status field)
 */
class CustomerValidationService
{
    /**
     * Daftar field wajib agar pelanggan siap billing.
     * Key = nama field di Customer model, Value = label ramah pengguna.
     */
    public const REQUIRED_FIELDS = [
        'full_name' => 'Nama Lengkap',
        'primary_phone' => 'Nomor HP Utama',
        'address' => 'Alamat Lengkap',
        'village_id' => 'Desa / Kelurahan',
        'district_id' => 'Kecamatan',
        'city_id' => 'Kota / Kabupaten',
        'pop_id' => 'POP / Cabang',
        'internet_package_id' => 'Paket Internet',
        // Service-level required fields (checked via relation):
        'service_monthly_price' => 'Harga Bulanan',
        'service_activation_date' => 'Tanggal Aktivasi',
        'service_due_date' => 'Tanggal Jatuh Tempo',
        'status' => 'Status Layanan',
    ];

    /**
     * Field opsional/teknis yang meningkatkan kelengkapan profil
     * tetapi tidak menghalangi billing.
     */
    public const OPTIONAL_FIELDS = [
        'identity_number' => 'NIK / Nomor Identitas',
        'gender' => 'Jenis Kelamin',
        'email' => 'Alamat Email',
        'latitude' => 'Koordinat Latitude',
        'longitude' => 'Koordinat Longitude',
        'sales_code' => 'Kode Sales',
        'agent_code' => 'Kode Agent',
        'ont_sn' => 'ONT Serial Number',
        'odp_code' => 'Kode ODP',
        'olt_code' => 'Kode OLT',
        'vlan_id' => 'VLAN ID',
    ];

    /**
     * Validate customer data completeness.
     *
     * Returns an array with:
     *   - percentage        (int)    : 0–100 fill percentage
     *   - filled_count      (int)    : number of filled fields
     *   - total_count       (int)    : total fields evaluated
     *   - missing_required  (array)  : keys of unfilled required fields
     *   - missing_optional  (array)  : keys of unfilled optional fields
     *   - is_ready_billing  (bool)   : true if all required fields are filled
     *   - completeness_status (string): derived status string
     *
     * @param  Customer  $customer  Must have customerService relation loaded
     * @return array<string, mixed>
     */
    public function validate(Customer $customer): array
    {
        // Ensure relation is available (cheap if already loaded)
        if (! $customer->relationLoaded('customerService')) {
            $customer->load('customerService');
        }

        $service = $customer->customerService;

        $missingRequired = [];
        $missingOptional = [];
        $filledCount = 0;

        $totalFields = count(self::REQUIRED_FIELDS) + count(self::OPTIONAL_FIELDS);

        // --- Check required fields ---
        foreach (self::REQUIRED_FIELDS as $key => $label) {
            $filled = $this->isFieldFilled($customer, $service, $key);
            if ($filled) {
                $filledCount++;
            } else {
                $missingRequired[$key] = $label;
            }
        }

        // --- Check optional fields ---
        foreach (self::OPTIONAL_FIELDS as $key => $label) {
            $filled = $this->isFieldFilled($customer, $service, $key);
            if ($filled) {
                $filledCount++;
            } else {
                $missingOptional[$key] = $label;
            }
        }

        $percentage = $totalFields > 0 ? (int) round(($filledCount / $totalFields) * 100) : 0;
        $isReadyBilling = count($missingRequired) === 0;

        // Derive status
        $completenessStatus = $this->deriveCompletenessStatus($customer, $isReadyBilling, $missingRequired);

        return [
            'percentage' => $percentage,
            'filled_count' => $filledCount,
            'total_count' => $totalFields,
            'missing_required' => $missingRequired,   // [key => label]
            'missing_optional' => $missingOptional,   // [key => label]
            'is_ready_billing' => $isReadyBilling,
            'completeness_status' => $completenessStatus,
        ];
    }

    /**
     * Determine the data_completeness_status string value.
     *
     * Priority:
     *   siap_billing       → already set by admin & all required filled
     *   lengkap            → all required + all optional filled
     *   perlu_dilengkapi   → some required fields are missing
     *   draft              → many required fields missing (< 50% of required filled)
     */
    public function deriveCompletenessStatus(
        Customer $customer,
        bool $isReadyBilling,
        array $missingRequired
    ): string {
        // Preserve 'siap_billing' if admin has already activated it
        if ($customer->data_completeness_status === 'siap_billing' && $isReadyBilling) {
            return 'siap_billing';
        }

        if (! $isReadyBilling) {
            $requiredTotal = count(self::REQUIRED_FIELDS);
            $missingCount = count($missingRequired);
            $filledRequired = $requiredTotal - $missingCount;

            // Draft: less than half of required fields filled
            if ($filledRequired < ($requiredTotal / 2)) {
                return 'draft';
            }

            return 'perlu_dilengkapi';
        }

        // All required filled — check if optional fields are also filled.
        // loadMissing(), bukan load(): dataCompleteness() dipanggil PER BARIS di
        // daftar pelanggan (customers/index.blade.php:332) yang sudah meng-eager-load
        // customerService, dan load() tetap menembak DB walau relasinya sudah ada.
        $customer->loadMissing('customerService');
        $service = $customer->customerService;
        foreach (self::OPTIONAL_FIELDS as $key => $label) {
            if (! $this->isFieldFilled($customer, $service, $key)) {
                // Has at least one optional field missing → still 'lengkap', not perfect
                return 'lengkap';
            }
        }

        return 'lengkap';
    }

    /**
     * Check whether a given field is considered "filled".
     *
     * Service-level fields are prefixed with "service_" and are
     * looked up on the related customerService model.
     *
     * @param  CustomerService|null  $service
     */
    private function isFieldFilled(Customer $customer, $service, string $key): bool
    {
        // Fields sourced from customerService relation
        if (str_starts_with($key, 'service_')) {
            $serviceField = substr($key, 8); // strip "service_" prefix
            if ($service === null) {
                return false;
            }
            $value = $service->{$serviceField} ?? null;
        } else {
            $value = $customer->{$key} ?? null;
        }

        if ($value === null) {
            return false;
        }

        // Beberapa field (mis. gender) di-cast ke backed enum — unwrap ke
        // ->value dulu sebelum (string) cast, enum gak punya __toString().
        if ($value instanceof \BackedEnum) {
            $value = $value->value;
        }

        $stringValue = trim((string) $value);

        return $stringValue !== '' && $stringValue !== '0';
    }
}
