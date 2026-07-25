<?php

namespace App\Http\Controllers;

use App\Enums\ScopeType;
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
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $allowedPopIds = app(EffectiveAccessService::class)->getAllowedPopIds($user);
        $scopeType = app(EffectiveAccessService::class)->getScopeType($user);

        $query = DatabaseNotification::query();

        // POP Scope Enforcement
        if ($scopeType !== ScopeType::ALL_POP) {
            // Users with task.view.all permission (e.g. FOP, NOC, CS, Admin) can see all notifications in their POP scope
            if ($user->hasPermission('task.view.all')) {
                $query->whereHasMorph('notifiable', [User::class], function ($q) use ($allowedPopIds) {
                    $q->where(function ($sub) use ($allowedPopIds) {
                        $sub->whereHas('roleScopes', fn ($rs) => $rs->where('scope_type', ScopeType::ALL_POP->value))
                            ->orWhereHas('roleScopes', fn ($rs) => $rs->whereIn('scope_type', [ScopeType::SELECTED_POP->value, ScopeType::POP_TREE->value])
                                ->whereHas('targets', fn ($t) => $t->whereIn('pop_id', $allowedPopIds))
                            );
                    });
                });
            } else {
                // Otherwise, normal users (e.g. Technician) only see their own notifications
                $query->where('notifiable_type', User::class)
                    ->where('notifiable_id', $user->id);
            }
        } else {
            // For Owner/Admin Pusat (all_pop):
            // If they don't have task.view.all, restrict to their own
            if (! $user->hasPermission('task.view.all')) {
                $query->where('notifiable_type', User::class)
                    ->where('notifiable_id', $user->id);
            }
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
        if ($scopeType !== ScopeType::ALL_POP) {
            $usersQuery->where(function ($sub) use ($allowedPopIds) {
                $sub->whereHas('roleScopes', fn ($rs) => $rs->where('scope_type', ScopeType::ALL_POP->value))
                    ->orWhereHas('roleScopes', fn ($rs) => $rs->whereIn('scope_type', [ScopeType::SELECTED_POP->value, ScopeType::POP_TREE->value])
                        ->whereHas('targets', fn ($t) => $t->whereIn('pop_id', $allowedPopIds))
                    );
            });
        }
        $filterUsers = $usersQuery->orderBy('name')->get();

        return view('notifications.index', compact('notifications', 'filterUsers'));
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->where('id', $id)->first();
        if ($notification) {
            $notification->markAsRead();
        }

        return response()->json(['success' => true]);
    }

    /**
     * Mark a specific notification as unread.
     */
    public function markAsUnread(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->where('id', $id)->first();
        if ($notification) {
            $notification->update(['read_at' => null]);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['success' => true]);
    }
}
