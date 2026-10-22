<?php

namespace Tests\Feature;

use App\Jobs\SendSlaReminderJob;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TicketNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_creation_persists_notification_and_dispatches_five_hour_reminder(): void
    {
        $this->freezeTime();
        Queue::fake([SendSlaReminderJob::class]);
        $technician = User::factory()->create();
        Sanctum::actingAs($technician);

        $response = $this->postJson('/api/tickets', [
            'customer_name' => 'Jane Doe',
            'device_issue' => 'Screen damage',
            'status' => 'in_progress',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'IN_PROGRESS');

        $workOrder = WorkOrder::firstOrFail();
        $this->assertDatabaseHas('notifications', [
            'user_id' => $technician->id,
            'title' => 'Tiket servis baru',
            'message' => "Tiket {$workOrder->wo_number} telah dibuat.",
            'type' => 'ticket_created',
            'read_at' => null,
        ]);
        Queue::assertPushed(SendSlaReminderJob::class, function (SendSlaReminderJob $job): bool {
            return $job->workOrderId === WorkOrder::firstOrFail()->id
                && $job->delay?->getTimestamp() === now()->addHours(5)->getTimestamp();
        });
    }

    public function test_work_order_creation_route_keeps_accepting_uppercase_status_values(): void
    {
        Queue::fake([SendSlaReminderJob::class]);
        $technician = User::factory()->create();
        Sanctum::actingAs($technician);

        $response = $this->postJson('/api/work-orders', [
            'customer_name' => 'John Doe',
            'device_issue' => 'Battery issue',
            'status' => 'IN_PROGRESS',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'IN_PROGRESS');
    }
}
