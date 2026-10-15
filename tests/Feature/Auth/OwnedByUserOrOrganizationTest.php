<?php

namespace Tests\Feature\Auth;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Support\OwnedRecord;
use Tests\TestCase;

class OwnedByUserOrOrganizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('owned_records', function (Blueprint $table) {
            $table->id();
            $table->string('owner_type');
            $table->unsignedBigInteger('owner_id');
        });
    }

    public function test_scope_returns_personal_and_organization_records(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->members()->attach($user, ['role' => 'member', 'joined_at' => now()]);

        $other = User::factory()->create();

        $personal = OwnedRecord::create([
            'owner_type' => $user->getMorphClass(),
            'owner_id' => $user->id,
        ]);
        $shared = OwnedRecord::create([
            'owner_type' => $organization->getMorphClass(),
            'owner_id' => $organization->id,
        ]);
        $foreign = OwnedRecord::create([
            'owner_type' => $other->getMorphClass(),
            'owner_id' => $other->id,
        ]);

        $ids = OwnedRecord::accessibleBy($user)->pluck('id');

        $this->assertTrue($ids->contains($personal->id));
        $this->assertTrue($ids->contains($shared->id));
        $this->assertFalse($ids->contains($foreign->id));
    }

    public function test_scope_can_be_limited_to_a_single_organization(): void
    {
        $user = User::factory()->create();
        $first = Organization::factory()->create();
        $second = Organization::factory()->create();
        $first->members()->attach($user, ['role' => 'member', 'joined_at' => now()]);
        $second->members()->attach($user, ['role' => 'member', 'joined_at' => now()]);

        $inFirst = OwnedRecord::create([
            'owner_type' => $first->getMorphClass(),
            'owner_id' => $first->id,
        ]);
        $inSecond = OwnedRecord::create([
            'owner_type' => $second->getMorphClass(),
            'owner_id' => $second->id,
        ]);

        $ids = OwnedRecord::accessibleBy($user, $first->id)->pluck('id');

        $this->assertTrue($ids->contains($inFirst->id));
        $this->assertFalse($ids->contains($inSecond->id));
    }

    public function test_is_accessible_by_matches_scope(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->members()->attach($user, ['role' => 'member', 'joined_at' => now()]);

        $personal = OwnedRecord::create([
            'owner_type' => $user->getMorphClass(),
            'owner_id' => $user->id,
        ]);
        $shared = OwnedRecord::create([
            'owner_type' => $organization->getMorphClass(),
            'owner_id' => $organization->id,
        ]);
        $foreignUser = User::factory()->create();
        $foreign = OwnedRecord::create([
            'owner_type' => $foreignUser->getMorphClass(),
            'owner_id' => $foreignUser->id,
        ]);

        $this->assertTrue($personal->isAccessibleBy($user));
        $this->assertTrue($shared->isAccessibleBy($user));
        $this->assertFalse($foreign->isAccessibleBy($user));
    }
}
