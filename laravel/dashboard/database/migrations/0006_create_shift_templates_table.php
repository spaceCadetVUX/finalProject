<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shift_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');                                    // "Ca sáng", "Ca hành chính"
            $table->time('check_in_time');                             // 08:00:00
            $table->time('check_out_time');                            // 12:00:00
            $table->unsignedTinyInteger('late_tolerance')->default(15); // phút
            $table->string('color', 7)->default('#3b82f6');            // hex color cho calendar
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_templates');
    }
};
