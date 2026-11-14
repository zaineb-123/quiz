<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('classroom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('opens_at')->nullable()->comment('Null = open as soon as the exam is published');
            $table->timestamp('closes_at')->nullable()->comment('Null = no deadline');
            $table->timestamp('reveal_answers_at')->nullable()->comment('Null = correct answers visible right after submission');
            $table->timestamps();

            // The same exam can be reused in several classrooms, but only once per classroom.
            $table->unique(['exam_id', 'classroom_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_assignments');
    }
};
