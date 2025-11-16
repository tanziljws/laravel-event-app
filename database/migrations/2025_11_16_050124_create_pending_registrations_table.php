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
        Schema::create('pending_registrations', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique(); // Temporary token untuk verify OTP
            $table->string('name');
            $table->string('email')->index();
            $table->string('phone');
            $table->string('address');
            $table->enum('education', ['SD', 'SMP', 'SMA', 'SMK', 'D3', 'S1', 'S2', 'S3']);
            $table->string('password'); // Plain password, akan di-hash saat create user
            $table->string('otp_code', 6); // OTP code (plain text untuk comparison)
            $table->timestamp('otp_expires_at');
            $table->timestamp('created_at');
            $table->timestamp('expires_at'); // Token expires setelah 15 menit
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pending_registrations');
    }
};
