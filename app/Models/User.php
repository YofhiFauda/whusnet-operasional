<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserStatus;
use App\Models\Concerns\RecordsAuditLogs;
use App\Services\EffectiveAccessService;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;

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
            'status' => UserStatus::class,
        ];
    }

    /**
     * Get the role associated with the user.
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function userPops(): HasMany
    {
        return $this->hasMany(UserPop::class);
    }

    public function roleScopes(): HasMany
    {
        return $this->hasMany(UserRoleScope::class);
    }

    /**
     * Custody barang QUANTITY/BATCH yang lagi dipegang user ini sebagai
     * teknisi (ADHOC-54). Cuma relasi query — FIFO consumption lintas baris
     * itu tugas `InventoryService::consumeFromCustody()` (Fase Service).
     */
    public function technicianCustodies(): HasMany
    {
        return $this->hasMany(TechnicianCustody::class, 'technician_id');
    }

    /**
     * Check if the user has a specific role by code.
     */
    public function hasRole(string|array $roles): bool
    {
        $roleCode = $this->role?->code;
        if (! $roleCode) {
            return false;
        }

        $rolesArray = is_array($roles) ? $roles : func_get_args();

        return in_array($roleCode, $rolesArray, true);
    }

    /**
     * Check if the user has a specific permission.
     */
    public function hasPermission(string $permission): bool
    {
        return app(EffectiveAccessService::class)->userCan($this, $permission);
    }

    /**
     * Check whether the user should be treated as a full-access admin.
     */
    public function hasFullAccess(): bool
    {
        return $this->hasPermission('*');
    }

    /**
     * Check whether the user is a technician role.
     */
    public function isTechnician(): bool
    {
        return $this->hasRole('teknisi');
    }

    /**
     * Get the POPs assigned to the user.
     *
     * @return BelongsToMany<Pop, $this>
     */
    public function pops()
    {
        return $this->belongsToMany(Pop::class, 'user_pops');
    }

    /**
     * Pelanggan yang rutin ditagih user ini (kalau dia kolektor) — rute
     * permanen `customers.collector_id`, bukan snapshot per transaksi.
     *
     * @return HasMany<Customer, $this>
     */
    public function assignedCustomers()
    {
        return $this->hasMany(Customer::class, 'collector_id');
    }

    /**
     * Unread notification count buat badge lonceng navbar — di-cache TTL
     * pendek karena query ini jalan di SETIAP page load (dropdown komponen
     * dipasang di layout utama, docs/plan/analisa-status-implementasi-
     * notifikasi.md §4 no. 1). TTL sengaja pendek (bukan di-invalidate manual
     * tiap ada notif baru masuk) — badge di klien udah nambah realtime lewat
     * Echo begitu notif baru nyampe (notification-dropdown.blade.php), jadi
     * angka cache di sini cuma dipakai buat initial render pas page load,
     * beda user/tab, gak perlu presisi ke-detik.
     */
    public function unreadNotificationsCountCached(): int
    {
        return Cache::remember(
            "user.{$this->id}.unread_notifications_count",
            20,
            fn () => $this->unreadNotifications()->count()
        );
    }

    /**
     * Dipanggil dari NotificationController::markAsRead/markAsUnread/
     * markAllAsRead — mark-read HARUS langsung kelihatan turun di badge tab
     * yang sama tanpa nunggu TTL 20 detik habis.
     */
    public function clearUnreadNotificationsCountCache(): void
    {
        Cache::forget("user.{$this->id}.unread_notifications_count");
    }
}
