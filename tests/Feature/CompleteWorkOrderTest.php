<?php

namespace Tests\Feature;

use App\Events\LowStockDetected;
use App\Models\Part;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompleteWorkOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_successfully_completes_work_order_and_deducts_stock(): void
    {
        Event::fake([LowStockDetected::class]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $part = Part::create([
            'serial_number' => 'PART-001',
            'name' => 'LCD Screen iPhone 13',
            'category' => 'Screen',
            'stock_quantity' => 10,
            'min_stock_level' => 2,
            'base_price_idr' => 1500000,
        ]);

        $workOrder = WorkOrder::create([
            'wo_number' => 'WO-2026-0001',
            'technician_id' => $user->id,
            'customer_name' => 'John Doe',
            'device_issue' => 'Cracked Screen',
            'status' => 'PENDING',
        ]);

        $item = WorkOrderItem::create([
            'work_order_id' => $workOrder->id,
            'part_id' => $part->id,
            'quantity' => 3,
        ]);

        $response = $this->postJson("/api/work-orders/{$workOrder->id}/complete");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $workOrder->id,
                    'status' => 'COMPLETED',
                ],
            ]);

        $this->assertDatabaseHas('parts', [
            'id' => $part->id,
            'stock_quantity' => 7,
        ]);

        $item->refresh();
        $this->assertNotNull($item->usage_hash);

        Event::assertNotDispatched(LowStockDetected::class);
    }

    public function test_triggers_low_stock_event_when_reaching_or_below_min_stock(): void
    {
        Event::fake([LowStockDetected::class]);

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $part = Part::create([
            'serial_number' => 'PART-002',
            'name' => 'Battery iPhone 13',
            'category' => 'Battery',
            'stock_quantity' => 5,
            'min_stock_level' => 3,
            'base_price_idr' => 500000,
        ]);

        $workOrder = WorkOrder::create([
            'wo_number' => 'WO-2026-0002',
            'technician_id' => $user->id,
            'customer_name' => 'Jane Doe',
            'device_issue' => 'Battery Health Degradation',
            'status' => 'IN_PROGRESS',
        ]);

        WorkOrderItem::create([
            'work_order_id' => $workOrder->id,
            'part_id' => $part->id,
            'quantity' => 3,
        ]);

        $response = $this->postJson("/api/work-orders/{$workOrder->id}/complete");

        $response->assertStatus(200);

        $this->assertDatabaseHas('parts', [
            'id' => $part->id,
            'stock_quantity' => 2,
        ]);

        Event::assertDispatched(LowStockDetected::class, function (LowStockDetected $event) use ($part) {
            return $event->part->id === $part->id;
        });
    }

    public function test_returns_422_when_insufficient_stock(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $part = Part::create([
            'serial_number' => 'PART-003',
            'name' => 'Camera Module iPhone 13',
            'category' => 'Camera',
            'stock_quantity' => 1,
            'min_stock_level' => 1,
            'base_price_idr' => 800000,
        ]);

        $workOrder = WorkOrder::create([
            'wo_number' => 'WO-2026-0003',
            'technician_id' => $user->id,
            'customer_name' => 'Alice',
            'device_issue' => 'Blurry Camera',
            'status' => 'PENDING',
        ]);

        WorkOrderItem::create([
            'work_order_id' => $workOrder->id,
            'part_id' => $part->id,
            'quantity' => 5,
        ]);

        $response = $this->postJson("/api/work-orders/{$workOrder->id}/complete");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        $this->assertDatabaseHas('parts', [
            'id' => $part->id,
            'stock_quantity' => 1,
        ]);

        $this->assertDatabaseHas('work_orders', [
            'id' => $workOrder->id,
            'status' => 'PENDING',
        ]);
    }

    public function test_returns_400_when_work_order_already_completed(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $workOrder = WorkOrder::create([
            'wo_number' => 'WO-2026-0004',
            'technician_id' => $user->id,
            'customer_name' => 'Bob',
            'device_issue' => 'Broken Speaker',
            'status' => 'COMPLETED',
        ]);

        $response = $this->postJson("/api/work-orders/{$workOrder->id}/complete");

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
            ]);
    }
}
