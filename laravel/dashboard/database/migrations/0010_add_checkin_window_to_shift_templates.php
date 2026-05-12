<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('shift_templates', function (Blueprint $table) {
            // Phút được phép vào sớm trước giờ vào (mặc định 60 phút)
            $table->unsignedSmallInteger('checkin_before')->default(60)->after('late_tolerance');
            // Phút được phép vào trễ sau giờ vào (mặc định 60 phút)
            $table->unsignedSmallInteger('checkin_after')->default(60)->after('checkin_before');
        });
    }

    public function down(): void
    {
        Schema::table('shift_templates', function (Blueprint $table) {
            $table->dropColumn(['checkin_before', 'checkin_after']);
        });
    }
};
