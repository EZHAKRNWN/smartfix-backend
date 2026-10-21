<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkOrder extends Model
{
    /**
     * The attributes that aren't mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * Get the technician assigned to the work order.
     */
    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    /**
     * Get the line items for the work order.
     */
    public function items(): HasMany
    {
        return $this->hasMany(WorkOrderItem::class);
    }
}
