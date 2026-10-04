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
        Schema::create('parts', function (Blueprint $table) {
            $table->id();
            $table->string('serial_number')->unique(); 
            $table->string('name'); 
            $table->string('category'); 
            $table->integer('stock_quantity');
            $table->decimal('base_price_usd', 8, 2)->nullable(); // HPP mata uang asing
            $table->decimal('base_price_idr', 12, 2); // HPP Rupiah
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parts');
    }
};
