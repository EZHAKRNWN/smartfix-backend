<?php

namespace App\Http\Controllers\Api;

use App\Actions\CompleteWorkOrderAction;
use App\Events\TicketCreated;
use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Jobs\SendSlaReminderJob;
use App\Models\BlockchainLog;
use App\Models\Part;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WorkOrderController extends Controller
{
    /**
     * Get list of work orders with items and technician.
     */
    public function index(Request $request): JsonResponse
    {
        $query = WorkOrder::with(['items.part', 'technician'])->latest();

        if ($request->filled('status') && $request->input('status') !== 'ALL') {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('wo_number', 'LIKE', "%{$search}%")
                    ->orWhere('customer_name', 'LIKE', "%{$search}%")
                    ->orWhere('device_issue', 'LIKE', "%{$search}%");
            });
        }

        $workOrders = $query->get();

        return response()->json([
            'success' => true,
            'data' => $workOrders,
        ], 200);
    }

    /**
     * Store a new repair work order.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'device_issue' => 'required|string|max:255',
            'wo_number' => 'nullable|string|unique:work_orders,wo_number',
            'status' => 'nullable|in:PENDING,IN_PROGRESS,COMPLETED,CANCELLED,pending,in_progress,completed,cancelled',
            'items' => 'nullable|array',
            'items.*.part_id' => 'required_with:items|exists:parts,id',
            'items.*.quantity' => 'required_with:items|integer|min:1',
        ]);

        $woNumber = $validated['wo_number'] ?? ('WO-'.strtoupper(Str::random(6)));

        $workOrder = WorkOrder::create([
            'wo_number' => $woNumber,
            'technician_id' => $request->user() ? $request->user()->id : 1,
            'customer_name' => $validated['customer_name'],
            'device_issue' => $validated['device_issue'],
            'status' => strtoupper($validated['status'] ?? 'PENDING'),
        ]);

        if (! empty($validated['items'])) {
            foreach ($validated['items'] as $item) {
                WorkOrderItem::create([
                    'work_order_id' => $workOrder->id,
                    'part_id' => $item['part_id'],
                    'quantity' => $item['quantity'],
                ]);
            }
        }

        $workOrder->load(['items.part', 'technician']);
        event(new TicketCreated($workOrder));
        SendSlaReminderJob::dispatch($workOrder->id)
            ->delay(now()->addHours(5))
            ->afterCommit();

        return response()->json([
            'success' => true,
            'message' => 'Tiket perbaikan berhasil dicatat.',
            'data' => $workOrder,
        ], 201);
    }

    /**
     * Get technician dashboard stats.
     */
    public function stats(Request $request): JsonResponse
    {
        $completedRepairs = WorkOrder::where('status', 'COMPLETED')->count();
        $pendingTickets = WorkOrder::whereIn('status', ['PENDING', 'IN_PROGRESS'])->count();

        $totalParts = Part::count();
        $blockchainLogs = BlockchainLog::count();
        $verifiedParts = max($blockchainLogs, $totalParts);

        return response()->json([
            'success' => true,
            'data' => [
                'completed_repairs' => $completedRepairs,
                'verified_parts' => $verifiedParts,
                'pending_tickets' => $pendingTickets,
            ],
        ], 200);
    }

    /**
     * Mark a work order as completed and deduct reserved inventory.
     */
    public function complete(Request $request, CompleteWorkOrderAction $action, int|string|null $id = null): JsonResponse
    {
        $targetId = $id ?? $request->route('id') ?? $request->input('id');

        if (! $targetId) {
            return response()->json([
                'success' => false,
                'message' => 'Work order ID is required.',
            ], 400);
        }

        $workOrder = WorkOrder::with('items')->findOrFail($targetId);

        try {
            $completedWorkOrder = $action->execute($workOrder);

            return response()->json([
                'success' => true,
                'message' => 'Work order completed successfully.',
                'data' => $completedWorkOrder,
            ], 200);
        } catch (InsufficientStockException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
