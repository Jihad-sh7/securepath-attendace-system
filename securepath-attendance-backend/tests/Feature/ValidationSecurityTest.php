<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesAuthorizedUsers;
use Tests\TestCase;

class ValidationSecurityTest extends TestCase
{
    use CreatesAuthorizedUsers, RefreshDatabase;

    public function test_suite_uses_disposable_in_memory_database(): void
    {
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
    }

    public function test_invalid_payload_uses_structured_422_response(): void
    {
        Sanctum::actingAs($this->adminWith(['people.create']));
        $this->postJson('/api/v1/people', ['email' => 'not-an-email'])->assertUnprocessable()->assertJsonStructure(['success', 'message', 'code', 'errors'])->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_api_never_serializes_password_hashes(): void
    {
        $super = $this->superAdmin(); Sanctum::actingAs($super);
        $response = $this->getJson('/api/v1/profile')->assertOk();
        $this->assertStringNotContainsString($super->password, $response->getContent());
        $response->assertJsonMissingPath('data.password');
    }

    public function test_settings_reject_invalid_threshold_order(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => \App\Enums\UserRole::SuperAdmin]));

        $this->putJson('/api/v1/settings', ['settings' => ['manualReviewThreshold' => 0.95, 'confidenceThreshold' => 0.80]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('settings.manualReviewThreshold');
    }
}
