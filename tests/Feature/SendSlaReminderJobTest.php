<?php

namespace Tests\Feature;

use App\Jobs\SendSlaReminderJob;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SendSlaReminderJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_sla_notification_when_work_order_is_still_in_progress(): void
    {
        $technician = User::factory()->create();
        $workOrder = WorkOrder::create([
            'wo_number' => 'WO-SLA-001',
            'technician_id' => $technician->id,
            'customer_name' => 'Jane Doe',
            'device_issue' => 'Screen damage',
            'status' => 'IN_PROGRESS',
        ]);

        (new SendSlaReminderJob($workOrder->id))->handle();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $technician->id,
            'title' => 'Pengingat SLA tiket',
            'type' => 'sla_reminder',
        ]);
    }

    public function test_drops_sla_notification_when_work_order_status_has_changed(): void
    {
        $technician = User::factory()->create();
        $workOrder = WorkOrder::create([
            'wo_number' => 'WO-SLA-002',
            'technician_id' => $technician->id,
            'customer_name' => 'John Doe',
            'device_issue' => 'Battery issue',
            'status' => 'IN_PROGRESS',
        ]);
        $job = new SendSlaReminderJob($workOrder->id);
        WorkOrder::whereKey($workOrder->id)->update(['status' => 'COMPLETED']);

        $job->handle();

        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_drops_sla_notification_when_work_order_no_longer_exists(): void
    {
        $technician = User::factory()->create();
        $workOrder = WorkOrder::create([
            'wo_number' => 'WO-SLA-003',
            'technician_id' => $technician->id,
            'customer_name' => 'Alex Doe',
            'device_issue' => 'Speaker issue',
            'status' => 'IN_PROGRESS',
        ]);
        $job = new SendSlaReminderJob($workOrder->id);
        $workOrder->delete();

        $job->handle();

        $this->assertDatabaseCount('notifications', 0);
    }
}
