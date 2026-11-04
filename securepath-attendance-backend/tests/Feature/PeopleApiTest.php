<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesAuthorizedUsers;
use Tests\TestCase;

class PeopleApiTest extends TestCase
{
    use CreatesAuthorizedUsers, RefreshDatabase;

    protected function setUp(): void { parent::setUp(); Sanctum::actingAs($this->adminWith(['people.view', 'people.create', 'people.edit', 'people.delete'])); }

    public function test_people_crud_validation_search_filters_and_pagination(): void
    {
        $department = Department::factory()->create(['code' => 'DEP-ENG', 'name' => 'Engineering']);
        $this->postJson('/api/v1/people', ['firstName' => 'Sarah', 'lastName' => 'Chen', 'email' => 'sarah@example.com', 'departmentId' => 'DEP-ENG', 'type' => 'employee'])->assertCreated()->assertJsonPath('data.name', 'Sarah Chen');
        $this->postJson('/api/v1/people', ['email' => 'invalid'])->assertUnprocessable()->assertJsonPath('code', 'VALIDATION_ERROR');
        Person::factory()->count(4)->create(['department_id' => $department->id]);
        $this->getJson('/api/v1/people?search=Sarah&departmentId=DEP-ENG&page=1&limit=2')->assertOk()->assertJsonPath('pagination.limit', 2)->assertJsonCount(1, 'data');
        $personCode = Person::where('email', 'sarah@example.com')->value('person_code');
        $this->putJson('/api/v1/people/'.$personCode, ['jobTitle' => 'Lead Developer'])->assertOk();
        $this->deleteJson('/api/v1/people/'.$personCode)->assertNoContent();
        $this->assertSoftDeleted('people', ['person_code' => $personCode]);
    }
}

