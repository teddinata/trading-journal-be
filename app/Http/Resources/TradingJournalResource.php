<?php
// app/Http/Resources/TradingJournalResource.php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TradingJournalResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'date' => $this->date->format('Y-m-d'),
            'emiten' => $this->emiten,
            'type' => $this->type,
            'buy_range' => [
                'low' => $this->buy_range_low,
                'high' => $this->buy_range_high
            ],
            'entry' => [
                'price' => $this->entry_price,
                'volume' => $this->volume,
                'timestamp' => $this->created_at
            ],
            'exit' => $this->when($this->exit_price, [
                'price' => $this->exit_price,
                'timestamp' => $this->updated_at
            ]),
            'targets' => [
                'take_profit_1' => $this->take_profit_1,
                'take_profit_2' => $this->take_profit_2,
                'stop_loss' => $this->stop_loss
            ],
            'performance' => [
                'profit_loss_amount' => $this->profit_loss_amount,
                'profit_loss_percentage' => $this->profit_loss_percentage,
                'status' => $this->status
            ],
            'analysis' => [
                'strategy' => $this->strategy,
                'notes' => $this->notes
            ],
            'timestamps' => [
                'created_at' => $this->created_at->toISOString(),
                'updated_at' => $this->updated_at->toISOString()
            ]
        ];
    }
}
