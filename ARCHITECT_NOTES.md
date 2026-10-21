# Architect Notes — Work Order Inventory Deduction Strategy

## Critical Rule: Inventory Deduction on Work Order Completion

When a Work Order's `status` transitions to `COMPLETED`, the system **MUST** atomically deduct the reserved parts from inventory. Failure to use proper locking will cause race-condition stock corruption under concurrent requests.

### Required Implementation Pattern

```php
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

DB::transaction(function () use ($workOrder) {
    foreach ($workOrder->items as $item) {
        // Pessimistic lock — blocks concurrent reads until this transaction commits
        $part = Part::where('id', $item->part_id)->lockForUpdate()->first();

        if ($part->stock_quantity < $item->quantity) {
            throw new \DomainException(
                "Insufficient stock for part [{$part->serial_number}]. Available: {$part->stock_quantity}, Requested: {$item->quantity}"
            );
        }

        $part->decrement('stock_quantity', $item->quantity);

        // Generate tamper-proof usage hash
        $item->update([
            'usage_hash' => hash('sha256', $workOrder->id . $item->part_id . now()->toIso8601String()),
        ]);
    }

    $workOrder->update(['status' => 'COMPLETED']);
});
```

### Why This Matters

| Concern              | Without `lockForUpdate()`                      | With `lockForUpdate()`                          |
|----------------------|-------------------------------------------------|-------------------------------------------------|
| **Concurrent reads** | Two requests read `stock_quantity = 5` at once  | Second request waits for the first to commit    |
| **Data integrity**   | Both deduct, resulting in `-1` stock            | Second request sees the updated stock of `0`    |
| **Auditability**     | No hash trail — deductions are untraceable      | SHA-256 hash seals each deduction event         |

### Reorder Point (ROP) Alert

After each deduction, check whether the part has fallen below its dynamic reorder point:

```php
if ($part->stock_quantity <= $part->min_stock_level) {
    // Dispatch a low-stock notification/event
    event(new LowStockAlert($part));
}
```

This check runs **inside** the transaction so it uses the freshly-decremented value, not a stale read.

### Rules for Controllers

1. **Never** deduct stock outside a `DB::transaction()` closure.
2. **Always** use `lockForUpdate()` when reading a part's `stock_quantity` before modifying it.
3. **Always** validate stock sufficiency inside the lock to prevent negative inventory.
4. **Always** generate the `usage_hash` at deduction time for audit integrity.
5. Move this logic into a dedicated **Service class** (e.g., `WorkOrderCompletionService`) — controllers should only orchestrate, not contain business logic.
