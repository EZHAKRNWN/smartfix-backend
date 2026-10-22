<?php

namespace App\Listeners;

use App\Events\TicketCreated;
use App\Models\UserNotification;

class PersistTicketNotification
{
    public function handle(TicketCreated $event): void
    {
        UserNotification::create([
            'user_id' => $event->workOrder->technician_id,
            'title' => 'Tiket servis baru',
            'message' => "Tiket {$event->workOrder->wo_number} telah dibuat.",
            'type' => 'ticket_created',
        ]);
    }
}
