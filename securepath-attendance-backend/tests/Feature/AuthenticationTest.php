<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_correct_credentials_return_a_sanctum_token_without_sensitive_fields(): void
    {
        User::factory()->create(['email' => 'admin@example.com', 'password' => 'AdminPassword123!', 'role' => UserRole::Admin]);
        $this->postJson('/api/v1/auth/login', ['email' => 'admin@example.com', 'password' => 'AdminPassword123!'])
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('data.user.role', 'admin')->assertJsonStructure(['data' => ['accessToken', 'user' => ['permissions']]])->assertJsonMissingPath('data.user.password');
    }

    public function test_wrong_password_and_suspended_accounts_are_rejected(): void
    {
        User::factory()->create(['email' => 'active@example.com', 'password' => 'CorrectPassword123!']);
        User::factory()->create(['email' => 'suspended@example.com', 'password' => 'CorrectPassword123!', 'status' => 'suspended']);
        $this->postJson('/api/v1/auth/login', ['email' => 'active@example.com', 'password' => 'wrong'])->assertUnprocessable();
        $this->postJson('/api/v1/auth/login', ['email' => 'suspended@example.com', 'password' => 'CorrectPassword123!'])->assertUnprocessable();
    }

    public function test_unauthenticated_api_requests_return_structured_401(): void
    {
        $this->getJson('/api/v1/profile')->assertUnauthorized()->assertJson(['success' => false, 'code' => 'UNAUTHENTICATED']);
    }

    public function test_unauthenticated_mobile_requests_do_not_redirect_to_missing_login_route(): void
    {
        $this->post('/api/v1/mobile/attendance/recognize', [
            'operation' => 'check_in',
            'eventUuid' => '88e8e123-d69b-43d6-8276-c3c36c5b7035',
        ])->assertUnauthorized()->assertJson(['success' => false, 'code' => 'UNAUTHENTICATED']);
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = User::factory()->create(['email' => 'logout@example.com', 'password' => 'LogoutPassword123!']);
        $token = $user->createToken('test')->plainTextToken;
        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
