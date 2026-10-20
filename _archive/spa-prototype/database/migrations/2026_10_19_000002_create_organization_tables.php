<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name')->unique();
            $table->json('departments');
            $table->timestamps();
        });

        Schema::create('evaluation_periods', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('start');
            $table->string('end');
            $table->boolean('active')->default(false);
            $table->timestamps();
        });

        Schema::create('evaluation_period_user', function (Blueprint $table) {
            $table->string('period_id');
            $table->string('user_id');
            $table->primary(['period_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->json('weights');
            $table->json('scale');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('evaluation_period_user');
        Schema::dropIfExists('evaluation_periods');
        Schema::dropIfExists('units');
    }
};
