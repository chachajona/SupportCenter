<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class TicketManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed the database with necessary data
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
        $this->artisan('db:seed', ['--class' => 'HelpdeskSeeder']);
    }

    public function test_support_agent_can_create_ticket(): void
    {
        $department = Department::first();
        $user = User::factory()->create(['department_id' => $department->id]);
        $user->assignRole('support_agent');

        $response = $this->actingAs($user)->post('/tickets', [
            'subject' => 'Test ticket',
            'description' => 'Test description',
            'priority_id' => 2,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('tickets', ['subject' => 'Test ticket']);
    }

    public function test_department_scoped_access(): void
    {
        $dept1 = Department::first();
        $dept2 = Department::skip(1)->first() ?? Department::factory()->create(['name' => 'Second Dept']);

        $user = User::factory()->create(['department_id' => $dept1->id]);
        $user->assignRole('department_manager');

        $ownTicket = Ticket::factory()->create(['department_id' => $dept1->id]);
        $otherTicket = Ticket::factory()->create(['department_id' => $dept2->id]);

        $this->assertTrue($user->can('view', $ownTicket));
        $this->assertFalse($user->can('view', $otherTicket));
    }

    public function test_ticket_assignment_creates_audit_record(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('system_administrator');

        $ticket = Ticket::factory()->create();

        $agent = User::factory()->create(['department_id' => $ticket->department_id]);
        $agent->assignRole('support_agent');

        $this->actingAs($admin);
        app(TicketAssignmentService::class)->assignTicket($ticket, $agent);

        // For SQLite, the action will be mapped to 'granted'
        $expectedAction = DB::connection()->getDriverName() === 'sqlite' ? 'granted' : 'ticket_assigned';
        $this->assertDatabaseHas('permission_audits', [
            'user_id' => $agent->id,
            'action' => $expectedAction,
        ]);
    }

    public function test_ticket_can_be_viewed(): void
    {
        $user = User::factory()->create();
        $user->assignRole('support_agent');

        $ticket = Ticket::factory()->create([
            'assigned_to' => $user->id,
        ]);

        $response = $this->actingAs($user)->get("/tickets/{$ticket->id}");
        $response->assertOk();
    }

    public function test_system_admin_can_view_all_tickets(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('system_administrator');

        $dept1 = Department::first();
        $dept2 = Department::skip(1)->first() ?? Department::factory()->create(['name' => 'Second Dept']);

        $ticket1 = Ticket::factory()->create(['department_id' => $dept1->id]);
        $ticket2 = Ticket::factory()->create(['department_id' => $dept2->id]);

        $this->assertTrue($admin->can('view', $ticket1));
        $this->assertTrue($admin->can('view', $ticket2));
    }

    public function test_unauthorized_user_cannot_view_ticket(): void
    {
        $user1 = User::factory()->create();
        $user1->assignRole('support_agent');

        $user2 = User::factory()->create();
        $user2->assignRole('support_agent');

        $user3 = User::factory()->create();

        $ticket = Ticket::factory()->create([
            'assigned_to' => $user2->id,
            'created_by' => $user3->id,
        ]);

        $this->assertFalse($user1->can('view', $ticket));
    }

    public function test_auto_assignment_works(): void
    {
        $department = Department::first();

        $agent = User::factory()->create(['department_id' => $department->id]);
        $agent->assignRole('support_agent');

        // Make sure the agent is properly created and has the right role
        $this->assertTrue($agent->hasRole('support_agent'));
        $this->assertEquals($department->id, $agent->department_id);

        $ticket = Ticket::factory()->create([
            'department_id' => $department->id,
            'assigned_to' => null,  // Ensure it's not already assigned
        ]);

        $assignmentService = app(TicketAssignmentService::class);
        $assignedUser = $assignmentService->autoAssignTicket($ticket);

        $this->assertNotNull($assignedUser, 'Auto assignment should return a user');
        $this->assertEquals($agent->id, $assignedUser->id);
        $this->assertEquals($agent->id, $ticket->fresh()->assigned_to);
    }

    public function test_ticket_numbers_are_unique(): void
    {
        $ticket1 = Ticket::factory()->create();
        $ticket2 = Ticket::factory()->create();

        $this->assertNotEquals($ticket1->number, $ticket2->number);
        $this->assertNotEmpty($ticket1->number);
        $this->assertNotEmpty($ticket2->number);
    }
}
