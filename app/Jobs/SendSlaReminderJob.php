<?php

namespace App\Jobs;

use App\Models\UserNotification;
use App\Models\WorkOrder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendSlaReminderJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $workOrderId) {}

    public function handle(): void
    {
        $workOrder = WorkOrder::find($this->workOrderId);

        if (! $workOrder) {
            return;
        }

        $workOrder->refresh();

        if ($workOrder->status !== 'IN_PROGRESS') {
            return;
        }

        UserNotification::create([
            'user_id' => $workOrder->technician_id,
            'title' => 'Pengingat SLA tiket',
            'message' => "Tiket {$workOrder->wo_number} masih dalam proses setelah 5 jam.",
            'type' => 'sla_reminder',
        ]);
    }
}
