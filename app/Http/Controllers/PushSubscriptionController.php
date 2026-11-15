<?php

namespace App\Http\Controllers;

use App\Notifications\TestWebPushNotification;
use App\Support\DeviceBrandDetector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function vapidPublicKey(): JsonResponse
    {
        return response()->json([
            'publicKey' => config('webpush.vapid.public_key'),
        ]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'max:1024'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
            'contentEncoding' => ['nullable', 'string'],
        ]);

        $subscription = $request->user()->updatePushSubscription(
            endpoint: $validated['endpoint'],
            key: $validated['keys']['p256dh'],
            token: $validated['keys']['auth'],
            contentEncoding: $validated['contentEncoding'] ?? null,
        );

        // ADHOC-288 Tahap 8 — dasar dashboard delivery rate per merek/OS.
        $subscription->forceFill([
            'device_name' => DeviceBrandDetector::detect($request->userAgent()),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 512),
        ])->save();

        return response()->json(['message' => 'Subscribed.']);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'max:1024'],
        ]);

        $request->user()->deletePushSubscription($validated['endpoint']);

        return response()->json(['message' => 'Unsubscribed.']);
    }

    public function subscriptions(Request $request): JsonResponse
    {
        return response()->json([
            'subscriptions' => $request->user()->pushSubscriptions()
                ->get(['id', 'endpoint', 'created_at']),
        ]);
    }

    // ADHOC-288 Tahap 8 — tombol "Kirim notifikasi uji" di halaman Profil.
    // Reuse TestWebPushNotification yang sebelumnya cuma dipicu artisan
    // (webpush:test), selalu ke notifiable yang login sendiri — tidak ada
    // cara kirim test push ke user lain lewat endpoint ini.
    public function sendTest(Request $request): JsonResponse
    {
        if ($request->user()->pushSubscriptions()->doesntExist()) {
            return response()->json([
                'message' => 'Belum ada perangkat aktif. Aktifkan notifikasi dulu.',
            ], 422);
        }

        $request->user()->notify(new TestWebPushNotification);

        return response()->json(['message' => 'Notifikasi uji dikirim.']);
    }
}
