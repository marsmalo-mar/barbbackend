<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Use raw SQL for MySQL compatibility (renameColumn may not work in all MySQL versions)
        
        // Rename password_hash to password if it exists
        $columns = DB::select("SHOW COLUMNS FROM users LIKE 'password_hash'");
        if (count($columns) > 0) {
            DB::statement('ALTER TABLE users CHANGE password_hash password VARCHAR(255) NOT NULL');
        }
        
        // Rename avatar_path to avatar if it exists
        $columns = DB::select("SHOW COLUMNS FROM users LIKE 'avatar_path'");
        if (count($columns) > 0) {
            DB::statement('ALTER TABLE users CHANGE avatar_path avatar VARCHAR(255) NULL');
        }
        
        // Add email_verified_at if it doesn't exist
        $columns = DB::select("SHOW COLUMNS FROM users LIKE 'email_verified_at'");
        if (count($columns) == 0) {
            DB::statement('ALTER TABLE users ADD email_verified_at TIMESTAMP NULL AFTER password');
        }
        
        // Add updated_at if it doesn't exist
        $columns = DB::select("SHOW COLUMNS FROM users LIKE 'updated_at'");
        if (count($columns) == 0) {
            DB::statement('ALTER TABLE users ADD updated_at TIMESTAMP NULL AFTER created_at');
        }
        
        // Drop old columns if they exist
        $columns = DB::select("SHOW COLUMNS FROM users LIKE 'is_active'");
        if (count($columns) > 0) {
            DB::statement('ALTER TABLE users DROP COLUMN is_active');
        }
        
        $columns = DB::select("SHOW COLUMNS FROM users LIKE 'activation_token'");
        if (count($columns) > 0) {
            DB::statement('ALTER TABLE users DROP COLUMN activation_token');
        }
        
        $columns = DB::select("SHOW COLUMNS FROM users LIKE 'activation_expires'");
        if (count($columns) > 0) {
            DB::statement('ALTER TABLE users DROP COLUMN activation_expires');
        }
    }

    public function down(): void
    {
        // Reverse if needed
        $columns = DB::select("SHOW COLUMNS FROM users LIKE 'password'");
        if (count($columns) > 0) {
            DB::statement('ALTER TABLE users CHANGE password password_hash VARCHAR(255) NOT NULL');
        }
        
        $columns = DB::select("SHOW COLUMNS FROM users LIKE 'avatar'");
        if (count($columns) > 0) {
            DB::statement('ALTER TABLE users CHANGE avatar avatar_path VARCHAR(255) NULL');
        }
    }
};

