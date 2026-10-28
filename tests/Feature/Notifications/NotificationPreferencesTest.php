<?php

namespace Tests\Feature\Notifications;

use App\Enums\NotificationCategory;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\NotificationPreferences;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_without_rows_has_every_channel_enabled(): void
    {
        $preferences = app(NotificationPreferences::class)->for(User::factory()->create());

        $this->assertCount(5, $preferences);
        $this->assertSame('system', $preferences[0]['category']);
        $this->assertSame('Sistema', $preferences[0]['category_label']);

        foreach ($preferences as $preference) {
            $this->assertTrue($preference['in_app']);
            $this->assertTrue($preference['email']);
            $this->assertTrue($preference['push']);
        }
    }

    public function test_stored_row_overrides_only_its_category(): void
    {
        $user = User::factory()->create();
        NotificationPreference::factory()->for($user)->forCategory(NotificationCategory::Billing)->create([
            'push' => false,
        ]);

        $preferences = collect(app(NotificationPreferences::class)->for($user))
            ->keyBy('category');

        $this->assertFalse($preferences['billing']['push']);
        $this->assertTrue($preferences['billing']['in_app']);
        $this->assertTrue($preferences['documents']['push']);
    }

    public function test_allows_reads_the_channel_of_the_category(): void
    {
        $user = User::factory()->create();
        NotificationPreference::factory()->for($user)->forCategory(NotificationCategory::Reminders)->create([
            'email' => false,
        ]);

        $service = app(NotificationPreferences::class);

        $this->assertFalse($service->allows($user, NotificationCategory::Reminders, 'email'));
        $this->assertTrue($service->allows($user, NotificationCategory::Reminders, 'push'));
        $this->assertTrue($service->allows($user, NotificationCategory::Billing, 'email'));
    }

    public function test_update_upserts_and_keeps_untouched_categories(): void
    {
        $user = User::factory()->create();
        $service = app(NotificationPreferences::class);

        $service->update($user, [
            ['category' => 'billing', 'in_app' => true, 'email' => false, 'push' => false],
        ]);
        $service->update($user, [
            ['category' => 'billing', 'in_app' => false, 'email' => false, 'push' => false],
        ]);

        $this->assertSame(1, $user->notificationPreferences()->count());
        $this->assertFalse($service->allows($user, NotificationCategory::Billing, 'in_app'));
        $this->assertTrue($service->allows($user, NotificationCategory::System, 'in_app'));
    }
}
