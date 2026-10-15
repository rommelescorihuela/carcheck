<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\OrganizationInvitation;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvitationController extends Controller
{
    public function store(Request $request, Organization $organization): JsonResponse
    {
        $this->authorize('manageMembers', $organization);

        $validated = $request->validate([
            'email' => 'required|string|email|max:255',
            'role' => 'required|in:admin,member',
        ]);

        $existing = User::where('email', $validated['email'])->first();
        if ($existing && $organization->hasMember($existing)) {
            throw ValidationException::withMessages([
                'email' => ['Ese correo ya es miembro de la organización.'],
            ]);
        }

        $plainToken = Str::random(40);

        // Reemplaza cualquier invitación pendiente para el mismo correo.
        $organization->invitations()
            ->where('email', $validated['email'])
            ->whereNull('accepted_at')
            ->delete();

        $invitation = $organization->invitations()->create([
            'email' => $validated['email'],
            'role' => $validated['role'],
            'token' => hash('sha256', $plainToken),
            'expires_at' => now()->addDays(7),
        ]);

        $acceptUrl = rtrim(config('app.frontend_url'), '/').'/invitaciones/'.$plainToken;

        rescue(fn () => Mail::to($invitation->email)->queue(
            new OrganizationInvitation($invitation, $acceptUrl)
        ));

        return response()->json(['message' => 'Invitación enviada.'], 201);
    }

    public function accept(Request $request, string $token): JsonResponse
    {
        $invitation = Invitation::where('token', hash('sha256', $token))->firstOrFail();

        if ($invitation->isAccepted() || $invitation->isExpired()) {
            throw ValidationException::withMessages([
                'token' => ['La invitación ya no es válida.'],
            ]);
        }

        /** @var User $user */
        $user = $request->user();

        if (strcasecmp($invitation->email, $user->email) !== 0) {
            abort(403, 'La invitación es para otro correo.');
        }

        $organization = $invitation->organization;

        if (! $organization->hasMember($user)) {
            $organization->members()->attach($user->id, [
                'role' => $invitation->role,
                'joined_at' => now(),
            ]);
        }

        $invitation->update(['accepted_at' => now()]);

        return response()->json(['organization' => $organization]);
    }
}
