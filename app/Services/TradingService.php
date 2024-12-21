<?php

namespace App\Services;

use App\Models\TradingPosition;
use Illuminate\Support\Facades\DB;

class TradingService
{
    const LOT_SIZE = 100; // 1 lot = 100 lembar

    public function addTransaction(TradingPosition $position, array $data)
    {
        return DB::transaction(function () use ($position, $data) {
            // Kalkulasi amount berdasarkan lot (sama seperti frontend)
            $amount = $data['price'] * $data['volume'] * self::LOT_SIZE;

            // Buat transaksi baru
            $transaction = $position->transactions()->create([
                'date' => $data['date'] ?? now(),
                'type' => $data['type'],
                'price' => $data['price'],
                'volume' => $data['volume'],
                'amount' => $amount,
                'notes' => $data['notes'] ?? null
            ]);

            // Update summary
            $this->updatePositionSummary($position);

            return $transaction;
        });
    }

    private function updatePositionSummary(TradingPosition $position)
    {
        $transactions = $position->transactions;

        // Hitung volume dalam lot
        $buyTransactions = $transactions->where('type', 'BUY');
        $sellTransactions = $transactions->where('type', 'SELL');

        $totalBuyVolume = $buyTransactions->sum('volume');
        $totalSellVolume = $sellTransactions->sum('volume');
        $remainingVolume = $totalBuyVolume - $totalSellVolume;

        // Hitung average price (sudah dalam harga per lembar)
        $totalBuyAmount = $buyTransactions->sum('amount');
        $averagePrice = $totalBuyVolume > 0 ? ($totalBuyAmount / (self::LOT_SIZE * $totalBuyVolume)) : 0;

        // Hitung PnL berdasarkan lot size
        $realizedPnL = $this->calculateRealizedPnL($transactions);
        $unrealizedPnL = $this->calculateUnrealizedPnL($position, $remainingVolume, $averagePrice);

        // Update summary
        $position->summary()->updateOrCreate(
            ['trading_position_id' => $position->id],
            [
                'total_volume' => $remainingVolume,
                'average_price' => $averagePrice,
                'realized_pnl' => $realizedPnL,
                'unrealized_pnl' => $unrealizedPnL,
                'total_pnl' => $realizedPnL + $unrealizedPnL
            ]
        );

        if ($remainingVolume === 0) {
            $position->update(['status' => 'CLOSED']);
        }
    }

    private function calculateRealizedPnL($transactions)
    {
        $buyQueue = collect();
        $realizedPnL = 0;

        foreach ($transactions->sortBy('date') as $transaction) {
            if ($transaction->type === 'BUY') {
                $buyQueue->push([
                    'price' => $transaction->price,
                    'volume' => $transaction->volume,
                    'remaining' => $transaction->volume
                ]);
            } else { // SELL
                $remainingVolume = $transaction->volume;

                while ($remainingVolume > 0 && $buyQueue->isNotEmpty()) {
                    $buy = $buyQueue->shift();
                    $volumeToCalculate = min($buy['remaining'], $remainingVolume);

                    // PnL kalkulasi dengan lot size
                    $pnl = ($transaction->price - $buy['price']) * $volumeToCalculate * self::LOT_SIZE;
                    $realizedPnL += $pnl;
                    $remainingVolume -= $volumeToCalculate;

                    if ($buy['remaining'] > $volumeToCalculate) {
                        $buyQueue->prepend([
                            'price' => $buy['price'],
                            'volume' => $buy['volume'],
                            'remaining' => $buy['remaining'] - $volumeToCalculate
                        ]);
                    }
                }
            }
        }

        return $realizedPnL;
    }

    private function calculateUnrealizedPnL($position, $remainingVolume, $averagePrice)
    {
        $lastTransaction = $position->transactions()->latest('date')->first();
        if (!$lastTransaction) return 0;

        // Unrealized PnL dengan lot size
        return ($lastTransaction->price - $averagePrice) * $remainingVolume * self::LOT_SIZE;
    }
}
