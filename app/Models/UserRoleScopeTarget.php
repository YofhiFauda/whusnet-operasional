<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserRoleScopeTarget extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = ['user_role_scope_id', 'pop_id'];

    protected $fillable = [
        'user_role_scope_id',
        'pop_id',
    ];

    /**
     * Set the keys for a save update query.
     */
    protected function setKeysForSaveQuery($query)
    {
        $query->where('user_role_scope_id', $this->getAttribute('user_role_scope_id'))
            ->where('pop_id', $this->getAttribute('pop_id'));

        return $query;
    }

    public function userRoleScope(): BelongsTo
    {
        return $this->belongsTo(UserRoleScope::class);
    }

    public function pop(): BelongsTo
    {
        return $this->belongsTo(Pop::class);
    }
}
