<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bug #6 Fix: OTP column quá ngắn (6 chars) để chứa hashed OTP (60+ chars).
     * Tăng lên 255 để chứa bcrypt hash.
     */
    public function up(): void
    {
        Schema::table('password_resets_otp', function (Blueprint $table) {
            $table->string('otp', 255)->change();
        });
    }

    public function down(): void
    {
        Schema::table('password_resets_otp', function (Blueprint $table) {
            $table->string('otp', 6)->change();
        });
    }
};
