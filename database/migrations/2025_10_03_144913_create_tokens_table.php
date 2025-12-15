<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('token', 36)->unique();
            $table->timestamp('created_at')->useCurrent();
            $table->dateTime('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tokens');
    }
};
