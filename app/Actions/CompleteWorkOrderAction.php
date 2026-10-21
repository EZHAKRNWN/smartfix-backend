<?php

namespace App\Actions;

use App\Events\LowStockDetected;
use App\Exceptions\InsufficientStockException;
use App\Models\Part;
use App\Models\WorkOrder;
use Exception;
use Illuminate\Support\Facades\DB;

class CompleteWorkOrderAction
{
    /**
     * Complete a work order, atomically deduct stock with pessimistic locking,
     * generate usage hashes, and trigger low-stock alerts when threshold is breached.
     *
     * @throws InsufficientStockException
     * @throws Exception
     */
    public function execute(WorkOrder $workOrder): WorkOrder
    {
        return DB::transaction(function () use ($workOrder): WorkOrder {
            if ($workOrder->status === 'COMPLETED') {
                throw new Exception("Work order [{$workOrder->wo_number}] is already completed.");
            }

            $workOrder->loadMissing('items');

            // KRITIKAL: Urutkan berdasarkan part_id untuk mencegah Database Deadlock
            $sortedItems = $workOrder->items->sortBy('part_id');

            foreach ($sortedItems as $item) {
                // Kunci baris part
                $part = Part::where('id', $item->part_id)->lockForUpdate()->first();

                if (! $part) {
                    throw new Exception("Part with ID [{$item->part_id}] was not found.");
                }

                if ($part->stock_quantity < $item->quantity) {
                    throw new InsufficientStockException(
                        "Stock for Part [{$part->serial_number}] is insufficient. Available: {$part->stock_quantity}, Requested: {$item->quantity}"
                    );
                }

                // Efisiensi Kueri: Kurangi langsung di memori dan simpan
                $part->stock_quantity -= $item->quantity;
                $part->save();

                // Kriptografi: Gunakan microtime dan ID item untuk jaminan keunikan absolut
                $usageHash = hash('sha256', $workOrder->wo_number . $item->id . $item->part_id . microtime(true));
                $item->update([
                    'usage_hash' => $usageHash,
                ]);

                // Trigger event jika stok menyentuh batas ROP
                if ($part->stock_quantity <= $part->min_stock_level) {
                    event(new LowStockDetected($part));
                }
            }

            $workOrder->update(['status' => 'COMPLETED']);

            return $workOrder->refresh();
        });
    }
}
