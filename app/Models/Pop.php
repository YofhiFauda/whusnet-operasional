<?php

namespace App\Models;

use App\Models\Concerns\RecordsAuditLogs;
use App\Models\Customer;
use App\Models\Distribution;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use LogicException;
    
#[Fillable([
    'code',
    'pop_code',
    'registration_prefix',
    'cid_prefix',
    'name',
    'type',
    'parent_id',
    'address',
    'village',
    'district',
    'city',
    'latitude',
    'longitude',
    'pic_name',
    'pic_phone',
    'status',
])]
class Pop extends Model
{
    use RecordsAuditLogs, HasFactory;

    protected string $auditModule = 'POP/Cabang';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'parent_id' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function distributions(): HasMany
    {
        return $this->hasMany(Distribution::class);
    }

    /**
     * Get the parent POP.
     * @return BelongsTo<Pop, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Pop::class, 'parent_id');
    }

    /**
     * Get the child POPs.
     *
     * @return HasMany<Pop, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Pop::class, 'parent_id');
    }

    /**
     * Get identifier sequences for this POP.
     *
     * @return HasMany<PopSequence, $this>
     */
    public function sequences(): HasMany
    {
        return $this->hasMany(PopSequence::class);
    }

    /**
     * Get payments recorded under this POP.
     *
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get the users assigned to this POP.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<User, $this>
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_pops');
    }

    /**
     * Scope a query to only include POPs accessible by the given user.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \App\Models\User|null $user
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForUser($query, $user = null)
    {
        $user = $user ?? Auth::user();

        if (!$user) {
            return $query->whereRaw('1 = 0'); // return empty if no user
        }

        if (in_array($user->role?->name, ['Owner', 'Admin', 'Admin Pusat'], true)) {
            return $query; // return all
        }

        return $query->whereHas('users', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        });
    }

    /**
     * Generate a registration number (customer_code) for a new customer.
     *
     * Format: {registration_prefix}{######}
     * Example: RQ000001
     *
     * This code is PERMANENT — it stays linked to the customer for life
     * and becomes the base of the CID after activation.
     * When a customer is terminated, this bare "RQ######" portion is displayed in the UI.
     */
    public function generateRegistrationNumber(): string
    {
        if (!$this->cid_prefix) {
            throw new LogicException('POP cid_prefix belum dikonfigurasi.');
        }
        if (!$this->registration_prefix) {
            throw new LogicException('POP registration_prefix belum dikonfigurasi.');
        }

        $nextNumber = DB::transaction(function (): int {
            $sequence = PopSequence::query()
                ->where('pop_id', $this->id)
                ->where('sequence_type', PopSequence::TYPE_REGISTRATION)
                ->lockForUpdate()
                ->first();

            if (!$sequence) {
                $sequence = PopSequence::create([
                    'pop_id' => $this->id,
                    'sequence_type' => PopSequence::TYPE_REGISTRATION,
                    'current_number' => 0,
                ]);
            }

            $sequence->current_number++;
            $sequence->save();

            return $sequence->current_number;
        });

        // Format: {registration_prefix}{######}
        return sprintf('%s%06d', $this->registration_prefix, $nextNumber);
    }

    /**
     * Extract the bare sequential registration ID from a customer_code.
     * Used for display when a customer is terminated.
     *
     * Example: "C00RQ000001" → "RQ000001"
     *
     * Strips the leading cid_prefix and "00" OLT placeholder, leaving only
     * the registration_prefix + 6-digit number which is the permanent identifier.
     */
    public function extractBareRegistrationId(string $customerCode): string
    {
        $prefix = $this->cid_prefix;
        if ($prefix !== '' && str_starts_with($customerCode, $prefix . '00')) {
            return substr($customerCode, strlen($prefix) + 2);
        }

        // Fallback: return as-is if format doesn't match.
        return $customerCode;
    }

    /**
     * Resolve the correct display identifier for a customer based on their status.
     *
     * Alur sesuai spesifikasi-pop-distribusi-cid.md:
     *
     *   Status pending/survey/installation/installed/terminated
     *     → REQ ID murni: "RQ######"
     *
     *   Status active/suspended + punya distribusi
     *     → CID lengkap: "{cid_prefix}{mini_pop}{dist_code}{req_id}_{DESA}_{NAMA}"
     *       Contoh: D2X6CRQ001296_MANGKUJAYAN_DYAHGALUH
     *
     *   Status active/suspended + belum punya distribusi
     *     → Format default: "{cid_prefix}00{req_id}"
     *       Contoh: C00RQ001296
     *
     * @param Customer $customer
     * @return string
     */
    public function resolveDisplayId(Customer $customer): string
    {
        $status = strtolower((string) ($customer->status ?? ''));
        $reqId = $this->extractBareRegistrationId($customer->customer_code);

        // Status gagal/reject/putus hanya menampilkan REQ ID murni
        $bareStatuses = ['terminated', 'failed', 'rejected', 'putus', 'gagal'];
        if (in_array($status, $bareStatuses, true)) {
            return $reqId;
        }

        // Jika sudah masuk distribusi → tampilkan CID lengkap
        if ($customer->distribution_id && $customer->cid) {
            return $customer->cid;
        }

        // Default untuk status lainnya (registrasi, pending, aktif, dsb)
        // Format default C00RQ######
        return sprintf('%s00%s', $this->cid_prefix, $reqId);
    }

    /**
     * Generate a complex CID based on customer and distribution details.
     *
     * Format: {cid_prefix}{olt_number}{dist_code}{customer_code}_{DESA}_{NAMA}
     * Example: C1X1ARQ000001_MANGKUJAYAN_DYAHPURBA
     *
     * The CID prepends the real olt_number and
     * inserts the distribution code to the base RQ id, producing the final activated identity.
     */
    public function generateComplexCid(Customer $customer, ?Distribution $distribution = null): string
    {
        $prefix = $this->cid_prefix;
        $tech = $customer->customerTechnicalDetail;
        $oltNumber = $this->resolveMiniPopSegment($customer, $tech?->olt_number);
        $distCode = $distribution ? $distribution->code : 'XX';

        // Use the permanent registration identifier part only, e.g. "RQ000001".
        $reqId = $this->extractBareRegistrationId($customer->customer_code);

        return sprintf('%s%s%s%s', $prefix, $oltNumber, $distCode, $reqId);
    }

    /**
     * Generate the PPPOE username format for this customer.
     * Format: {CID}_{DESA}_{NAMA}
     */
    public function generatePppoeUsername(Customer $customer, ?Distribution $distribution = null): string
    {
        $cid = $this->generateComplexCid($customer, $distribution);
        $villageName = strtoupper(str_replace(' ', '', $customer->village?->name ?? 'UNK'));
        $customerName = strtoupper(str_replace(' ', '', $customer->full_name));

        return sprintf('%s_%s_%s', $cid, $villageName, $customerName);
    }

    private function resolveMiniPopSegment(Customer $customer, ?string $fallback = null): string
    {
        $popCode = trim((string) ($customer->pop?->pop_code ?? ''));
        $cidPrefix = trim((string) ($customer->pop?->cid_prefix ?? $this->cid_prefix ?? ''));

        if ($popCode !== '' && $cidPrefix !== '' && str_starts_with($popCode, $cidPrefix)) {
            $miniSegment = substr($popCode, strlen($cidPrefix));
            $miniSegment = preg_replace('/[^A-Z0-9]/i', '', $miniSegment) ?: '';

            if ($miniSegment !== '') {
                return $miniSegment;
            }
        }

        if (!empty($fallback)) {
            $fallback = preg_replace('/[^A-Z0-9]/i', '', (string) $fallback) ?: '';
            if ($fallback !== '') {
                return $fallback;
            }
        }

        return '1';
    }

    /**
     * @deprecated Use generateRegistrationNumber() instead.
     * Kept for backward compatibility with legacy CID-type sequence calls.
     */
    public function generateCid(): string
    {
        if (!$this->cid_prefix) {
            throw new LogicException('POP cid_prefix belum dikonfigurasi.');
        }

        $nextNumber = DB::transaction(function (): int {
            $sequence = PopSequence::query()
                ->where('pop_id', $this->id)
                ->where('sequence_type', PopSequence::TYPE_CID)
                ->lockForUpdate()
                ->first();

            if (!$sequence) {
                $sequence = PopSequence::create([
                    'pop_id' => $this->id,
                    'sequence_type' => PopSequence::TYPE_CID,
                    'current_number' => 0,
                ]);
            }

            $sequence->current_number++;
            $sequence->save();

            return $sequence->current_number;
        });

        return sprintf('%s%06d', $this->cid_prefix, $nextNumber);
    }
}
