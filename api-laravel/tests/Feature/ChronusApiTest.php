<?php

namespace Tests\Feature;

use App\Models\AccessLocation;
use App\Models\Gatekeeper;
use App\Models\GatekeeperShift;
use App\Models\Person;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ChronusApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_login_and_protected_profile(): void
    {
        $r = $this->postJson('/api/v1/auth/login', ['login' => 'admin', 'password' => 'Chronus@123']);
        $r->assertOk()->assertJsonStructure(['data' => ['token', 'user']]);
        $this->withToken($r->json('data.token'))->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_operator_cannot_manage_registrations(): void
    {
        $u = User::where('username', 'portaria')->first();
        $this->actingAs($u)->postJson('/api/v1/departments', ['name' => 'Restrito'])->assertForbidden();
        $this->actingAs($u)->getJson('/api/v1/gatekeepers')->assertForbidden();
        $this->actingAs($u)->getJson('/api/v1/security-companies')->assertForbidden();
    }

    public function test_gatekeeper_is_not_registered_as_employee(): void
    {
        $gatekeeper = Gatekeeper::with('company', 'user')->firstOrFail();

        $this->assertSame('Carlos Lima', $gatekeeper->name);
        $this->assertSame('portaria', $gatekeeper->user->username);
        $this->assertNotNull($gatekeeper->company);
        $this->assertDatabaseMissing('people', ['registration' => 'P001']);
    }

    public function test_admin_manages_security_companies_and_gatekeepers(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        $company = $this->actingAs($admin)->postJson('/api/v1/security-companies', [
            'name' => 'Segurança Alfa',
            'cnpj' => '12.345.678/0001-90',
            'active' => true,
        ])->assertCreated()->json('data');

        $this->actingAs($admin)->postJson('/api/v1/gatekeepers', [
            'security_company_id' => $company['id'],
            'name' => 'Maria da Silva',
            'registration' => 'P002',
            'active' => true,
        ])->assertCreated()
            ->assertJsonPath('data.company.name', 'Segurança Alfa');

        $this->assertDatabaseHas('gatekeepers', [
            'registration' => 'P002',
            'security_company_id' => $company['id'],
        ]);
    }

    public function test_gatekeeper_controls_shift_and_one_hour_break(): void
    {
        Carbon::setTestNow('2026-08-17 08:00:00');
        $operator = User::where('username', 'portaria')->firstOrFail();
        $location = AccessLocation::firstOrFail();

        $this->actingAs($operator)->postJson('/api/v1/gatekeeper-shifts/start', [
            'access_location_id' => $location->id,
        ])->assertCreated()->assertJsonPath('data.status', 'working');

        $this->actingAs($operator)->postJson('/api/v1/gatekeeper-shifts/start', [
            'access_location_id' => $location->id,
        ])->assertUnprocessable();

        Carbon::setTestNow('2026-08-17 12:00:00');
        $this->actingAs($operator)->postJson('/api/v1/gatekeeper-shifts/break/start')
            ->assertOk()->assertJsonPath('data.status', 'on_break');

        Carbon::setTestNow('2026-08-17 12:59:00');
        $this->actingAs($operator)->postJson('/api/v1/gatekeeper-shifts/break/end')
            ->assertUnprocessable()->assertJsonValidationErrors('break');

        Carbon::setTestNow('2026-08-17 13:00:00');
        $this->actingAs($operator)->postJson('/api/v1/gatekeeper-shifts/break/end')
            ->assertOk()->assertJsonPath('data.status', 'working');

        Carbon::setTestNow('2026-08-17 17:00:00');
        $this->actingAs($operator)->postJson('/api/v1/gatekeeper-shifts/finish')
            ->assertOk()->assertJsonPath('data.status', 'finished');

        $this->assertDatabaseCount('gatekeeper_shifts', 1);
        $this->assertNotNull(GatekeeperShift::firstOrFail()->ended_at);
        Carbon::setTestNow();
    }

    public function test_person_movement_is_idempotent_and_blocks_duplicate_state(): void
    {
        $u = User::where('username', 'portaria')->first();
        $p = Person::first();
        $l = AccessLocation::first();
        $payload = ['uuid' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'person_id' => $p->id, 'type' => 'entry', 'access_location_id' => $l->id];
        $this->actingAs($u)->postJson('/api/v1/movements/person', $payload)->assertCreated();
        $this->actingAs($u)->postJson('/api/v1/movements/person', $payload)->assertCreated();
        $this->assertDatabaseCount('person_movements', 1);
        $payload['uuid'] = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
        $this->actingAs($u)->postJson('/api/v1/movements/person', $payload)->assertUnprocessable();
    }

    public function test_admin_exception_requires_reason(): void
    {
        $u = User::where('username', 'admin')->first();
        $p = Person::first();
        $l = AccessLocation::first();
        $base = ['person_id' => $p->id, 'type' => 'entry', 'access_location_id' => $l->id];
        $this->actingAs($u)->postJson('/api/v1/movements/person', $base + ['uuid' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa'])->assertCreated();
        $this->actingAs($u)->postJson('/api/v1/movements/person', $base + ['uuid' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'force' => true])->assertUnprocessable();
        $this->actingAs($u)->postJson('/api/v1/movements/person', $base + ['uuid' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc', 'force' => true, 'correction_reason' => 'Erro confirmado pelo administrador'])->assertCreated();
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_reports_generate_real_files(): void
    {
        $u = User::where('username', 'admin')->first();
        $this->actingAs($u)->get('/api/v1/reports/pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
        $r = $this->actingAs($u)->get('/api/v1/reports/xlsx');
        $r->assertOk();
        $this->assertStringStartsWith('PK', $r->streamedContent());
    }

    public function test_correction_preserves_original(): void
    {
        $u = User::where('username', 'admin')->first();
        $payload = ['uuid' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa', 'person_id' => Person::first()->id, 'type' => 'entry', 'access_location_id' => AccessLocation::first()->id];
        $original = $this->actingAs($u)->postJson('/api/v1/movements/person', $payload)->json('data');
        $this->actingAs($u)->postJson('/api/v1/movements/person/'.$original['id'].'/correct', ['uuid' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 'type' => 'exit', 'occurred_at' => now()->toISOString(), 'access_location_id' => AccessLocation::first()->id, 'correction_reason' => 'Tipo informado incorretamente'])->assertCreated();
        $this->assertDatabaseHas('person_movements', ['id' => $original['id'], 'status' => 'corrected']);
        $this->assertDatabaseHas('person_movements', ['corrects_id' => $original['id'], 'status' => 'valid']);
    }

    public function test_admin_can_register_multiple_vehicles_for_employee(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        $person = Person::firstOrFail();

        foreach ([
            ['plate' => 'def-2g34', 'model' => 'Onix', 'color' => 'Branco', 'active' => true],
            ['plate' => 'GHI5678', 'model' => 'Civic', 'color' => 'Preto', 'active' => false],
        ] as $vehicle) {
            $this->actingAs($admin)->postJson('/api/v1/vehicles', $vehicle + [
                'person_ids' => [$person->id],
            ])->assertCreated();
        }

        $this->assertDatabaseHas('vehicles', ['plate' => 'DEF2G34', 'active' => true]);
        $this->assertDatabaseHas('vehicles', ['plate' => 'GHI5678', 'active' => false]);
        $this->assertCount(3, $person->fresh()->vehicles);

        $this->actingAs($admin)
            ->getJson('/api/v1/people/'.$person->id)
            ->assertOk()
            ->assertJsonCount(3, 'data.vehicles')
            ->assertJsonPath('data.vehicles.1.plate', 'DEF2G34');
    }

    public function test_vehicle_plate_is_validated_in_backend(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        $person = Person::firstOrFail();

        $this->actingAs($admin)->postJson('/api/v1/vehicles', [
            'plate' => 'ABC@1D23',
            'model' => 'Modelo',
            'color' => 'Azul',
            'active' => true,
            'person_ids' => [$person->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('plate');

        $this->assertDatabaseCount('vehicles', 1);
    }

    public function test_vehicle_update_keeps_employee_relationship(): void
    {
        $admin = User::where('username', 'admin')->firstOrFail();
        $person = Person::firstOrFail();
        $vehicle = Vehicle::firstOrFail();

        $this->actingAs($admin)->putJson('/api/v1/vehicles/'.$vehicle->id, [
            'plate' => 'ABC1D23',
            'model' => 'Corolla Cross',
            'color' => 'Cinza',
            'active' => false,
            'person_ids' => [$person->id],
        ])->assertOk()->assertJsonPath('data.active', false);

        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->id,
            'model' => 'Corolla Cross',
            'active' => false,
        ]);
        $this->assertDatabaseHas('person_vehicles', [
            'person_id' => $person->id,
            'vehicle_id' => $vehicle->id,
        ]);
    }
}
