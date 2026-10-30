<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->ownedOrganizations()->exists()) {
            throw ValidationException::withMessages([
                'account' => [
                    'Traspasa o elimina tus organizaciones antes de borrar la cuenta.',
                ],
            ]);
        }

        $user->organizations()->detach();

        // Los vehículos personales se van con la cuenta (soft delete, como el resto del dominio).
        $user->vehicles()->delete();

        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'Cuenta eliminada.']);
    }
}
