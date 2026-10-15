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
        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'Cuenta eliminada.']);
    }
}
