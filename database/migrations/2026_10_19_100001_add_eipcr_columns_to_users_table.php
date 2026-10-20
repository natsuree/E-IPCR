<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('teaching')->index();
            $table->string('employee_type', 20)->nullable(); // teaching | nonteaching
            $table->string('position')->nullable();
            $table->foreignId('college_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->string('employee_id', 50)->nullable()->unique();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropConstrainedForeignId('college_id');
            $table->dropSoftDeletes();
            $table->dropUnique(['employee_id']);
            $table->dropColumn(['role', 'employee_type', 'position', 'active', 'employee_id']);
        });
    }
};
