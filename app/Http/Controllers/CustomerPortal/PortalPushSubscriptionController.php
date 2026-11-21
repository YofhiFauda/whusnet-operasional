<?php

namespace App\Http\Controllers\CustomerPortal;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Notifications\TestWebPushNotification;
use App\Support\DeviceBrandDetector;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response;
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
    /**
     * VAPID public key
     *
     * Public key buat `PushSubscription` browser/PWA. Tidak sensitif — boleh
     * diminta tanpa identitas pelanggan (cuma butuh `X-Portal-Client`).
     */
    #[Response(200, description: 'Public key VAPID aktif.', examples: [[
        'publicKey' => 'BIv1y6...example',
    ]])]
    public function vapidPublicKey(): JsonResponse
    {
        return response()->json([
            'publicKey' => config('webpush.vapid.public_key'),
        ]);
    }

    /**
     * Subscribe perangkat
     *
     * Simpan/perbarui `PushSubscription` perangkat pelanggan buat notif
     * Tagihan/Pembayaran.
     */
    #[BodyParameter('endpoint', description: 'Endpoint push service browser.', example: 'https://fcm.googleapis.com/fcm/send/abc123')]
    #[BodyParameter('keys.p256dh', description: 'Public key enkripsi dari `PushSubscription`.', example: 'BNc...example')]
    #[BodyParameter('keys.auth', description: 'Auth secret dari `PushSubscription`.', example: 'tBHI...example')]
    #[BodyParameter('contentEncoding', description: 'Content encoding opsional (`aes128gcm`/`aesgcm`).', example: 'aes128gcm')]
    #[Response(200, description: 'Subscription tersimpan.', examples: [['message' => 'Subscribed.']])]
    #[Response(401, description: 'Token pelanggan tidak ada/tidak valid/kedaluwarsa.')]
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

    /**
     * Unsubscribe perangkat
     *
     * Hapus `PushSubscription` perangkat ini — berhenti menerima notif.
     */
    #[BodyParameter('endpoint', description: 'Endpoint push service yang mau dihapus.', example: 'https://fcm.googleapis.com/fcm/send/abc123')]
    #[Response(200, description: 'Subscription dihapus (idempotent — tidak error kalau sudah tidak ada).', examples: [['message' => 'Unsubscribed.']])]
    #[Response(401, description: 'Token pelanggan tidak ada/tidak valid/kedaluwarsa.')]
    public function unsubscribe(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'max:1024'],
        ]);

        $customer = Customer::findOrFail($request->attributes->get('portal_customer_id'));

        $customer->deletePushSubscription($validated['endpoint']);

        return response()->json(['message' => 'Unsubscribed.']);
    }

    /**
     * Daftar subscription
     *
     * Semua perangkat yang sedang subscribe notif push buat pelanggan ini.
     */
    #[Response(200, description: 'Daftar subscription aktif (bisa kosong).', examples: [[
        'subscriptions' => [['id' => 1, 'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123', 'created_at' => '2026-11-01T08:00:00+07:00']],
    ]])]
    #[Response(401, description: 'Token pelanggan tidak ada/tidak valid/kedaluwarsa.')]
    public function subscriptions(Request $request): JsonResponse
    {
        $customer = Customer::findOrFail($request->attributes->get('portal_customer_id'));

        return response()->json([
            'subscriptions' => $customer->pushSubscriptions()
                ->get(['id', 'endpoint', 'created_at']),
        ]);
    }

    /**
     * Kirim notifikasi uji
     *
     * Tombol "Kirim notifikasi uji" di Portal Next.js — reuse
     * `TestWebPushNotification` yang sama dgn staf (`PushSubscriptionController`).
     */
    #[Response(200, description: 'Notifikasi uji dikirim ke semua perangkat aktif pelanggan.', examples: [['message' => 'Notifikasi uji dikirim.']])]
    #[Response(401, description: 'Token pelanggan tidak ada/tidak valid/kedaluwarsa.')]
    #[Response(422, description: 'Belum ada perangkat aktif — pelanggan belum subscribe push sama sekali.', examples: [['message' => 'Belum ada perangkat aktif. Aktifkan notifikasi dulu.']])]
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
