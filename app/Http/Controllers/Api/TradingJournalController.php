<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TradingJournalResource;
use App\Http\Resources\TradingJournalCollection;
use App\Models\TradingPosition;
use App\Services\TradingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TradingJournalController extends Controller
{
    protected $tradingService;

    public function __construct(TradingService $tradingService)
    {
        $this->tradingService = $tradingService;
    }

    public function index(Request $request)
    {
        try {
            \Log::info('Starting to fetch trading positions');

            $query = TradingPosition::query();
            \Log::info('Base query created');

            // Add user filter
            $query->where('user_id', $request->user()->id);
            \Log::info('Added user filter');

            // Add eager loading
            $query->with(['summary', 'transactions']);
            \Log::info('Added eager loading');

            // Add other filters
            if ($request->filled('emiten')) {
                $query->where('emiten', $request->emiten);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled(['date_from', 'date_to'])) {
                $startDate = Carbon::parse($request->date_from)->startOfDay();
                $endDate = Carbon::parse($request->date_to)->endOfDay();

                $query->whereHas('transactions', function($q) use ($startDate, $endDate) {
                    $q->whereBetween('date', [$startDate, $endDate]);
                });
            }
            \Log::info('Added all filters');

            // Execute query
            $positions = $query->paginate($request->input('per_page', 10));
            \Log::info('Query executed successfully');

            return response()->json([
                'status' => 'success',
                'data' => [
                    'positions' => $positions->items(),
                    'pagination' => [
                        'total' => $positions->total(),
                        'per_page' => $positions->perPage(),
                        'current_page' => $positions->currentPage(),
                        'last_page' => $positions->lastPage(),
                        'from' => $positions->firstItem(),
                        'to' => $positions->lastItem()
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error in index method: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch trading positions',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
                'trace' => config('app.debug') ? $e->getTrace() : null
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'emiten' => 'required|string|max:10',
                'type' => 'required|in:BUY,SELL',
                'buy_range_low' => 'required|numeric|min:0',
                'buy_range_high' => 'required|numeric|min:0',
                'entry_price' => 'required|numeric|min:0',
                'volume' => 'required|integer|min:1',
                'stop_loss' => 'required|numeric|min:0',
                'take_profit_1' => 'nullable|numeric|min:0',
                'take_profit_2' => 'nullable|numeric|min:0',
                'strategy' => 'nullable|string',
                'notes' => 'nullable|string'
            ]);

            DB::beginTransaction();

            // Create position
            $position = TradingPosition::create([
                'user_id' => $request->user()->id,
                'emiten' => $validated['emiten'],
                'type' => $validated['type'],
                'buy_range_low' => $validated['buy_range_low'],
                'buy_range_high' => $validated['buy_range_high'],
                'entry_price' => $validated['entry_price'],
                'stop_loss' => $validated['stop_loss'],
                'take_profit_1' => $validated['take_profit_1'],
                'take_profit_2' => $validated['take_profit_2'],
                'strategy' => $validated['strategy'],
                'notes' => $validated['notes']
            ]);

            // Add initial transaction
            $this->tradingService->addTransaction($position, [
                'date' => now(),
                'type' => $validated['type'],
                'price' => $validated['entry_price'],
                'volume' => $validated['volume'],
                'notes' => 'Initial Position'
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Trading position created successfully',
                'data' => $position->fresh(['summary', 'transactions'])
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create trading position: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create trading position',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    public function show(TradingPosition $position)
    {
        try {
            if ($position->user_id !== auth()->id()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized'
                ], 403);
            }

            $position->load(['summary', 'transactions']);

            return response()->json([
                'status' => 'success',
                'data' => $position
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch position: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch position details',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    public function update(Request $request, TradingPosition $position)
    {
        try {
            if ($position->user_id !== $request->user()->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unauthorized'
                ], 403);
            }

            $validated = $request->validate([
                'type' => 'required|in:BUY,SELL',
                'price' => 'required|numeric|min:0',
                'volume' => 'required|integer|min:1',
                'notes' => 'nullable|string'
            ]);

            DB::beginTransaction();

            $transaction = $this->tradingService->addTransaction($position, [
                'date' => now(),
                'type' => $validated['type'],
                'price' => $validated['price'],
                'volume' => $validated['volume'],
                'notes' => $validated['notes'] ?? null
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Transaction added successfully',
                'data' => $position->fresh(['summary', 'transactions'])
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to add transaction: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to add transaction',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    public function destroy(TradingPosition $position)
    {
        try {
            if ($position->user_id !== auth()->id()) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            DB::beginTransaction();

            $position->delete();

            DB::commit();

            return response()->json(['message' => 'Trading position deleted successfully']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to delete trading position',
                'message' => config('app.debug') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }
}
