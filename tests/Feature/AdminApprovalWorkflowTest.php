<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_a_pending_user_without_an_authentication_token(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'StrongPass1!',
            'password_confirmation' => 'StrongPass1!',
        ]);

        $response->assertCreated()
            ->assertJsonMissingPath('access_token')
            ->assertJsonPath('user.is_approved', false);

        $this->assertDatabaseHas('users', [
            'email' => 'new@example.com',
            'is_approved' => false,
        ]);
    }

    public function test_valid_credentials_cannot_login_until_user_is_approved(): void
    {
        User::factory()->create([
            'email' => 'pending@example.com',
            'password' => 'StrongPass1!',
            'is_approved' => false,
        ]);

        $this->postJson('/api/v1/login', [
            'email' => 'pending@example.com',
            'password' => 'StrongPass1!',
        ])->assertForbidden()
            ->assertJsonPath('message', 'Your account is pending admin approval.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_approved_user_can_login(): void
    {
        User::factory()->create([
            'email' => 'approved@example.com',
            'password' => 'StrongPass1!',
            'is_approved' => true,
        ]);

        $this->postJson('/api/v1/login', [
            'email' => 'approved@example.com',
            'password' => 'StrongPass1!',
        ])->assertOk()
            ->assertJsonStructure(['access_token', 'user']);
    }

    public function test_admin_can_list_and_approve_pending_users(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_approved' => true]);
        $pendingUser = User::factory()->create(['is_approved' => false]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/pending-users')
            ->assertOk()
            ->assertJsonPath('users.0.id', $pendingUser->id);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/admin/approve-user/{$pendingUser->id}")
            ->assertOk()
            ->assertJsonPath('user.is_approved', true);

        $this->assertDatabaseHas('users', [
            'id' => $pendingUser->id,
            'is_approved' => true,
        ]);
    }

    public function test_non_admin_cannot_access_approval_endpoints(): void
    {
        $viewer = User::factory()->create(['role' => 'viewer', 'is_approved' => true]);
        $pendingUser = User::factory()->create(['is_approved' => false]);

        $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/admin/pending-users')
            ->assertForbidden();

        $this->actingAs($viewer, 'sanctum')
            ->postJson("/api/admin/approve-user/{$pendingUser->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $pendingUser->id,
            'is_approved' => false,
        ]);
    }

    public function test_approving_a_missing_user_returns_not_found(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_approved' => true]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/approve-user/999999')
            ->assertNotFound();
    }
}
