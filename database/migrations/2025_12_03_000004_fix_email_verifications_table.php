<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Check if email_verifications table exists
        if (!Schema::hasTable('email_verifications')) {
            Schema::create('email_verifications', function (Blueprint $table) {
                $table->id();
                $table->string('email', 255);
                $table->string('token', 255);
                $table->timestamp('expires_at');
                $table->timestamp('created_at')->nullable();
                $table->index('email');
            });
        } else {
            // Add email column if it doesn't exist
            $columns = DB::select("SHOW COLUMNS FROM email_verifications LIKE 'email'");
            if (count($columns) == 0) {
                DB::statement('ALTER TABLE email_verifications ADD email VARCHAR(255) NOT NULL AFTER id');
                DB::statement('ALTER TABLE email_verifications ADD INDEX email_verifications_email_index (email)');
            }
            
            // Remove user_id if it exists (we're using email instead)
            $columns = DB::select("SHOW COLUMNS FROM email_verifications LIKE 'user_id'");
            if (count($columns) > 0) {
                // Drop foreign key first if it exists
                try {
                    DB::statement('ALTER TABLE email_verifications DROP FOREIGN KEY email_verifications_user_id_foreign');
                } catch (\Exception $e) {
                    // Foreign key might not exist, continue
                }
                DB::statement('ALTER TABLE email_verifications DROP COLUMN user_id');
            }
            
            // Add created_at if it doesn't exist
            $columns = DB::select("SHOW COLUMNS FROM email_verifications LIKE 'created_at'");
            if (count($columns) == 0) {
                DB::statement('ALTER TABLE email_verifications ADD created_at TIMESTAMP NULL');
            }
        }
    }

    public function down(): void
    {
        // Reverse if needed
        if (Schema::hasTable('email_verifications')) {
            $columns = DB::select("SHOW COLUMNS FROM email_verifications LIKE 'email'");
            if (count($columns) > 0) {
                DB::statement('ALTER TABLE email_verifications DROP COLUMN email');
            }
        }
    }
};

