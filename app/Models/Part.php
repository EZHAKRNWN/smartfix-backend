<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Part extends Model
{
    /**
     * The attributes that aren't mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * Get the blockchain logs for this part.
     */
    public function blockchainLogs(): HasMany
    {
        return $this->hasMany(BlockchainLog::class);
    }
}
