<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->unsignedBigInteger('manager_id')->nullable(); // FK thêm sau để tránh circular
            $table->time('check_in_time')->default('08:00:00');
            $table->time('check_out_time')->default('17:00:00');
            $table->unsignedTinyInteger('late_tolerance')->default(15); // phút
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
