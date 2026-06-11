<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        $user = $user ?? auth()->user();

        if (!$user) {
            return $query->whereRaw('1 = 0'); // return empty if no user
        }

        if (in_array(optional($user->role)->name, ['Owner', 'Admin Pusat'])) {
            return $query; // return all
        }

        return $query->whereHas('users', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        });
    }

    public function generateRegistrationNumber(): string
    {
        return $this->generateIdentifier(PopSequence::TYPE_REGISTRATION);
    }

    public function generateCid(): string
    {
        return $this->generateIdentifier(PopSequence::TYPE_CID);
    }

    private function generateIdentifier(string $type): string
    {
        $prefix = $type === PopSequence::TYPE_REGISTRATION
            ? $this->registration_prefix
            : $this->cid_prefix;

        if (!$prefix || !$this->pop_code) {
            throw new LogicException('POP identifier settings are incomplete.');
        }

        $nextNumber = DB::transaction(function () use ($type): int {
            $sequence = PopSequence::query()
                ->where('pop_id', $this->id)
                ->where('sequence_type', $type)
                ->lockForUpdate()
                ->first();

            if (!$sequence) {
                $sequence = PopSequence::create([
                    'pop_id' => $this->id,
                    'sequence_type' => $type,
                    'current_number' => 0,
                ]);
            }

            $sequence->current_number++;
            $sequence->save();

            return $sequence->current_number;
        });

        return sprintf('%s-%s-%06d', $prefix, $this->pop_code, $nextNumber);
    }
}
