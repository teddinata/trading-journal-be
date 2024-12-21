<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TradingTransaction extends Model
{
    protected $fillable = [
        'trading_position_id',
        'date',
        'type',
        'price',
        'amount',
        'volume',
        'notes'
    ];

    protected $casts = [
        'date' => 'date',
        'price' => 'decimal:2',
        'amount' => 'decimal:2',
    ];

    public function position(): BelongsTo
    {
        return $this->belongsTo(TradingPosition::class, 'trading_position_id');
    }
}
