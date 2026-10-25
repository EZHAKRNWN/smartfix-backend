<?php

namespace Tests\Feature;

use App\Events\LowStockDetected;
use App\Models\User;
use App\Models\WorkOrder;
use Database\Seeders\SmartFixDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SmartFixDatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_realistic_related_data_idempotently_and_dispatches_low_stock_events(): void
    {
        Event::fake([LowStockDetected::class]);

        $this->seed(SmartFixDatabaseSeeder::class);

        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseCount('parts', 12);
        $this->assertDatabaseCount('work_orders', 8);
        $this->assertDatabaseCount('work_order_items', 3);
        $this->assertDatabaseCount('notifications', 6);
        $this->assertDatabaseHas('parts', [
            'serial_number' => 'SF-IP14-LCD-001',
            'stock_quantity' => 2,
            'min_stock_level' => 3,
        ]);
        $this->assertDatabaseHas('parts', [
            'serial_number' => 'SF-IP11-BAT-001',
            'stock_quantity' => 8,
            'min_stock_level' => 3,
        ]);
        $this->assertDatabaseHas('work_orders', [
            'wo_number' => 'WO-SF-2026-0003',
            'device_model' => 'Samsung Galaxy A54',
            'status' => 'IN_PROGRESS',
        ]);
        $this->assertSame(
            385000.0,
            (float) WorkOrder::query()->where('wo_number', 'WO-SF-2026-0003')->value('estimated_cost'),
        );
        $this->assertDatabaseHas('work_order_items', [
            'work_order_id' => WorkOrder::query()->where('wo_number', 'WO-SF-2026-0003')->value('id'),
            'quantity' => 1,
        ]);

        $technician = User::query()->where('email', 'teknisi@smartfix.com')->firstOrFail();
        $this->assertTrue(Hash::check('password123', $technician->password));

        foreach (User::query()->get() as $seededTechnician) {
            $this->assertDatabaseHas('notifications', [
                'user_id' => $seededTechnician->id,
                'type' => 'part_checkout',
            ]);
            $this->assertDatabaseHas('notifications', [
                'user_id' => $seededTechnician->id,
                'type' => 'sla_reminder',
            ]);
        }

        Event::assertDispatched(
            LowStockDetected::class,
            fn (LowStockDetected $event): bool => $event->part->stock_quantity <= $event->part->min_stock_level,
        );

        $this->seed(SmartFixDatabaseSeeder::class);

        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseCount('parts', 12);
        $this->assertDatabaseCount('work_orders', 8);
        $this->assertDatabaseCount('work_order_items', 3);
        $this->assertDatabaseCount('notifications', 6);

        $this->assertSame(2, WorkOrder::query()->where('status', 'PENDING')->count());
        $this->assertSame(3, WorkOrder::query()->where('status', 'IN_PROGRESS')->count());
        $this->assertSame(2, WorkOrder::query()->where('status', 'COMPLETED')->count());
        $this->assertSame(1, WorkOrder::query()->where('status', 'CANCELLED')->count());
    }
}
