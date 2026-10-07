<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('dni', 8)->index();
            $table->string('nombres');
            $table->string('paterno');
            $table->string('materno')->nullable();
            $table->string('email');
            $table->string('phone', 20)->nullable();
            $table->string('status', 20)->default('inscrito'); // inscrito, en_curso, aprobado, desaprobado, cancelado
            $table->unsignedInteger('attended_sessions')->default(0);
            $table->decimal('final_grade', 4, 2)->nullable();
            $table->string('certificate_code')->nullable()->unique();
            $table->timestamps();

            $table->unique(['course_id', 'dni']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
