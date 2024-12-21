<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TradingJournal extends Model
{
    protected $fillable = [
        'user_id',
        'date',
        'emiten',
        'type',
        'entry_price',
        'exit_price',
        'volume',
        'take_profit_1',
        'take_profit_2',
        'stop_loss',
        'profit_loss_amount',
        'profit_loss_percentage',
        'status',
        'strategy',
        'notes'
    ];

    protected $casts = [
        'date' => 'date',
        'entry_price' => 'decimal:2',
        'exit_price' => 'decimal:2',
        'take_profit_1' => 'decimal:2',
        'take_profit_2' => 'decimal:2',
        'stop_loss' => 'decimal:2',
        'profit_loss_amount' => 'decimal:2',
        'profit_loss_percentage' => 'decimal:2'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
