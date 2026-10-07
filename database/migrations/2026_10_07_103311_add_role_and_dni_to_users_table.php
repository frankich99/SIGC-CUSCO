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
        Schema::table('users', function (Blueprint $table) {
            $table->string('dni', 8)->nullable()->unique()->after('id');
            $table->string('paterno', 100)->nullable()->after('name');
            $table->string('materno', 100)->nullable()->after('paterno');
            $table->string('role', 20)->default('participante')->after('email');
            $table->string('phone', 20)->nullable()->after('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['dni', 'paterno', 'materno', 'role', 'phone']);
        });
    }
};
