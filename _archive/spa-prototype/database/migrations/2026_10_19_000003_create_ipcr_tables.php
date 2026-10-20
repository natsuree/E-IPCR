<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ipcr_records', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('owner_id')->index();
            $table->string('name');
            $table->string('position');
            $table->string('unit');
            $table->string('department');
            $table->string('type');
            $table->string('period')->index();
            $table->string('status')->default('Draft')->index();
            $table->string('created');
            $table->string('updated');
            $table->text('commitment');
            $table->text('development');
            $table->json('mfo')->nullable();
            $table->string('ratee_date')->nullable();
            $table->string('discussion_date')->nullable();
            $table->json('rating_settings');
            $table->json('final_rating')->nullable();

            $table->foreign('owner_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('ipcr_rows', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('record_id')->index();
            $table->string('category');
            $table->boolean('applicable')->default(true);
            $table->string('program')->default('');
            $table->string('outcome')->default('');
            $table->string('indicator_type')->default('Function');
            $table->text('indicator')->default('');
            $table->text('target')->default('');
            $table->text('actual')->default('');
            $table->json('q')->nullable();
            $table->json('e')->nullable();
            $table->json('t')->nullable();
            $table->text('remarks')->default('');

            $table->foreign('record_id')->references('id')->on('ipcr_records')->cascadeOnDelete();
        });

        Schema::create('ipcr_history', function (Blueprint $table) {
            $table->id();
            $table->string('record_id')->index();
            $table->string('at');
            $table->string('actor');
            $table->string('role');
            $table->string('action');
            $table->text('comment')->default('');

            $table->foreign('record_id')->references('id')->on('ipcr_records')->cascadeOnDelete();
        });

        Schema::create('ipcr_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('record_id')->index();
            $table->string('at');
            $table->json('snapshot');

            $table->foreign('record_id')->references('id')->on('ipcr_records')->cascadeOnDelete();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('user')->index();
            $table->text('text');
            $table->string('record')->nullable();
            $table->string('at');
            $table->boolean('read')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('ipcr_submissions');
        Schema::dropIfExists('ipcr_history');
        Schema::dropIfExists('ipcr_rows');
        Schema::dropIfExists('ipcr_records');
    }
};
