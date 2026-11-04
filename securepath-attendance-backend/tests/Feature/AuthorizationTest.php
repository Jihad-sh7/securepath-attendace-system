<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AttendanceRecord;
use App\Models\Permission;
use App\Models\Person;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesAuthorizedUsers;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use CreatesAuthorizedUsers, RefreshDatabase;

    public function test_super_admin_bypasses_all_permissions_and_can_create_admins(): void
    {
        $this->seed(PermissionSeeder::class); Sanctum::actingAs($this->superAdmin());
        $this->getJson('/api/v1/dashboard')->assertOk();
        $this->postJson('/api/v1/admins', ['name' => 'New Admin', 'email' => 'new.admin@example.com', 'password' => 'NewAdminPassword123!', 'password_confirmation' => 'NewAdminPassword123!', 'permissions' => ['dashboard.view']])->assertCreated();
    }

    public function test_limited_admin_can_access_allowed_endpoint_and_receives_403_elsewhere(): void
    {
        Sanctum::actingAs($this->adminWith(['people.view']));
        $this->getJson('/api/v1/people')->assertOk();
        $this->getJson('/api/v1/attendance')->assertForbidden()->assertJsonPath('code', 'FORBIDDEN');
        $this->getJson('/api/v1/admins')->assertForbidden();
    }

    public function test_normal_admin_cannot_modify_super_admin_or_grant_permissions(): void
    {
        $admin = $this->adminWith(['admins.edit', 'admins.permissions']); $super = User::factory()->create(['role' => UserRole::SuperAdmin]); Sanctum::actingAs($admin);
        $this->putJson('/api/v1/admins/'.$super->id, ['name' => 'Changed'])->assertForbidden();
        $this->putJson('/api/v1/admins/'.$super->id.'/permissions', ['permissions' => []])->assertForbidden();
    }

    public function test_regular_user_attendance_list_is_scoped_to_their_person(): void
    {
        $this->seed(PermissionSeeder::class);
        $person = Person::factory()->create();
        $other = Person::factory()->create();
        $user = User::factory()->create(['role' => UserRole::User, 'person_id' => $person->id]);
        $user->permissions()->attach(Permission::where('name', 'myAttendance.view')->firstOrFail());
        AttendanceRecord::factory()->create(['event_code' => 'EVT-OWN', 'person_id' => $person->id]);
        AttendanceRecord::factory()->create(['event_code' => 'EVT-OTHER', 'person_id' => $other->id]);

        Sanctum::actingAs($user);
        $this->getJson('/api/v1/attendance?personId='.$other->person_code)
            ->assertOk()
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonPath('data.0.id', 'EVT-OWN')
            ->assertJsonMissing(['id' => 'EVT-OTHER']);
    }
}
