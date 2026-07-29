<?php

namespace App\Models;

use App\Enums\Gender;
use App\Models\Concerns\RecordsAuditLogs;
use App\Services\CustomerValidationService;
use App\Traits\HasPopScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'person_id',
    'customer_code',
    'old_customer_id',
    'old_request_id',
    'cid',
    'full_name',
    'identity_number',
    'gender',
    'customer_type',
    'company_name',
    'npwp',
    'email',
    'primary_phone',
    'alternative_phone',
    'registration_date',
    'data_completeness_status',
    'pop_id',
    'distribution_id',
    'mini_pop_id',
    'status',
    'rejected_at',
    'terminated_at',
    'address',
    'latitude',
    'longitude',
    'city_id',
    'district_id',
    'village_id',
    'internet_package_id',
    'contract_period_months',
    'discount_amount',
    'tax_percent',
    'sales_code',
    'agent_code',
    'referral_customer_code',
    'ont_sn',
    'ip_address',
    'odp_code',
    'olt_code',
    'vlan_id',
    'foto_ktp',
    'foto_rumah',
    'foto_kontrak',
    'created_by',
    'updated_by',
])]
class Customer extends Model
{
    use HasFactory, HasPopScope, RecordsAuditLogs;

    protected string $auditModule = 'Data Pelanggan';

    protected array $auditHidden = [
        'foto_ktp',
        'foto_rumah',
        'foto_kontrak',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'registration_date' => 'date',
            'rejected_at' => 'datetime',
            'terminated_at' => 'datetime',
            'gender' => Gender::class,
        ];
    }

    /**
     * @return BelongsTo<City, $this>
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * @return BelongsTo<District, $this>
     */
    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    /**
     * @return BelongsTo<Village, $this>
     */
    public function village(): BelongsTo
    {
        return $this->belongsTo(Village::class);
    }

    /**
     * @return BelongsTo<InternetPackage, $this>
     */
    public function internetPackage(): BelongsTo
    {
        return $this->belongsTo(InternetPackage::class, 'internet_package_id');
    }

    /**
     * @return BelongsTo<SubscriptionStatus, $this>
     */
    public function subscriptionStatus(): BelongsTo
    {
        return $this->belongsTo(SubscriptionStatus::class, 'status', 'code');
    }

    /**
     * @return BelongsTo<Pop, $this>
     */
    public function pop(): BelongsTo
    {
        return $this->belongsTo(Pop::class);
    }

    /**
     * Identitas orang di balik baris pelanggan ini. Satu person bisa punya
     * banyak customer (daftar ulang, pindah kontrak) — lihat rancangan
     * persons. Nullable selama backfill belum jalan.
     *
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * The distribution area this customer belongs to.
     * Used as part of CID generation: {pop.cid_prefix}{olt_number}{distribution.code}...
     *
     * @return BelongsTo<Distribution, $this>
     */
    public function distribution(): BelongsTo
    {
        return $this->belongsTo(Distribution::class);
    }

    /**
     * Mini POP (OLT) spesifik customer ini — di-assign pasca pemasangan/aktivasi,
     * gak diisi saat registrasi (registrasi cuma pilih Cabang POP via pop_id).
     * Dipakai buat resolve segmen Mini POP di CID (lihat Pop::resolveMiniPopSegment()).
     *
     * @return BelongsTo<Pop, $this>
     */
    public function miniPop(): BelongsTo
    {
        return $this->belongsTo(Pop::class, 'mini_pop_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * @return HasOne<CustomerAddress, $this>
     */
    public function customerAddress(): HasOne
    {
        return $this->hasOne(CustomerAddress::class);
    }

    /**
     * @return HasOne<CustomerService, $this>
     */
    public function customerService(): HasOne
    {
        return $this->hasOne(CustomerService::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @return HasOne<Invoice, $this>
     */
    public function latestInvoice(): HasOne
    {
        return $this->hasOne(Invoice::class)->latestOfMany();
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasMany<CustomerSurvey, $this>
     */
    public function surveys(): HasMany
    {
        return $this->hasMany(CustomerSurvey::class);
    }

    /**
     * @return HasMany<CustomerInstallation, $this>
     */
    public function installations(): HasMany
    {
        return $this->hasMany(CustomerInstallation::class);
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function fopTasks(): HasMany
    {
        return $this->hasMany(FopTask::class);
    }

    /**
     * Get the latest survey.
     *
     * @return HasOne<CustomerSurvey, $this>
     */
    public function latestSurvey(): HasOne
    {
        return $this->hasOne(CustomerSurvey::class)->latestOfMany();
    }

    /**
     * @return HasOne<CustomerInstallation, $this>
     */
    public function latestInstallation(): HasOne
    {
        return $this->hasOne(CustomerInstallation::class)->latestOfMany();
    }

    /**
     * @return HasOne<CustomerDevice, $this>
     */
    public function customerDevice(): HasOne
    {
        return $this->hasOne(CustomerDevice::class);
    }

    /**
     * @return HasOne<CustomerTechnicalDetail, $this>
     */
    public function customerTechnicalDetail(): HasOne
    {
        return $this->hasOne(CustomerTechnicalDetail::class);
    }

    /**
     * @return HasMany<CustomerDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(CustomerDocument::class);
    }

    /**
     * Auto-update data_completeness_status whenever the model is saved.
     * Uses CustomerValidationService to derive the correct status.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saved(function (Customer $customer) {
            $customer->recalculateCompleteness();
        });
    }

    /**
     * Recalculate completeness and update the database column quietly.
     */
    public function recalculateCompleteness(): void
    {
        $this->unsetRelation('customerService');
        $this->unsetRelation('customerAddress');

        /** @var CustomerValidationService $service */
        $service = app(CustomerValidationService::class);
        $result = $service->validate($this);
        $newStatus = $result['completeness_status'];

        if ($this->data_completeness_status !== $newStatus) {
            $this->updateQuietly(['data_completeness_status' => $newStatus]);
        }
    }

    /**
     * Calculate data completeness using CustomerValidationService.
     *
     * Returns:
     *   percentage        (int)    : 0–100 fill percentage
     *   filled_count      (int)    : number of filled fields
     *   total_count       (int)    : total fields evaluated
     *   missing_required  (array)  : [key => label] of unfilled required fields
     *   missing_optional  (array)  : [key => label] of unfilled optional fields
     *   is_ready_billing  (bool)   : true when all required fields are filled
     *   completeness_status (string): derived status string
     */
    public function dataCompleteness(): array
    {
        /** @var CustomerValidationService $service */
        $service = app(CustomerValidationService::class);

        return $service->validate($this);
    }

    /**
     * Compute the display identifier based on customer status.
     *
     * Sesuai spesifikasi-pop-distribusi-cid.md:
     * - REQ ID murni (RQ######) saat: pending/survey/pemasangan/installed/terminated
     * - CID lengkap saat: aktif + punya distribusi
     * - C00RQ###### saat: aktif tanpa distribusi
     */
    public function getDisplayIdAttribute(): string
    {
        $pop = $this->pop;
        if (! $pop) {
            return $this->customer_code;
        }

        return $pop->resolveDisplayId($this);
    }

    /**
     * Get the label type for the display ID.
     * Returns 'REQ ID', 'CID', or 'ID' depending on the customer's status.
     */
    public function getDisplayIdLabelAttribute(): string
    {
        $status = strtolower((string) ($this->status ?? ''));

        $bareStatuses = ['terminated', 'pending', 'waiting_survey', 'surveyed', 'waiting_installation', 'installed'];
        if (in_array($status, $bareStatuses, true)) {
            return 'REQ ID';
        }

        if (in_array($status, ['active', 'suspended'], true)) {
            if ($this->distribution_id && $this->cid) {
                return 'CID';
            }

            return 'ID';
        }

        return 'ID';
    }

    /**
     * Get a clean formatted address without duplicated parts.
     * Removes duplicate segments like village/district if they are already in the string.
     */
    public function getCleanAddressAttribute(): string
    {
        $address = $this->address ?? '';

        // Pilihan B (rancangan §2.6): accessor TIDAK boleh memicu query tersembunyi.
        // Dulu membaca $this->village/district/city langsung — kalau relasinya tak
        // di-eager-load, itu 3 query per pemanggilan, dan accessor terlihat seperti
        // properti biasa di Blade sehingga jebakan ini menyebar diam-diam.
        //
        // Sekarang hanya membaca relasi yang SUDAH dimuat. Di bawah
        // preventLazyLoading (dev/test) ini tidak pernah melempar; di produksi
        // tidak pernah jadi query siluman. Kalau pemanggil lupa eager-load, alamat
        // mentah dikembalikan apa adanya — degradasi anggun, bukan N+1 senyap.
        // customer_addresses menyimpan village/district/city sebagai STRING; itu
        // dipakai sebagai sumber utama bila relasinya dimuat, relasi id→name jadi
        // cadangan.
        $addr = $this->relationLoaded('customerAddress') ? $this->customerAddress : null;

        $village = ($addr?->village)
            ?: ($this->relationLoaded('village') ? $this->village?->name : null);
        $district = ($addr?->district)
            ?: ($this->relationLoaded('district') ? $this->district?->name : null);
        $city = ($addr?->city)
            ?: ($this->relationLoaded('city') ? $this->city?->name : null);

        $parts = array_map('trim', explode(',', $address));

        $removables = array_filter([
            strtolower($village ?? ''),
            strtolower($district ?? ''),
            strtolower($city ?? ''),
        ]);

        while (! empty($parts)) {
            $lastPart = strtolower(end($parts));
            $cleanLastPart = trim(str_replace(['desa ', 'kec. ', 'kecamatan ', 'kab. ', 'kabupaten ', 'kota ', 'kelurahan '], '', $lastPart));

            if (in_array($cleanLastPart, $removables, true) || in_array($lastPart, $removables, true)) {
                array_pop($parts);
            } else {
                break;
            }
        }

        if ($village) {
            $parts[] = $village;
        }
        if ($district) {
            $parts[] = $district;
        }
        if ($city) {
            $parts[] = $city;
        }

        return empty($parts) ? '—' : implode(', ', array_filter($parts));
    }

    /**
     * Determine workflow stages progress.
     */
    public function workflowProgress(): array
    {
        $status = strtolower($this->status);

        $stages = [
            'registrasi' => ['label' => 'R', 'name' => 'Registrasi', 'status' => 'completed', 'color' => 'bg-green-500 dark:bg-green-600'],
            'survey' => ['label' => 'S', 'name' => 'Survey', 'status' => 'pending', 'color' => 'bg-slate-200 dark:bg-slate-700 text-slate-400 dark:text-slate-500'],
            'pemasangan' => ['label' => 'P', 'name' => 'Pemasangan', 'status' => 'pending', 'color' => 'bg-slate-200 dark:bg-slate-700 text-slate-400 dark:text-slate-500'],
            'uji' => ['label' => 'U', 'name' => 'Uji Layanan', 'status' => 'pending', 'color' => 'bg-slate-200 dark:bg-slate-700 text-slate-400 dark:text-slate-500'],
            'aktivasi' => ['label' => 'A', 'name' => 'Aktivasi', 'status' => 'pending', 'color' => 'bg-slate-200 dark:bg-slate-700 text-slate-400 dark:text-slate-500'],
        ];

        $completedStatuses = ['active', 'suspended', 'terminated'];

        // Survey
        if (in_array($status, ['surveyed', 'waiting_installation', 'installed', ...$completedStatuses])) {
            $stages['survey'] = ['label' => 'S', 'name' => 'Survey', 'status' => 'completed', 'color' => 'bg-green-500 dark:bg-green-600 text-white'];
        } elseif ($status === 'waiting_survey') {
            $stages['survey'] = ['label' => 'S', 'name' => 'Survey', 'status' => 'in_progress', 'color' => 'bg-amber-500 dark:bg-amber-600 text-white animate-pulse'];
        }

        // Pemasangan (Instalasi) & Uji Layanan
        if (in_array($status, ['installed', ...$completedStatuses])) {
            $stages['pemasangan'] = ['label' => 'P', 'name' => 'Pemasangan', 'status' => 'completed', 'color' => 'bg-green-500 dark:bg-green-600 text-white'];
            $stages['uji'] = ['label' => 'U', 'name' => 'Uji Layanan', 'status' => 'completed', 'color' => 'bg-green-500 dark:bg-green-600 text-white'];
        } elseif ($status === 'waiting_installation') {
            $stages['pemasangan'] = ['label' => 'P', 'name' => 'Pemasangan', 'status' => 'in_progress', 'color' => 'bg-amber-500 dark:bg-amber-600 text-white animate-pulse'];
        }

        // Aktivasi
        if (in_array($status, ['active', 'suspended'])) {
            $stages['aktivasi'] = ['label' => 'A', 'name' => 'Aktivasi', 'status' => 'completed', 'color' => $status === 'suspended' ? 'bg-amber-500 dark:bg-amber-600 text-white' : 'bg-green-500 dark:bg-green-600 text-white'];
        } elseif ($status === 'terminated') {
            $stages['aktivasi'] = ['label' => 'A', 'name' => 'Aktivasi', 'status' => 'terminated', 'color' => 'bg-red-500 dark:bg-red-600 text-white'];
        } elseif ($status === 'installed') {
            $stages['aktivasi'] = ['label' => 'A', 'name' => 'Aktivasi', 'status' => 'in_progress', 'color' => 'bg-amber-500 dark:bg-amber-600 text-white animate-pulse'];
        }

        return $stages;
    }
}
