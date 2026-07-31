<?php

namespace App\Models;

use App\Models\Concerns\RecordsAuditLogs;
use App\Services\EffectiveAccessService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
    use HasFactory, RecordsAuditLogs;

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
     *
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

    public function userRoleScopeTargets(): HasMany
    {
        return $this->hasMany(UserRoleScopeTarget::class);
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
     * @return BelongsToMany<User, $this>
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_pops');
    }

    /**
     * Scope a query to only include POPs accessible by the given user.
     *
     * @param  Builder  $query
     * @param  User|null  $user
     * @return Builder
     */
    public function scopeForUser($query, $user = null)
    {
        $user = $user ?? Auth::user();

        if (! $user) {
            return $query->whereRaw('1 = 0'); // return empty if no user
        }

        // Fase 5.5 — pakai EffectiveAccessService, BUKAN pivot user_pops via
        // whereHas('users'). Dua bug di versi lama:
        //  1. Jalur user_pops TIDAK paham pop_tree — user ber-scope pop_tree bisa
        //     lihat daftar POP yang salah di dropdown (kebocoran lintas cabang).
        //  2. Cek akses penuh memakai role NAME ('Owner'/'Admin'/'Admin Pusat')
        //     padahal seluruh repo pakai role CODE, dan 'Admin Pusat' tidak ada
        //     di RoleSeeder → cek itu praktis mati.
        // getAllowedPopIds() sudah resolve pop_tree DAN di-cache Redis;
        // hasAllPopAccess() menangani deny-by-default (scope kosong ≠ akses penuh).
        $access = app(EffectiveAccessService::class);

        if ($access->hasAllPopAccess($user)) {
            return $query; // akses semua POP — tanpa filter
        }

        // Scope kosong → whereIn('id', []) → tidak ada POP (deny-by-default).
        return $query->whereIn($this->getTable().'.id', $access->getAllowedPopIds($user));
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
        if (! $this->cid_prefix) {
            throw new LogicException('POP cid_prefix belum dikonfigurasi.');
        }
        if (! $this->registration_prefix) {
            throw new LogicException('POP registration_prefix belum dikonfigurasi.');
        }

        $prefix = $this->registration_prefix;

        // customer_code only needs to be unique within a branch (mini-pops share
        // their cabang's cid_prefix, so the collision check must cover the whole
        // cabang subtree — not just this exact POP row — to stay consistent with
        // the (pop_id, customer_code) DB constraint's real-world guarantee).
        $branchPopId = $this->parent_id ?? $this->id;

        $candidateCode = DB::transaction(function () use ($prefix, $branchPopId): string {
            $sequence = PopSequence::query()
                ->where('pop_id', $this->id)
                ->where('sequence_type', PopSequence::TYPE_REGISTRATION)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                $sequence = PopSequence::create([
                    'pop_id' => $this->id,
                    'sequence_type' => PopSequence::TYPE_REGISTRATION,
                    'current_number' => 0,
                ]);
            }

            // Sinkronisasi counter: pastikan current_number >= nomor tertinggi
            // yang sudah ada di tabel customers untuk POP ini (menghindari collision
            // akibat data import/migrasi yang memiliki kode lebih tinggi dari counter).
            $prefixLen = strlen($prefix) + 1;
            $maxExistingNumber = Customer::where('pop_id', $this->id)
                ->where('customer_code', 'like', $prefix.'%')
                ->selectRaw("MAX(CAST(SUBSTRING(customer_code, {$prefixLen}) AS UNSIGNED)) as max_num")
                ->value('max_num') ?? 0;

            if ($maxExistingNumber >= $sequence->current_number) {
                $sequence->current_number = $maxExistingNumber;
            }

            // Loop sampai menemukan kode yang belum dipakai DI CABANG INI
            do {
                $sequence->current_number++;
                $candidate = sprintf('%s%06d', $prefix, $sequence->current_number);
            } while (
                Customer::where('customer_code', $candidate)
                    ->where(function ($q) use ($branchPopId) {
                        $q->where('pop_id', $branchPopId)
                            ->orWhereHas('pop', fn ($pq) => $pq->where('parent_id', $branchPopId));
                    })
                    ->exists()
            );

            $sequence->save();

            return $candidate;
        });

        return $candidateCode;
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
        if ($prefix !== '' && str_starts_with($customerCode, $prefix.'00')) {
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
     *   Status active/suspended + `customers.cid` SUDAH terisi
     *     → pakai CID tersimpan apa adanya: "{cid_prefix}{mini_pop}{dist_code}{req_id}"
     *       Contoh: D2X6CRQ001296 — termasuk CID legacy yang segmen distribusinya
     *       "XX" (C1XXRQ000011) karena distribusinya memang tidak diketahui.
     *
     *   Status active/suspended + CID belum pernah digenerate
     *     → Format default: "{cid_prefix}00{req_id}"
     *       Contoh: C00RQ001296
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

        // CID tersimpan = identitas OTORITATIF pelanggan; kalau sudah ada,
        // itu yang ditampilkan.
        //
        // SENGAJA tidak lagi mensyaratkan `distribution_id` terisi. Dulu begitu,
        // dan akibatnya 331 pelanggan aktif hasil migrasi legacy — yang CID-nya
        // sudah ada (mis. C1XXRQ000011, segmen distribusi "XX" karena tidak
        // diketahui) tapi `distribution_id`-nya NULL — ditampilkan sebagai
        // "C00RQ000011": CID karangan yang tidak pernah ada di kenyataan, beda
        // dari nilai di kolom `cid`, dan tidak ketemu waktu dicari.
        if ($customer->cid) {
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
        // 1. Prioritas utama: Mini POP yang di-assign eksplisit ke customer
        // (lewat modal assignment pasca pemasangan/aktivasi). Ini satu-satunya
        // sumber yang bisa beda per-customer walau mereka 1 Cabang POP yang sama.
        $miniPop = $customer->miniPop;
        if ($miniPop) {
            $miniPopCode = trim((string) $miniPop->pop_code);
            $miniCidPrefix = trim((string) ($miniPop->cid_prefix ?? $this->cid_prefix ?? ''));

            if ($miniPopCode !== '' && $miniCidPrefix !== '' && str_starts_with($miniPopCode, $miniCidPrefix)) {
                $segment = preg_replace('/[^A-Z0-9]/i', '', substr($miniPopCode, strlen($miniCidPrefix))) ?: '';
                if ($segment !== '') {
                    return $segment;
                }
            }
        }

        // 2. Legacy fallback: pop_code milik Cabang POP customer sendiri. Catatan:
        // ini NILAINYA SAMA untuk semua customer di Cabang yang sama (bukan per-OLT),
        // dipertahankan cuma buat customer lama yang belum di-assign mini_pop_id.
        $popCode = trim((string) ($customer->pop?->pop_code ?? ''));
        $cidPrefix = trim((string) ($customer->pop?->cid_prefix ?? $this->cid_prefix ?? ''));

        if ($popCode !== '' && $cidPrefix !== '' && str_starts_with($popCode, $cidPrefix)) {
            $miniSegment = substr($popCode, strlen($cidPrefix));
            $miniSegment = preg_replace('/[^A-Z0-9]/i', '', $miniSegment) ?: '';

            if ($miniSegment !== '') {
                return $miniSegment;
            }
        }

        // 3. Fallback terakhir: olt_number free-text dari laporan teknis instalasi.
        if (! empty($fallback)) {
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
        if (! $this->cid_prefix) {
            throw new LogicException('POP cid_prefix belum dikonfigurasi.');
        }

        $nextNumber = DB::transaction(function (): int {
            $sequence = PopSequence::query()
                ->where('pop_id', $this->id)
                ->where('sequence_type', PopSequence::TYPE_CID)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
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
