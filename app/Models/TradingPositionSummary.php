<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TradingPositionSummary extends Model
{
    protected $fillable = [
        'trading_position_id',
        'total_volume',
        'average_price',
        'realized_pnl',
        'unrealized_pnl',
        'total_pnl'
    ];

    protected $casts = [
        'average_price' => 'decimal:2',
        'realized_pnl' => 'decimal:2',
        'unrealized_pnl' => 'decimal:2',
        'total_pnl' => 'decimal:2'
    ];

    public function position(): BelongsTo
    {
        return $this->belongsTo(TradingPosition::class, 'trading_position_id');
    }
}
