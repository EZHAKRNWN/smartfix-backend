<?php

namespace App\Http\Controllers\Api;

use App\Actions\CompleteWorkOrderAction;
use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Models\WorkOrder;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkOrderController extends Controller
{
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
