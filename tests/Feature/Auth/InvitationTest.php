<?php

namespace Tests\Feature\Auth;

use App\Mail\OrganizationInvitation;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    private function invitationFor(Organization $organization, string $email, string $plainToken, array $overrides = []): Invitation
    {
        return Invitation::create(array_merge([
            'organization_id' => $organization->id,
            'email' => $email,
            'role' => 'member',
            'token' => hash('sha256', $plainToken),
            'expires_at' => now()->addDays(7),
        ], $overrides));
    }

    public function test_owner_can_invite_and_email_is_queued(): void
    {
        Mail::fake();

        $organization = Organization::factory()->create();
        Sanctum::actingAs($organization->owner);

        $this->postJson("/api/organizations/{$organization->id}/invitations", [
            'email' => 'nuevo@example.com',
            'role' => 'member',
        ])->assertCreated();

        $invitation = Invitation::firstWhere('email', 'nuevo@example.com');

        $this->assertNotNull($invitation);
        $this->assertSame(64, strlen($invitation->token));
        Mail::assertQueued(OrganizationInvitation::class);
    }

    public function test_plain_member_cannot_invite(): void
    {
        $organization = Organization::factory()->create();
        $member = User::factory()->create();
        $organization->members()->attach($member, ['role' => 'member', 'joined_at' => now()]);

        Sanctum::actingAs($member);

        $this->postJson("/api/organizations/{$organization->id}/invitations", [
            'email' => 'nuevo@example.com',
            'role' => 'member',
        ])->assertForbidden();
    }

    public function test_cannot_invite_existing_member(): void
    {
        $organization = Organization::factory()->create();
        $member = User::factory()->create();
        $organization->members()->attach($member, ['role' => 'member', 'joined_at' => now()]);

        Sanctum::actingAs($organization->owner);

        $this->postJson("/api/organizations/{$organization->id}/invitations", [
            'email' => $member->email,
            'role' => 'member',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_user_can_accept_invitation_and_becomes_member(): void
    {
        $organization = Organization::factory()->create();
        $invitee = User::factory()->create(['email' => 'invitado@example.com']);
        $this->invitationFor($organization, 'invitado@example.com', 'plain-token');

        Sanctum::actingAs($invitee);

        $this->postJson('/api/invitations/plain-token/accept')
            ->assertOk()
            ->assertJsonPath('organization.id', $organization->id);

        $this->assertTrue($organization->fresh()->hasMember($invitee));
        $this->assertSame('member', $organization->fresh()->roleOf($invitee));
        $this->assertNotNull(Invitation::firstWhere('email', 'invitado@example.com')->accepted_at);
    }

    public function test_expired_invitation_cannot_be_accepted(): void
    {
        $organization = Organization::factory()->create();
        $invitee = User::factory()->create(['email' => 'invitado@example.com']);
        $this->invitationFor($organization, 'invitado@example.com', 'plain-token', [
            'expires_at' => now()->subDay(),
        ]);

        Sanctum::actingAs($invitee);

        $this->postJson('/api/invitations/plain-token/accept')->assertStatus(422);
        $this->assertFalse($organization->fresh()->hasMember($invitee));
    }

    public function test_invitation_cannot_be_reused(): void
    {
        $organization = Organization::factory()->create();
        $invitee = User::factory()->create(['email' => 'invitado@example.com']);
        $this->invitationFor($organization, 'invitado@example.com', 'plain-token');

        Sanctum::actingAs($invitee);

        $this->postJson('/api/invitations/plain-token/accept')->assertOk();
        $this->postJson('/api/invitations/plain-token/accept')->assertStatus(422);
    }

    public function test_invitation_for_another_email_is_forbidden(): void
    {
        $organization = Organization::factory()->create();
        $outsider = User::factory()->create(['email' => 'otro@example.com']);
        $this->invitationFor($organization, 'invitado@example.com', 'plain-token');

        Sanctum::actingAs($outsider);

        $this->postJson('/api/invitations/plain-token/accept')->assertForbidden();
    }

    public function test_unknown_token_returns_not_found(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/invitations/does-not-exist/accept')->assertNotFound();
    }
}
