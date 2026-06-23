<?php

namespace Tests\Feature;

use App\Models\RideRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RideRequestFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_can_create_ride()
    {
        $driver = User::factory()->create();

        $response = $this->actingAs($driver, 'api')->postJson('/api/rides', [
            'origin_address' => 'Calle Origen 123',
            'origin_lat' => 19.4326,
            'origin_lng' => -99.1332,
            'destination_address' => 'Calle Destino 456',
            'destination_lat' => 19.5326,
            'destination_lng' => -99.2332,
            'departure_time' => now()->addHours(2)->format('Y-m-d H:i:s'),
            'available_seats' => 3,
            'total_seats' => 3,
            'price_per_seat' => 50,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['success', 'data' => ['id', 'status']]);
    }

    public function test_passenger_can_join_ride()
    {
        $driver = User::factory()->create();
        $passenger = User::factory()->create();
        $ride = RideRequest::factory()->create([
            'driver_id' => $driver->id,
            'status' => 'available',
            'available_seats' => 3,
            'total_seats' => 3,
        ]);

        $response = $this->actingAs($passenger, 'api')->postJson("/api/rides/{$ride->id}/join", [
            'seats' => 1,
        ]);

        $response->assertStatus(200);
        $this->assertEquals(1, $ride->fresh()->passenger_requests_count);
    }

    public function test_cannot_join_own_ride()
    {
        $driver = User::factory()->create();
        $ride = RideRequest::factory()->create([
            'driver_id' => $driver->id,
            'status' => 'available',
        ]);

        $response = $this->actingAs($driver, 'api')->postJson("/api/rides/{$ride->id}/join", [
            'seats' => 1,
        ]);

        $response->assertStatus(400);
    }

    public function test_cannot_join_full_ride()
    {
        $driver = User::factory()->create();
        $passenger = User::factory()->create();
        $ride = RideRequest::factory()->create([
            'driver_id' => $driver->id,
            'status' => 'full',
            'available_seats' => 0,
            'total_seats' => 2,
        ]);

        $response = $this->actingAs($passenger, 'api')->postJson("/api/rides/{$ride->id}/join", [
            'seats' => 1,
        ]);

        $response->assertStatus(422);
    }
}
