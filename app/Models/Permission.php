<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'module', 'description'])]
class Permission extends Model
{
    /**
     * Get the roles associated with the permission.
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_permissions');
    }
}
