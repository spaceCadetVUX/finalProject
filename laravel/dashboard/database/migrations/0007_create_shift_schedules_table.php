<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shift_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_template_id')->constrained()->cascadeOnDelete();

            // assignee: 'department' | 'employee'
            $table->enum('assignee_type', ['department', 'employee']);
            $table->unsignedBigInteger('assignee_id');

            // Ngày trong tuần áp dụng: [1,2,3,4,5] (1=Thứ 2 … 7=CN, ISO-8601)
            $table->json('days_of_week');

            $table->date('start_date');
            $table->date('end_date')->nullable(); // null = vô thời hạn

            $table->boolean('is_active')->default(true);
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['assignee_type', 'assignee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shift_schedules');
    }
};
