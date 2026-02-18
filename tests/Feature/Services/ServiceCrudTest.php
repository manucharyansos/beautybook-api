<?php

use App\Models\Salon;
use App\Models\Service;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('owner can list only own salon services (tenant isolation)', function () {
    $salonA = Salon::factory()->create();
    $salonB = Salon::factory()->create();

    $ownerA = User::factory()->create([
        'salon_id' => $salonA->id,
        'role' => 'owner',
    ]);

    Service::factory()->count(2)->create(['salon_id' => $salonA->id]);
    Service::factory()->count(3)->create(['salon_id' => $salonB->id]);

    Sanctum::actingAs($ownerA);

    $res = $this->getJson('/api/services');

    $res->assertOk();
    // paginate returns data array
    expect($res->json('data'))->toHaveCount(2);
});

it('staff cannot create service', function () {
    $salon = Salon::factory()->create();

    $staff = User::factory()->create([
        'salon_id' => $salon->id,
        'role' => 'staff',
    ]);

    Sanctum::actingAs($staff);

    $res = $this->postJson('/api/services', [
        'name' => 'Test Service',
        'duration_minutes' => 60,
        'price' => 9000,
        'is_active' => true,
    ]);

    $res->assertForbidden();
});

it('owner can create service', function () {
    $salon = Salon::factory()->create();

    $owner = User::factory()->create([
        'salon_id' => $salon->id,
        'role' => 'owner',
    ]);

    Sanctum::actingAs($owner);

    $res = $this->postJson('/api/services', [
        'name' => 'Manicure',
        'duration_minutes' => 60,
        'price' => 8000,
        'is_active' => true,
    ]);

    $res->assertCreated()
        ->assertJsonPath('data.name', 'Manicure');

    $this->assertDatabaseHas('services', [
        'salon_id' => $salon->id,
        'name' => 'Manicure',
    ]);
});

it('owner cannot access other salon service via show', function () {
    $salonA = Salon::factory()->create();
    $salonB = Salon::factory()->create();

    $ownerA = User::factory()->create([
        'salon_id' => $salonA->id,
        'role' => 'owner',
    ]);

    $serviceB = Service::factory()->create(['salon_id' => $salonB->id]);

    Sanctum::actingAs($ownerA);

    // With tenant global scope, route-model binding should not find it => 404
    $res = $this->getJson('/api/services/'.$serviceB->id);
    $res->assertNotFound();
});
