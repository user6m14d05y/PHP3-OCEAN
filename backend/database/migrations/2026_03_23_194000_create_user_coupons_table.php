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
<<<<<<< HEAD
        // Schema::dropIfExists('user_coupons');
=======
        Schema::dropIfExists('user_coupons');
>>>>>>> origin/binhbc
        Schema::create('user_coupons', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('coupon_id');
<<<<<<< HEAD
            $table->integer('used_count')->default(0);
            $table->boolean('is_saved')->default(true);
=======
            $table->integer('used_count')->default(0); 
            $table->boolean('is_saved')->default(true); 
>>>>>>> origin/binhbc
            $table->timestamps();
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->foreign('coupon_id')->references('id')->on('coupons')->onDelete('cascade');
            $table->unique(['user_id', 'coupon_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_coupons');
    }
};
