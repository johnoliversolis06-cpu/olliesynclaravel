<?php

namespace App\Http\Controllers;

use App\Models\UserNotificationPreference;
use Illuminate\Http\Request;

/**
 * NotificationController
 * ──────────────────────────────────────────────────────────────
 * Handles saving and removing the browser's Web Push subscription.
 *
 * Flow:
 *  1. User visits a page → AuthenticatedLayout.vue registers the service worker
 *  2. User clicks "Turn On" in Settings → browser asks for permission
 *  3. If granted, Vue calls POST /notifications/subscribe with the subscription JSON
 *  4. This controller saves it to user_notification_preferences.push_subscription
 *  5. Now the cron command (CheckScheduledNotifications) can send push messages
 *     to this user's browser even when the tab is closed.
 *
 * Routes (add to routes/web.php):
 *   Route::post('/notifications/subscribe', [NotificationController::class, 'subscribe'])->name('notifications.subscribe');
 *   Route::delete('/notifications/unsubscribe', [NotificationController::class, 'unsubscribe'])->name('notifications.unsubscribe');
 *   Route::get('/notifications/vapid-key', [NotificationController::class, 'vapidPublicKey'])->name('notifications.vapidKey');
 */
class NotificationController extends Controller
{
    /**
     * Save the browser push subscription.
     * Called from AuthenticatedLayout.vue after permission is granted.
     *
     * Request body (from browser's PushSubscription.toJSON()):
     * {
     *   "endpoint": "https://fcm.googleapis.com/fcm/send/...",
     *   "expirationTime": null,
     *   "keys": {
     *     "p256dh": "...",
     *     "auth": "..."
     *   }
     * }
     */
    public function subscribe(Request $request)
    {
        $validated = $request->validate([
            'endpoint'         => 'required|string|url',
            'keys'             => 'required|array',
            'keys.p256dh'      => 'required|string',
            'keys.auth'        => 'required|string',
        ]);

        // Store the full subscription JSON
        $subscriptionJson = json_encode([
            'endpoint' => $validated['endpoint'],
            'keys'     => $validated['keys'],
        ]);

        // Create or update the user's notification preferences
        UserNotificationPreference::updateOrCreate(
            ['user_id' => auth()->id()],
            ['push_subscription' => $subscriptionJson]
        );

        return response()->json(['status' => 'subscribed']);
    }

    /**
     * Remove the push subscription (user turned off notifications).
     */
    public function unsubscribe()
    {
        UserNotificationPreference::where('user_id', auth()->id())
            ->update(['push_subscription' => null]);

        return response()->json(['status' => 'unsubscribed']);
    }

    /**
     * Return the VAPID public key so the browser can subscribe.
     * This key is public — safe to expose to the client.
     */
    public function vapidPublicKey()
    {
        return response()->json([
            'vapidPublicKey' => config('services.vapid.public_key', ''),
        ]);
    }
}