<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            $table->date('work_date');
            $table->timestamp('check_in_at')->nullable();
            $table->float('check_in_confidence')->nullable();
            $table->string('check_in_image')->nullable();
            $table->timestamp('check_out_at')->nullable();
            $table->float('check_out_confidence')->nullable();
            $table->string('check_out_image')->nullable();
            $table->enum('status', ['present', 'late', 'early_leave', 'absent', 'leave'])->default('absent');
            $table->string('note')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'work_date']); // 1 nhân viên - 1 bản ghi/ngày
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
