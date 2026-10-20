<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('Teaching Employee');
            $table->string('position')->default('');
            $table->string('unit')->default('');
            $table->string('department')->default('');
            $table->string('type')->default('Teaching');
            $table->boolean('active')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'position', 'unit', 'department', 'type', 'active']);
        });
    }
};
