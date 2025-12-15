<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Check if password_hash column exists and rename it
        if (Schema::hasColumn('users', 'password_hash')) {
            Schema::table('users', function (Blueprint $table) {
                $table->renameColumn('password_hash', 'password');
            });
        }
        
        // Also update other old columns if they exist
        if (Schema::hasColumn('users', 'avatar_path')) {
            Schema::table('users', function (Blueprint $table) {
                $table->renameColumn('avatar_path', 'avatar');
            });
        }
        
        // Add email_verified_at if it doesn't exist
        if (!Schema::hasColumn('users', 'email_verified_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->timestamp('email_verified_at')->nullable()->after('password');
            });
        }
        
        // Add updated_at if it doesn't exist
        if (!Schema::hasColumn('users', 'updated_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            });
        }
        
        // Remove old columns if they exist
        if (Schema::hasColumn('users', 'is_active')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('is_active');
            });
        }
        
        if (Schema::hasColumn('users', 'activation_token')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('activation_token');
            });
        }
        
        if (Schema::hasColumn('users', 'activation_expires')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('activation_expires');
            });
        }
    }

    public function down(): void
    {
        // Reverse the changes if needed
        if (Schema::hasColumn('users', 'password')) {
            Schema::table('users', function (Blueprint $table) {
                $table->renameColumn('password', 'password_hash');
            });
        }
        
        if (Schema::hasColumn('users', 'avatar')) {
            Schema::table('users', function (Blueprint $table) {
                $table->renameColumn('avatar', 'avatar_path');
            });
        }
    }
};

