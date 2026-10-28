<?php

namespace App\Http\Controllers\Api;

use App\Enums\NotificationCategory;
use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Services\NotificationPreferences;
use App\Services\Notifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\Rule;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()
            ->notifications()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20);

        return NotificationResource::collection($notifications)->response();
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'data' => ['count' => $request->user()->unreadNotifications()->count()],
        ]);
    }

    public function markRead(Request $request, string $notification): NotificationResource
    {
        $record = $this->findOwned($request, $notification);

        if ($record->read_at === null) {
            $record->markAsRead();
        }

        return new NotificationResource($record->fresh());
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['message' => 'Todos los avisos quedaron como leídos.']);
    }

    public function destroy(Request $request, string $notification): JsonResponse
    {
        $this->findOwned($request, $notification)->delete();

        return response()->json(['message' => 'Aviso eliminado.']);
    }

    public function preferences(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'preferences' => app(NotificationPreferences::class)->for($request->user()),
                'push_configured' => $this->pushConfigured(),
            ],
        ]);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'preferences' => 'required|array|min:1',
            'preferences.*.category' => ['required', Rule::enum(NotificationCategory::class)],
            'preferences.*.in_app' => 'required|boolean',
            'preferences.*.email' => 'required|boolean',
            'preferences.*.push' => 'required|boolean',
        ]);

        app(NotificationPreferences::class)->update($request->user(), $validated['preferences']);

        return response()->json([
            'data' => [
                'preferences' => app(NotificationPreferences::class)->for($request->user()),
                'push_configured' => $this->pushConfigured(),
            ],
        ]);
    }

    public function publicKey(): JsonResponse
    {
        return response()->json([
            'data' => [
                'public_key' => $this->pushConfigured() ? Config::string('webpush.vapid.public_key') : null,
            ],
        ]);
    }

    public function storeSubscription(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => 'required|string|max:1024|url',
            'keys.p256dh' => 'required|string|max:255',
            'keys.auth' => 'required|string|max:255',
            'content_encoding' => 'nullable|in:aes128gcm,aesgcm',
        ]);

        $subscription = $request->user()->updatePushSubscription(
            $validated['endpoint'],
            $validated['keys']['p256dh'],
            $validated['keys']['auth'],
            $validated['content_encoding'] ?? 'aes128gcm',
        );

        return response()->json([
            'message' => 'Push activado en este dispositivo.',
            'data' => [
                'id' => $subscription->getKey(),
                'endpoint' => $subscription->endpoint,
                'content_encoding' => $subscription->content_encoding?->value,
            ],
        ], 201);
    }

    public function destroySubscription(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => 'required|string|max:1024',
        ]);

        $request->user()->deletePushSubscription($validated['endpoint']);

        return response()->json(['message' => 'Push desactivado en este dispositivo.']);
    }

    public function sendTest(Request $request): JsonResponse
    {
        $notification = app(Notifier::class)->send(
            $request->user(),
            NotificationCategory::System,
            'Notificación de prueba',
            'Si ves este mensaje, los avisos de CarCheck funcionan.',
            [],
            '/notificaciones',
        );

        return response()->json([
            'message' => 'Notificación de prueba enviada.',
            'data' => $notification === null
                ? null
                : (new NotificationResource($notification))->resolve($request),
        ]);
    }

    private function findOwned(Request $request, string $notification): DatabaseNotification
    {
        // 404 y no 403: no se revela la existencia de avisos ajenos.
        return $request->user()
            ->notifications()
            ->whereKey($notification)
            ->firstOrFail();
    }

    private function pushConfigured(): bool
    {
        return filled(config('webpush.vapid.public_key'))
            && filled(config('webpush.vapid.private_key'));
    }
}
