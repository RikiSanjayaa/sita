<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void
  {
    Schema::table('mentorship_chat_threads', function (Blueprint $table) {
      $table->string('type', 30)->default('pembimbing')->after('student_user_id');
      $table->unsignedBigInteger('context_id')->nullable()->after('type');
      $table->string('label')->nullable()->after('context_id');

      // Drop the old unique index on student_user_id (allow multiple threads per student)
      $table->dropUnique('mentorship_chat_threads_student_user_id_unique');

      // Add composite index
      $table->index(['student_user_id', 'type', 'context_id'], 'threads_student_type_context_idx');
    });

    Schema::create('mentorship_chat_thread_participants', function (Blueprint $table) {
      $table->id();
      $table->foreignId('thread_id')->constrained('mentorship_chat_threads')->cascadeOnDelete();
      $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
      $table->string('role', 30)->default('member'); // student, examiner, advisor
      $table->timestamps();

      $table->unique(['thread_id', 'user_id']);
    });
  }

  public function down(): void
  {
    Schema::dropIfExists('mentorship_chat_thread_participants');

    Schema::table('mentorship_chat_threads', function (Blueprint $table) {
      $table->dropIndex('threads_student_type_context_idx');
      $table->dropColumn(['type', 'context_id', 'label']);
      $table->unique('student_user_id');
    });
  }
};
