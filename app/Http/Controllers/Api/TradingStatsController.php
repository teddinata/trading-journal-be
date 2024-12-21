<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TradingStatsResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class TradingStatsController extends Controller
{
    public function summary(Request $request)
    {
        try {
            $days = $request->input('days', 30);
            $endDate = Carbon::now()->endOfDay();
            $startDate = Carbon::now()->subDays($days)->startOfDay();

            $cacheKey = "trading_stats_user_{$request->user()->id}_days_{$days}";

            $data = Cache::remember($cacheKey, 300, function () use ($request, $startDate, $endDate) {
                // Get positions data
                $positions = DB::table('trading_positions')
                    ->join('trading_position_summaries', 'trading_positions.id', '=', 'trading_position_summaries.trading_position_id')
                    ->where('trading_positions.user_id', $request->user()->id)
                    ->where('trading_positions.status', 'CLOSED')
                    ->select(
                        'trading_positions.*',
                        'trading_position_summaries.realized_pnl',
                        'trading_position_summaries.total_volume',
                        'trading_position_summaries.average_price'
                    )
                    ->get();

                return [
                    'summary' => $this->calculateStats($positions),
                    'daily_pnl' => $this->calculateDailyPnL($positions, $startDate, $endDate),
                    'strategy_analysis' => $this->analyzeStrategies($positions)
                ];
            });

            return new TradingStatsResource($data);

        } catch (\Exception $e) {
            Log::error('Trading stats error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to generate trading statistics',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    private function calculateStats($positions)
    {
        $winningPositions = $positions->where('realized_pnl', '>', 0);
        $losingPositions = $positions->where('realized_pnl', '<', 0);
        $breakEvenPositions = $positions->where('realized_pnl', '=', 0);

        $totalWinAmount = $winningPositions->sum('realized_pnl');
        $totalLossAmount = abs($losingPositions->sum('realized_pnl'));

        // Calculate daily metrics
        $days = $positions->groupBy(function($pos) {
            return Carbon::parse($pos->created_at)->format('Y-m-d');
        });

        $totalTradingDays = $days->count();
        $totalVolume = $positions->sum('total_volume');

        // Daily stats
        $winningDays = $days->filter(function($dayPositions) {
            return $dayPositions->sum('realized_pnl') > 0;
        })->count();

        $losingDays = $days->filter(function($dayPositions) {
            return $dayPositions->sum('realized_pnl') < 0;
        })->count();

        $breakEvenDays = $days->filter(function($dayPositions) {
            return $dayPositions->sum('realized_pnl') == 0;
        })->count();

        return [
            'overview' => [
                'total_pnl' => round($positions->sum('realized_pnl'), 2),
                'total_trades' => $positions->count(),
                'total_volume' => $totalVolume,
                'avg_trade_size' => round($positions->avg('total_volume') ?? 0, 2),
                'avg_daily_pnl' => $totalTradingDays > 0 ?
                    round($positions->sum('realized_pnl') / $totalTradingDays, 2) : 0,
                'avg_daily_volume' => $totalTradingDays > 0 ?
                    round($totalVolume / $totalTradingDays, 2) : 0
            ],
            'performance' => [
                'winning_trades' => $winningPositions->count(),
                'losing_trades' => $losingPositions->count(),
                'breakeven_trades' => $breakEvenPositions->count(),
                'win_rate' => $positions->count() > 0 ?
                    round(($winningPositions->count() / $positions->count()) * 100, 2) : 0,
                'profit_factor' => $totalLossAmount > 0 ?
                    round($totalWinAmount / $totalLossAmount, 2) : 0,
                'avg_win' => round($winningPositions->avg('realized_pnl') ?? 0, 2),
                'avg_loss' => round($losingPositions->avg('realized_pnl') ?? 0, 2),
                'largest_win' => round($winningPositions->max('realized_pnl') ?? 0, 2),
                'largest_loss' => round($losingPositions->min('realized_pnl') ?? 0, 2)
            ],
            'daily_stats' => [
                'total_trading_days' => $totalTradingDays,
                'winning_days' => $winningDays,
                'losing_days' => $losingDays,
                'breakeven_days' => $breakEvenDays,
                'logged_days' => $totalTradingDays, // Bisa disesuaikan jika ada tracking khusus
                'open_trades' => DB::table('trading_positions')
                    ->where('status', 'OPEN')
                    ->count()
            ],
            'risk_metrics' => [
                'win_loss_ratio' => $this->calculateWinLossRatio($winningPositions, $losingPositions),
                'risk_reward_ratio' => $this->calculateRiskRewardRatio($positions),
                'expectancy' => $this->calculateExpectancy($positions),
                'avg_risk_per_trade' => $this->calculateAvgRiskPerTrade($positions)
            ],
            'streaks' => [
                'current_streak' => $this->calculateCurrentStreak($positions),
                'max_winning_streak' => $this->calculateMaxStreak($positions, true),
                'max_losing_streak' => $this->calculateMaxStreak($positions, false),
                'max_consecutive_win_days' => $this->calculateMaxDayStreak($days, true),
                'max_consecutive_loss_days' => $this->calculateMaxDayStreak($days, false)
            ]
        ];
    }

    // Tambahkan method baru untuk menghitung streak harian
    private function calculateMaxDayStreak($days, bool $isWinning)
    {
        $maxStreak = 0;
        $currentStreak = 0;

        foreach ($days as $dayPositions) {
            $dayPnL = $dayPositions->sum('realized_pnl');

            if (($isWinning && $dayPnL > 0) || (!$isWinning && $dayPnL < 0)) {
                $currentStreak++;
                $maxStreak = max($maxStreak, $currentStreak);
            } else {
                $currentStreak = 0;
            }
        }

        return $maxStreak;
    }

    private function calculateDailyPnL($positions, $startDate, $endDate)
    {
        try {
            // Ambil semua transaksi untuk posisi yang closed
            $transactions = DB::table('trading_transactions')
                ->join('trading_positions', 'trading_positions.id', '=', 'trading_transactions.trading_position_id')
                ->join('trading_position_summaries', 'trading_positions.id', '=', 'trading_position_summaries.trading_position_id')
                ->where('trading_positions.status', 'CLOSED')
                ->whereBetween('trading_transactions.date', [$startDate, $endDate])
                ->select(
                    'trading_transactions.date',
                    'trading_transactions.type',
                    'trading_transactions.price',
                    'trading_transactions.volume',
                    'trading_position_summaries.average_price',
                    'trading_position_summaries.realized_pnl'
                )
                ->orderBy('trading_transactions.date')
                ->get();

            $dailyPnL = [];
            $currentDate = clone $startDate;
            $cumulativePnL = 0;

            while ($currentDate <= $endDate) {
                $dateStr = $currentDate->format('Y-m-d');

                // Filter transaksi untuk tanggal ini
                $dayTransactions = $transactions->where('date', $dateStr);

                // Hitung P/L harian dari transaksi SELL
                $dayPnL = $dayTransactions
                    ->where('type', 'SELL')
                    ->sum(function($transaction) {
                        return ($transaction->price - $transaction->average_price) * $transaction->volume;
                    });

                $cumulativePnL += $dayPnL;

                $dailyPnL[] = [
                    'date' => $dateStr,
                    'pnl' => round($dayPnL, 2),
                    'cumulative_pnl' => round($cumulativePnL, 2),
                    'trades_count' => $dayTransactions->count(),
                    'winning_trades' => $dayTransactions
                        ->where('type', 'SELL')
                        ->filter(function($t) {
                            return ($t->price - $t->average_price) > 0;
                        })->count(),
                    'losing_trades' => $dayTransactions
                        ->where('type', 'SELL')
                        ->filter(function($t) {
                            return ($t->price - $t->average_price) < 0;
                        })->count()
                ];

                $currentDate->addDay();
            }

            return $dailyPnL;

        } catch (\Exception $e) {
            Log::error('Error calculating daily PnL: ' . $e->getMessage());
            return [];
        }
    }

    private function analyzeStrategies($positions)
    {
        $strategies = [];

        foreach ($positions->groupBy('strategy') as $strategy => $trades) {
            if (empty($strategy)) continue;

            $winningTrades = $trades->where('realized_pnl', '>', 0);
            $losingTrades = $trades->where('realized_pnl', '<', 0);

            $totalPnL = $trades->sum('realized_pnl');
            $winCount = $winningTrades->count();
            $lossCount = $losingTrades->count();
            $totalTrades = $trades->count();

            $strategies[$strategy] = [
                'total_trades' => $totalTrades,
                'total_pnl' => round($totalPnL, 2),
                'win_rate' => $totalTrades > 0 ? round(($winCount / $totalTrades) * 100, 2) : 0,
                'avg_profit' => $winCount > 0 ? round($winningTrades->sum('realized_pnl') / $winCount, 2) : 0,
                'avg_loss' => $lossCount > 0 ? round($losingTrades->sum('realized_pnl') / $lossCount, 2) : 0,
                'expectancy' => $this->calculateStrategyExpectancy($trades)
            ];
        }

        return $strategies;
    }

    private function calculateWinLossRatio($winningPositions, $losingPositions)
    {
        $avgWin = $winningPositions->avg('realized_pnl') ?? 0;
        $avgLoss = abs($losingPositions->avg('realized_pnl') ?? 0);
        return $avgLoss > 0 ? round(abs($avgWin / $avgLoss), 2) : 0;
    }

    private function calculateRiskRewardRatio($positions)
    {
        $validPositions = $positions->filter(function ($position) {
            return $position->stop_loss > 0 &&
                   ($position->take_profit_1 > 0 || $position->take_profit_2 > 0);
        });

        if ($validPositions->isEmpty()) return 0;

        $totalRisk = $validPositions->sum(function ($position) {
            return abs($position->entry_price - $position->stop_loss) * $position->total_volume;
        });

        $totalReward = $validPositions->sum(function ($position) {
            $tp = $position->take_profit_2 > 0 ? $position->take_profit_2 : $position->take_profit_1;
            return abs($tp - $position->entry_price) * $position->total_volume;
        });

        return $totalRisk > 0 ? round($totalReward / $totalRisk, 2) : 0;
    }

    private function calculateExpectancy($positions)
    {
        if ($positions->isEmpty()) return 0;

        $winningPositions = $positions->where('realized_pnl', '>', 0);
        $losingPositions = $positions->where('realized_pnl', '<', 0);

        $winRate = $positions->count() > 0 ? $winningPositions->count() / $positions->count() : 0;
        $avgWin = $winningPositions->avg('realized_pnl') ?? 0;
        $avgLoss = abs($losingPositions->avg('realized_pnl') ?? 0);

        return round(($winRate * $avgWin) - ((1 - $winRate) * $avgLoss), 2);
    }

    private function calculateCurrentStreak($positions)
    {
        if ($positions->isEmpty()) return 0;

        $latestPositions = $positions->sortByDesc('created_at')->values();
        $currentStreak = 0;
        $isWinning = $latestPositions[0]->realized_pnl > 0;

        foreach ($latestPositions as $position) {
            if (($isWinning && $position->realized_pnl > 0) ||
                (!$isWinning && $position->realized_pnl < 0)) {
                $currentStreak += ($isWinning ? 1 : -1);
            } else {
                break;
            }
        }

        return $currentStreak;
    }

    private function calculateMaxStreak($positions, bool $isWinning)
    {
        if ($positions->isEmpty()) return 0;

        $trades = $positions->sortBy('created_at')->values();
        $maxStreak = 0;
        $currentStreak = 0;

        foreach ($trades as $trade) {
            if (($isWinning && $trade->realized_pnl > 0) ||
                (!$isWinning && $trade->realized_pnl < 0)) {
                $currentStreak++;
                $maxStreak = max($maxStreak, $currentStreak);
            } else {
                $currentStreak = 0;
            }
        }

        return $maxStreak;
    }

    private function calculateStrategyExpectancy($trades)
    {
        if ($trades->isEmpty()) {
            return 0;
        }

        $winningTrades = $trades->where('profit_loss_amount', '>', 0);
        $losingTrades = $trades->where('profit_loss_amount', '<', 0);

        $winRate = $trades->count() > 0 ? $winningTrades->count() / $trades->count() : 0;
        $avgWin = $winningTrades->avg('profit_loss_amount') ?? 0;
        $avgLoss = abs($losingTrades->avg('profit_loss_amount') ?? 0);

        return round(($winRate * $avgWin) - ((1 - $winRate) * $avgLoss), 2);
    }

    private function calculateAvgRiskPerTrade($positions)
    {
        $validPositions = $positions->filter(function ($position) {
            return $position->stop_loss > 0;
        });

        if ($validPositions->isEmpty()) return 0;

        $totalRisk = $validPositions->sum(function ($position) {
            return abs($position->entry_price - $position->stop_loss) * $position->total_volume;
        });

        return round($totalRisk / $validPositions->count(), 2);
    }
}
