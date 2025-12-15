<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add email column if it doesn't exist
        if (!Schema::hasColumn('barbers', 'email')) {
            Schema::table('barbers', function (Blueprint $table) {
                $table->string('email', 255)->nullable()->after('name');
            });
        }
    }

    public function down(): void
    {
        Schema::table('barbers', function (Blueprint $table) {
            if (Schema::hasColumn('barbers', 'email')) {
                $table->dropColumn('email');
            }
        });
    }
};

