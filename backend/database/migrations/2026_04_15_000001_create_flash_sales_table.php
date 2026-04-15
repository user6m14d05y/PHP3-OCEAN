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
            $table->foreignId('product_id')->constrained('products', 'product_id')->cascadeOnDelete();
            $table->unsignedBigInteger('variant_id')->nullable(); // Nếu null = áp dụng cho tất cả variant
            $table->string('title')->default('Flash Sale');
            $table->text('description')->nullable();
            $table->unsignedInteger('total_stock');        // Tổng số lượng được bán
            $table->unsignedInteger('sold_count')->default(0); // Đã bán (sync từ Queue)
            $table->decimal('sale_price', 15, 2);          // Giá bán flash sale
            $table->decimal('original_price', 15, 2);      // Giá gốc (để hiện % giảm)
            $table->unsignedInteger('max_per_user')->default(1); // Giới hạn mỗi user mua tối đa
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->enum('status', ['draft', 'active', 'ended', 'cancelled'])->default('draft');
            $table->timestamps();

            $table->index(['status', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flash_sales');
    }
};
