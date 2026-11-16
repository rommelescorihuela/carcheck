<?php

namespace App\Http\Controllers\Api;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'password' => 'required|string',
        ]);

        if (! Hash::check((string) $request->input('password'), $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['La contraseña no es correcta.'],
            ]);
        }

        if ($user->ownedOrganizations()->exists()) {
            throw ValidationException::withMessages([
                'account' => [
                    'Transfiere la propiedad o elimina tus organizaciones antes de borrar la cuenta.',
                ],
            ]);
        }

        $user->organizations()->detach();

        // La suscripción personal se conserva como historial, pero deja de estar activa.
        $user->subscriptions()->get()->each(function ($subscription): void {
            $subscription->update([
                'status' => SubscriptionStatus::Canceled,
                'canceled_at' => now(),
            ]);
        });

        // Los vehículos personales se van con la cuenta (soft delete, como el resto del dominio).
        $user->vehicles()->delete();

        $user->tokens()->delete();
        $user->pushSubscriptions()->delete();
        $user->notifications()->delete();

        // La columna `email` es única: sin anonimizarla, esa persona nunca podría
        // volver a registrarse con su correo.
        $user->forceFill([
            'name' => 'Cuenta eliminada',
            'email' => 'deleted-'.$user->id.'@users.invalid',
            'password' => Str::random(40),
            'remember_token' => null,
        ])->save();

        $user->delete();

        return response()->json(['message' => 'Cuenta eliminada.']);
    }
}
