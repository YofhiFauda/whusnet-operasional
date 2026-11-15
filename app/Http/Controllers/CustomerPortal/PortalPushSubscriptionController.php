<?php

namespace App\Http\Controllers\CustomerPortal;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Notifications\TestWebPushNotification;
use App\Support\DeviceBrandDetector;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `GET /push/vapid-key`, `POST /push/subscribe`, `POST /push/unsubscribe`,
 * `GET /push/subscriptions` (docs/api/api-portal-pelanggan/, Tahap 7 Web Push
 * Fase 3). Sama persis pola `PushSubscriptionController` (staf), bedanya
 * identitas pelanggan diambil dari `portal_customer_id` (hasil
 * `EnsurePortalCustomerToken`) — TIDAK PERNAH dari request, sama aturan
 * dengan `PortalMeController` (business-logic.md §Kepemilikan data).
 */
#[Group('Portal Pelanggan', 'Endpoint subscription Web Push bagi aplikasi Portal Pelanggan.')]
class PortalPushSubscriptionController extends Controller
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

        $customer = Customer::findOrFail($request->attributes->get('portal_customer_id'));

        $subscription = $customer->updatePushSubscription(
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

        $customer = Customer::findOrFail($request->attributes->get('portal_customer_id'));

        $customer->deletePushSubscription($validated['endpoint']);

        return response()->json(['message' => 'Unsubscribed.']);
    }

    public function subscriptions(Request $request): JsonResponse
    {
        $customer = Customer::findOrFail($request->attributes->get('portal_customer_id'));

        return response()->json([
            'subscriptions' => $customer->pushSubscriptions()
                ->get(['id', 'endpoint', 'created_at']),
        ]);
    }

    // Tahap 8 — tombol "Kirim notifikasi uji" di Portal Next.js. Reuse
    // TestWebPushNotification yang sama dgn staf (PushSubscriptionController).
    public function sendTest(Request $request): JsonResponse
    {
        $customer = Customer::findOrFail($request->attributes->get('portal_customer_id'));

        if ($customer->pushSubscriptions()->doesntExist()) {
            return response()->json([
                'message' => 'Belum ada perangkat aktif. Aktifkan notifikasi dulu.',
            ], 422);
        }

        $customer->notify(new TestWebPushNotification);

        return response()->json(['message' => 'Notifikasi uji dikirim.']);
    }
}
