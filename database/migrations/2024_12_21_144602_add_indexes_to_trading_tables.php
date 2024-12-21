<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexesToTradingTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Indexes untuk trading_positions
        Schema::table('trading_positions', function (Blueprint $table) {
            $table->index(['user_id', 'emiten', 'status'], 'idx_user_emiten_status');
            $table->index(['user_id', 'created_at'], 'idx_user_created_at');
            $table->index(['emiten'], 'idx_emiten');
            $table->index(['status'], 'idx_status');
        });

        // Indexes untuk trading_transactions
        Schema::table('trading_transactions', function (Blueprint $table) {
            $table->index(['trading_position_id', 'date'], 'idx_position_date');
            $table->index(['date'], 'idx_transaction_date');
            $table->index(['type'], 'idx_transaction_type');
        });

        // Indexes untuk trading_position_summaries
        Schema::table('trading_position_summaries', function (Blueprint $table) {
            $table->index(['trading_position_id'], 'idx_summary_position');
            $table->index(['realized_pnl'], 'idx_realized_pnl');
            $table->index(['unrealized_pnl'], 'idx_unrealized_pnl');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Drop indexes dari trading_positions
        Schema::table('trading_positions', function (Blueprint $table) {
            $table->dropIndex('idx_user_emiten_status');
            $table->dropIndex('idx_user_created_at');
            $table->dropIndex('idx_emiten');
            $table->dropIndex('idx_status');
        });

        // Drop indexes dari trading_transactions
        Schema::table('trading_transactions', function (Blueprint $table) {
            $table->dropIndex('idx_position_date');
            $table->dropIndex('idx_transaction_date');
            $table->dropIndex('idx_transaction_type');
        });

        // Drop indexes dari trading_position_summaries
        Schema::table('trading_position_summaries', function (Blueprint $table) {
            $table->dropIndex('idx_summary_position');
            $table->dropIndex('idx_realized_pnl');
            $table->dropIndex('idx_unrealized_pnl');
        });
    }
}
