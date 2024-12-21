<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TradingPosition extends Model
{
    protected $fillable = [
        'user_id',
        'emiten',
        'type',
        'buy_range_low',
        'buy_range_high',
        'entry_price',
        'stop_loss',
        'take_profit_1',
        'take_profit_2',
        'status',
        'strategy',
        'notes'
    ];

    protected $casts = [
        // 'buy_range_low' => 'decimal:2',
        // 'buy_range_high' => 'decimal:2',
        'entry_price' => 'decimal:2',
        'stop_loss' => 'decimal:2',
        'take_profit_1' => 'decimal:2',
        'take_profit_2' => 'decimal:2'
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(TradingTransaction::class);
    }

    public function summary(): HasOne
    {
        return $this->hasOne(TradingPositionSummary::class);
    }
}
