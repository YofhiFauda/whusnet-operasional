<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\RecordsAuditLogs;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'phone', 'status', 'role_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, RecordsAuditLogs;

    protected string $auditModule = 'User Management';

    protected array $auditEvents = ['deleted'];

    protected array $auditHidden = [
        'password',
        'remember_token',
        'email_verified_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the role associated with the user.
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Check if the user has a specific permission.
     */
    public function hasPermission(string $permission): bool
    {
        if (!$this->role) {
            return false;
        }

        if ($this->hasFullAccess()) {
            return true;
        }

        return $this->role->permissions()->where('name', $permission)->exists();
    }

    /**
     * Check whether the user should be treated as a full-access admin.
     */
    public function hasFullAccess(): bool
    {
        return (bool) $this->role?->isFullAccessRole();
    }

    /**
     * Check whether the user is a technician role.
     */
    public function isTechnician(): bool
    {
        return (bool) $this->role?->isTechnicianRole();
    }

    /**
     * Get the POPs assigned to the user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<Pop, $this>
     */
    public function pops()
    {
        return $this->belongsToMany(Pop::class, 'user_pops');
    }
}
