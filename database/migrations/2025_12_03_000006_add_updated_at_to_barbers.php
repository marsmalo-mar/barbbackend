<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add updated_at column if it doesn't exist
        if (!Schema::hasColumn('barbers', 'updated_at')) {
            Schema::table('barbers', function (Blueprint $table) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            });
        }
        
        // Update existing records to have updated_at = created_at
        DB::statement('UPDATE barbers SET updated_at = created_at WHERE updated_at IS NULL');
    }

    public function down(): void
    {
        Schema::table('barbers', function (Blueprint $table) {
            if (Schema::hasColumn('barbers', 'updated_at')) {
                $table->dropColumn('updated_at');
            }
        });
    }
};

