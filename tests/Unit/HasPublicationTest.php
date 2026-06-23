<?php

namespace Tests\Unit;

use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HasPublicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_request_creates_publication_on_created()
    {
        $user = User::factory()->create();
        $sr = ServiceRequest::factory()->create(['user_id' => $user->id]);

        $this->assertNotNull($sr->publication);
        $this->assertEquals('active', $sr->publication->status);
        $this->assertEquals('service', $sr->publication->category);
    }

    public function test_allowed_transitions_from_open()
    {
        $sr = ServiceRequest::factory()->create(['status' => 'open']);

        $this->assertTrue($sr->canTransitionTo('in_progress'));
        $this->assertTrue($sr->canTransitionTo('cancelled'));
        $this->assertFalse($sr->canTransitionTo('completed'));
        $this->assertFalse($sr->canTransitionTo('delivered'));
    }

    public function test_blocked_transitions_from_terminal_states()
    {
        $completed = ServiceRequest::factory()->create(['status' => 'completed']);
        $cancelled = ServiceRequest::factory()->create(['status' => 'cancelled']);

        $this->assertFalse($completed->canTransitionTo('cancelled'));
        $this->assertFalse($cancelled->canTransitionTo('open'));
    }

    public function test_transitionTo_updates_status()
    {
        $sr = ServiceRequest::factory()->create(['status' => 'open']);
        $sr->transitionTo('in_progress');

        $this->assertEquals('in_progress', $sr->fresh()->status);
    }

    public function test_transitionTo_throws_on_invalid()
    {
        $this->expectException(\InvalidArgumentException::class);

        $sr = ServiceRequest::factory()->create(['status' => 'open']);
        $sr->transitionTo('completed');
    }

    public function test_publication_updates_on_status_change()
    {
        $sr = ServiceRequest::factory()->create(['status' => 'open', 'user_id' => User::factory()]);
        $initialStatus = $sr->publication->status;

        $sr->transitionTo('in_progress');

        $this->assertNotEquals($initialStatus, $sr->fresh()->publication->status);
        $this->assertEquals('in_progress', $sr->fresh()->publication->status);
    }
}
