<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Schema::create('trading_journals', function (Blueprint $table) {
        //     $table->id();
        //     $table->foreignId('user_id')->constrained()->onDelete('cascade');
        //     $table->date('date');
        //     $table->string('emiten');
        //     $table->enum('type', ['BUY', 'SELL']);
        //     $table->decimal('entry_price', 10, 2);
        //     $table->decimal('exit_price', 10, 2)->nullable();
        //     $table->integer('volume');
        //     $table->decimal('take_profit_1', 10, 2)->nullable();
        //     $table->decimal('take_profit_2', 10, 2)->nullable();
        //     $table->decimal('stop_loss', 10, 2);
        //     $table->decimal('profit_loss_amount', 12, 2)->nullable();
        //     $table->decimal('profit_loss_percentage', 8, 2)->nullable();
        //     $table->enum('status', ['OPEN', 'CLOSED'])->default('OPEN');
        //     $table->text('strategy')->nullable();
        //     $table->text('notes')->nullable();
        //     $table->timestamps();
        // });

        // Create trading_positions table
        Schema::create('trading_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('emiten');
            $table->enum('type', ['BUY', 'SELL']);
            $table->decimal('buy_range_low', 10, 2);   // Tambahkan range bawah
            $table->decimal('buy_range_high', 10, 2);  // Tambahkan range atas
            $table->decimal('entry_price', 10, 2);
            $table->decimal('stop_loss', 10, 2);
            $table->decimal('take_profit_1', 10, 2)->nullable();
            $table->decimal('take_profit_2', 10, 2)->nullable();
            $table->enum('status', ['OPEN', 'CLOSED'])->default('OPEN');
            $table->text('strategy')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Create trading_transactions table untuk mencatat setiap transaksi
        Schema::create('trading_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trading_position_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->enum('type', ['BUY', 'SELL']);
            $table->decimal('price', 10, 2);
            $table->integer('volume');
            $table->decimal('amount', 12, 2); // price * volume
            $table->text('notes')->nullable(); // untuk mencatat alasan (misal: "Average Down", "Take Profit", dll)
            $table->timestamps();
        });

        // Create trading_position_summaries table untuk rangkuman posisi
        Schema::create('trading_position_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trading_position_id')->constrained()->onDelete('cascade');
            $table->integer('total_volume');
            $table->decimal('average_price', 10, 2);
            $table->decimal('realized_pnl', 12, 2)->default(0);
            $table->decimal('unrealized_pnl', 12, 2)->default(0);
            $table->decimal('total_pnl', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Schema::dropIfExists('trading_journals');
        Schema::dropIfExists('trading_positions');
        Schema::dropIfExists('trading_transactions');
        Schema::dropIfExists('trading_position_summaries');
    }
};
