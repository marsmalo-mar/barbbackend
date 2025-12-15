<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('services', function (Blueprint $table) {
            // Add duration column back
            $table->integer('duration')->nullable()->after('price');
        });

        // Migrate existing time_range data to duration (default to 30 minutes)
        \App\Models\Service::query()->update(['duration' => 30]);

        // Drop the time_range column
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('time_range');
        });
    }

    public function down()
    {
        Schema::table('services', function (Blueprint $table) {
            // Add time_range column back
            $table->string('time_range', 50)->nullable()->after('price');
        });

        // Set default time_range for existing records
        \App\Models\Service::query()->update(['time_range' => '8:00am - 8:30am']);

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('duration');
        });
    }
};

