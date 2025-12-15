<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('services', function (Blueprint $table) {
            // Add new time_range column
            $table->string('time_range', 50)->nullable()->after('price');
        });

        // Migrate existing duration data to time_range format
        // Convert minutes to a default time range format
        // For example: 30 minutes -> "8:00am - 8:30am"
        $services = \App\Models\Service::all();
        foreach ($services as $service) {
            $duration = $service->duration ?? 30;
            $endMinutes = $duration;
            $endHour = 8 + intval($endMinutes / 60);
            $endMin = $endMinutes % 60;
            $endPeriod = $endHour >= 12 ? 'pm' : 'am';
            if ($endHour > 12) $endHour -= 12;
            if ($endHour == 0) $endHour = 12;
            $endTime = sprintf('%d:%02d%s', $endHour, $endMin, $endPeriod);
            $service->time_range = "8:00am - {$endTime}";
            // Temporarily disable timestamps to avoid issues
            $service->timestamps = false;
            $service->save();
        }

        // Drop the old duration column
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('duration');
        });
    }

    public function down()
    {
        Schema::table('services', function (Blueprint $table) {
            // Add duration column back
            $table->integer('duration')->nullable()->after('price');
        });

        // Set default duration for existing records
        \App\Models\Service::query()->update(['duration' => 30]);

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('time_range');
        });
    }
};

