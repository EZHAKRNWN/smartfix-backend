<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderItem extends Model
{
    /**
     * The attributes that aren't mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * Get the parent work order.
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * Get the part used in this item.
     */
    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }
}
