<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\LegacyAuthSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LegacyAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_migrated_user_can_login_with_legacy_password_algorithm(): void
    {
        $this->seed(LegacyAuthSeeder::class);

        $this->post(route('login.store'), [
            'username' => 'testsamiti@gmail.com',
            'password' => 'Test@1234',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs(User::findOrFail(1034));
    }

    public function test_invalid_password_is_rejected_and_counts_failed_attempt(): void
    {
        $this->seed(LegacyAuthSeeder::class);

        $this->post(route('login.store'), [
            'username' => 'testsamiti@gmail.com',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('username');

        $this->assertGuest();
        $this->assertSame(1, User::findOrFail(1034)->fail_attempt);
    }

    public function test_disabled_user_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'disabled@example.test',
            'password' => password_hash(hash('sha512', 'Disabled@123', true), PASSWORD_BCRYPT),
            'status' => '0',
            'user_type' => 1,
        ]);

        $this->post(route('login.store'), [
            'username' => 'disabled@example.test',
            'password' => 'Disabled@123',
        ])->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_unauthorized_role_cannot_access_master_management(): void
    {
        $this->seed(LegacyAuthSeeder::class);

        $this->post(route('login.store'), [
            'username' => 'testsamiti@gmail.com',
            'password' => 'Test@1234',
        ])->assertRedirect(route('dashboard'));

        $this->get(route('masters.index', 'courses'))->assertForbidden();
    }

    public function test_menu_visibility_follows_legacy_permissions(): void
    {
        $this->seed(LegacyAuthSeeder::class);

        $this->post(route('login.store'), [
            'username' => 'testcircle@gmail.com',
            'password' => 'Test@123',
        ]);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Reports')
            ->assertSee('Batches')
            ->assertDontSee('Master Management');
    }

    public function test_logout_clears_authenticated_session(): void
    {
        $this->seed(LegacyAuthSeeder::class);

        $this->post(route('login.store'), [
            'username' => 'testsamiti@gmail.com',
            'password' => 'Test@1234',
        ]);

        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
