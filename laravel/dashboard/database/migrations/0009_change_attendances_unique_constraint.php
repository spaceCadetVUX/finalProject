<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            // MySQL uses the composite unique index to back the user_id FK,
            // so we must drop the FK first, then drop the index, then re-add the FK.
            $table->dropForeign(['user_id']);
            $table->dropUnique(['user_id', 'work_date']);
            // Re-add FK (creates its own supporting index on user_id)
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            // Allow multiple shifts per day: unique per (employee, date, shift)
            $table->unique(['user_id', 'work_date', 'shift_schedule_id']);
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropUnique(['user_id', 'work_date', 'shift_schedule_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['user_id', 'work_date']);
        });
    }
};
