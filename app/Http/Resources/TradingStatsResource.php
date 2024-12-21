<?php
// app/Http/Resources/TradingStatsResource.php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TradingStatsResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'summary' => [
                'overall' => [
                    'total_pnl' => $this->resource['summary']['overview']['total_pnl'] ?? 0,
                    'total_trades' => $this->resource['summary']['overview']['total_trades'] ?? 0,
                    'total_volume' => $this->resource['summary']['overview']['total_volume'] ?? 0,
                    'avg_trade_size' => $this->resource['summary']['overview']['avg_trade_size'] ?? 0
                ],
                'performance' => [
                    'winning_trades' => $this->resource['summary']['performance']['winning_trades'] ?? 0,
                    'losing_trades' => $this->resource['summary']['performance']['losing_trades'] ?? 0,
                    'win_rate' => $this->resource['summary']['performance']['win_rate'] ?? 0,
                    'profit_factor' => $this->resource['summary']['performance']['profit_factor'] ?? 0,
                    'avg_win' => $this->resource['summary']['performance']['avg_win'] ?? 0,
                    'avg_loss' => $this->resource['summary']['performance']['avg_loss'] ?? 0,
                    'largest_win' => $this->resource['summary']['performance']['largest_win'] ?? 0,
                    'largest_loss' => $this->resource['summary']['performance']['largest_loss'] ?? 0
                ],
                'risk_metrics' => $this->resource['summary']['risk_metrics'] ?? [
                    'win_loss_ratio' => 0,
                    'risk_reward_ratio' => 0,
                    'expectancy' => 0,
                    'avg_risk_per_trade' => 0
                ],
                'streaks' => $this->resource['summary']['streaks'] ?? [
                    'current_streak' => 0,
                    'max_winning_streak' => 0,
                    'max_losing_streak' => 0
                ]
            ],
            'daily_pnl' => $this->resource['daily_pnl'] ?? [],
            'strategies' => collect($this->resource['strategy_analysis'] ?? [])->map(function ($strategy, $name) {
                return [
                    'name' => $name,
                    'metrics' => [
                        'total_trades' => $strategy['total_trades'] ?? 0,
                        'total_pnl' => $strategy['total_pnl'] ?? 0,
                        'win_rate' => $strategy['win_rate'] ?? 0,
                        'avg_profit' => $strategy['avg_profit'] ?? 0,
                        'avg_loss' => $strategy['avg_loss'] ?? 0,
                        'expectancy' => $strategy['expectancy'] ?? 0,
                        'profit_factor' => $strategy['profit_factor'] ?? 0
                    ],
                    'analysis' => [
                        'avg_holding_period' => $strategy['avg_holding_period'] ?? 0,
                        'best_performing_emiten' => $strategy['best_performing_emiten'] ?? null
                    ]
                ];
            })->values()->all()
        ];
    }
}
