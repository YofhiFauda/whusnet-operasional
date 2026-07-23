<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Identitas pelanggan permanen (rancangan-fase4-persons.md §3).
 *
 * Menjawab "SIAPA orangnya" — terpisah dari `customer_code`/RQ (kontrak mana)
 * dan CID (sambungan fisik mana). Satu person → banyak `customers` (daftar
 * ulang, pindah kontrak). UUIDv7 di-generate otomatis saat create; jangan diisi
 * manual kecuali sengaja (mis. import yang membawa uuid dari instalasi lain).
 */
#[Fillable([
    'uuid',
    'legacy_key',
    'merged_into',
])]
class Person extends Model
{
    protected static function booted(): void
    {
        // UUIDv7 (time-ordered) supaya punya lokalitas indeks di InnoDB.
        // Laravel 13 native: Str::uuid7(). Di-set saat creating kalau kosong.
        static::creating(function (Person $person) {
            if (empty($person->uuid)) {
                $person->uuid = (string) Str::uuid7();
            }
        });
    }

    /**
     * Semua baris pelanggan (kontrak) milik orang ini.
     *
     * @return HasMany<Customer, $this>
     */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /**
     * Person tujuan kalau baris ini sudah di-merge ke orang lain (gel.2).
     * Null = person ini masih berdiri sendiri.
     *
     * @return BelongsTo<Person, $this>
     */
    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'merged_into');
    }
}
