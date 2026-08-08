<?php

namespace App\Http\Controllers;

use App\Enums\ScopeType;
use App\Events\NotificationsMarkedRead;
use App\Models\User;
use App\Services\EffectiveAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;

class NotificationController extends Controller
{
    /**
     * Display a listing of notifications with dynamic filters and POP scope filtering.
     *
     * Scope-nya CUMA 2 kasus nyata, bukan 4 kombinasi (versi lama nge-duplikasi
     * baris "batasi ke notif sendiri" di dua cabang `scopeType` yang beda —
     * gampang salah tempel kalau nambah modul consumer notifikasi baru, lihat
     * docs/plan/analisa-status-implementasi-notifikasi.md §4 no. 3):
     *   1. Punya `task.view.all` (FOP/NOC/CS/Admin) → lihat notif semua user
     *      di POP yang dia punya akses (kalau `ALL_POP`, gak perlu filter POP
     *      sama sekali — dia emang lihat semua).
     *   2. Gak punya → cuma lihat notifnya sendiri, titik. Scope POP-nya gak
     *      relevan lagi di titik ini karena dia udah dibatasi ke diri sendiri.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $access = app(EffectiveAccessService::class);
        $allowedPopIds = $access->getAllowedPopIds($user);
        $hasAllPopAccess = $access->hasAllPopAccess($user);

        $query = DatabaseNotification::with('notifiable');

        if ($user->hasPermission('task.view.all')) {
            if (! $hasAllPopAccess) {
                $query->whereHasMorph('notifiable', [User::class], fn ($q) => $this->scopedToAllowedPops($q, $allowedPopIds));
            }
        } else {
            $query->where('notifiable_type', User::class)->where('notifiable_id', $user->id);
        }

        // Apply Filters
        if ($request->filled('date')) {
            // created_at bertipe timestamp — rentang eksplisit, bukan whereDate()
            // yang membungkusnya jadi DATE(created_at) dan mematikan index.
            $tanggal = Carbon::parse($request->date);
            $query->whereBetween('created_at', [$tanggal->copy()->startOfDay(), $tanggal->copy()->endOfDay()]);
        }

        if ($request->filled('type')) {
            // Fase 5.2 — kolom nyata ter-index, bukan where('data->type') yang
            // full-scan + parse JSON per baris di atas kolom TEXT.
            $query->where('notification_type', $request->type);
        }

        if ($request->filled('user_id')) {
            $query->where('notifiable_id', $request->user_id);
        }

        $notifications = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        // Fetch users for dropdown filtering (scoped by POP)
        // `role` dipakai per baris di dropdown (notifications/index.blade.php:34) —
        // tanpa eager load ini satu query per user yang tampil di filter.
        $usersQuery = User::with('role:id,name');
        if (! $hasAllPopAccess) {
            $usersQuery->where(fn ($sub) => $this->scopedToAllowedPops($sub, $allowedPopIds));
        }
        $filterUsers = $usersQuery->orderBy('name')->get();

        return view('notifications.index', compact('notifications', 'filterUsers'));
    }

    /**
     * Fragmen where "user ini punya akses ke salah satu POP di $allowedPopIds"
     * — dipakai 2x di index() (filter notifiable & dropdown filter user),
     * disatukan biar gak menyimpang diam-diam kalau salah satu diedit doang.
     *
     * @param  array<int, int>  $allowedPopIds
     */
    private function scopedToAllowedPops($query, array $allowedPopIds)
    {
        return $query->whereHas('roleScopes', fn ($rs) => $rs->where('scope_type', ScopeType::ALL_POP->value))
            ->orWhereHas('roleScopes', fn ($rs) => $rs->whereIn('scope_type', [ScopeType::SELECTED_POP->value, ScopeType::POP_TREE->value])
                ->whereHas('targets', fn ($t) => $t->whereIn('pop_id', $allowedPopIds))
            );
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $notification = $user->notifications()->where('id', $id)->first();
        if ($notification) {
            $notification->markAsRead();
            $this->syncUnreadCountAcrossTabs($user);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Mark a specific notification as unread.
     */
    public function markAsUnread(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $notification = $user->notifications()->where('id', $id)->first();
        if ($notification) {
            $notification->update(['read_at' => null]);
            $this->syncUnreadCountAcrossTabs($user);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->unreadNotifications->markAsRead();
        $this->syncUnreadCountAcrossTabs($user);

        return response()->json(['success' => true]);
    }

    /**
     * Cache count di-invalidate + broadcast NotificationsMarkedRead — tab
     * lain (atau device lain) yang lagi buka dropdown lonceng user yang sama
     * ikut update tanpa nunggu refresh manual (docs/plan/analisa-status-
     * implementasi-notifikasi.md §4 no. 2).
     */
    private function syncUnreadCountAcrossTabs(User $user): void
    {
        $user->clearUnreadNotificationsCountCache();

        broadcast(new NotificationsMarkedRead($user->id, $user->unreadNotificationsCountCached()))->toOthers();
    }
}
