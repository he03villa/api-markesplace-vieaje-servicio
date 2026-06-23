<?php

namespace Tests\Feature;

use App\Models\Offer;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceRequestFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_can_create_service_request()
    {
        $client = User::factory()->create();

        $response = $this->actingAs($client, 'api')->postJson('/api/service-requests', [
            'title' => 'Necesito un plomero',
            'description' => 'Tengo una fuga en la cocina',
            'category' => 'plomeria',
            'address' => 'Calle Principal 123',
            'latitude' => 19.4326,
            'longitude' => -99.1332,
            'budget_min' => 100,
            'budget_max' => 500,
            'deadline' => now()->addDays(7)->format('Y-m-d'),
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['success', 'data' => ['id', 'title', 'status']]);
    }

    public function test_worker_can_offer_on_open_request()
    {
        $client = User::factory()->create();
        $worker = User::factory()->create();
        $sr = ServiceRequest::factory()->create(['user_id' => $client->id, 'status' => 'open']);

        $response = $this->actingAs($worker, 'api')->postJson('/api/offers', [
            'service_request_id' => $sr->id,
            'price' => 350,
            'description' => 'Yo puedo hacerlo',
            'estimated_time' => '2 horas',
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);
    }

    public function test_client_can_accept_offer()
    {
        $client = User::factory()->create();
        $worker = User::factory()->create();
        $sr = ServiceRequest::factory()->create(['user_id' => $client->id, 'status' => 'open']);
        $offer = Offer::factory()->create([
            'service_request_id' => $sr->id,
            'user_id' => $worker->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($client, 'api')->postJson("/api/offers/{$offer->id}/accept");

        $response->assertStatus(200);
        $this->assertEquals('in_progress', $sr->fresh()->status);
        $this->assertEquals('accepted', $offer->fresh()->status);
    }

    public function test_cannot_offer_on_closed_request()
    {
        $client = User::factory()->create();
        $worker = User::factory()->create();
        $sr = ServiceRequest::factory()->create(['user_id' => $client->id, 'status' => 'completed']);

        $response = $this->actingAs($worker, 'api')->postJson('/api/offers', [
            'service_request_id' => $sr->id,
            'price' => 350,
            'description' => 'Yo puedo hacerlo',
        ]);

        $response->assertStatus(400);
    }
}
