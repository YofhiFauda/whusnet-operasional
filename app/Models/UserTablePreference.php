<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Preferensi tampilan tabel milik satu user. `columns` = daftar key kolom yang
 * TAMPIL, berurutan; kolom yang tidak ada di daftar berarti disembunyikan.
 */
class UserTablePreference extends Model
{
    protected $fillable = ['user_id', 'table_key', 'columns'];

    protected function casts(): array
    {
        return ['columns' => 'array'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
