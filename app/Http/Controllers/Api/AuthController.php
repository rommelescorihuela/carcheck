<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\WelcomeEmail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'currency' => 'sometimes|string|size:3',
            'terms' => 'accepted',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'currency' => $validated['currency'] ?? 'USD',
            'terms_accepted_at' => now(),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        // El correo de bienvenida no debe tumbar el registro si el SMTP falla.
        rescue(fn () => Mail::to($user->email)->queue(new WelcomeEmail($user)));

        return response()->json([
            'user' => $user->refresh(),
            'access_token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales no son correctas.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada.']);
    }

    public function refreshToken(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->currentAccessToken()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * Solicita el enlace de restablecimiento. La respuesta es siempre la misma
     * para no revelar si el correo existe.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|string|email',
        ]);

        Password::sendResetLink($request->only('email'));

        return response()->json([
            'message' => 'Si el correo existe, te enviamos un enlace para restablecer la contraseña.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'email' => 'required|string|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::reset($validated, function (User $user, string $password): void {
            $user->forceFill(['password' => $password])->save();

            // Cerrar todas las sesiones abiertas tras cambiar la contraseña.
            $user->tokens()->delete();
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json(['message' => 'Contraseña restablecida. Ya puedes iniciar sesión.']);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => [
                'sometimes',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'currency' => 'sometimes|string|size:3',
        ]);

        if (isset($validated['currency'])) {
            $validated['currency'] = strtoupper($validated['currency']);
        }

        $user->update($validated);

        return response()->json(['user' => $user->refresh()]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if (! Hash::check((string) $request->input('current_password'), $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['La contraseña actual no es correcta.'],
            ]);
        }

        $user->forceFill(['password' => $request->input('password')])->save();

        // Se conserva el token de esta pestaña; se cierran las demás sesiones.
        $currentToken = $user->currentAccessToken();
        $currentId = $currentToken?->getKey();

        if ($currentId !== null) {
            $user->tokens()->whereKeyNot($currentId)->delete();
        } else {
            $user->tokens()->delete();
        }

        return response()->json(['message' => 'Contraseña actualizada.']);
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user()]);
    }
}
