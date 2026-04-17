<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flash_sales', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Flash Sale');
            $table->timestamp('start_time');
            $table->timestamp('end_time');
            $table->enum('status', ['draft', 'active', 'ended', 'cancelled'])->default('draft');
            $table->timestamps();

            $table->index(['status', 'start_time', 'end_time']);
        });

        Schema::create('flash_sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flash_sale_id')->constrained('flash_sales')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products', 'product_id')->cascadeOnDelete();
            $table->decimal('campaign_price', 15, 2);
            $table->unsignedInteger('campaign_stock');
            $table->unsignedInteger('sold')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flash_sale_items');
        Schema::dropIfExists('flash_sales');
    }
};
