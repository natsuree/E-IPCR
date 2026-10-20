<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ipcrs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rating_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ipcr_template_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('draft')->index();
            $table->decimal('final_rating', 4, 2)->nullable();
            $table->string('adjectival_rating')->nullable();
            $table->json('section_averages')->nullable();
            $table->json('rating_basis')->nullable(); // settings used for the final rating
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'rating_period_id']);
        });

        // Items are copied from the template when the IPCR is created, so later
        // template edits never change an IPCR that already exists.
        Schema::create('ipcr_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipcr_id')->constrained()->cascadeOnDelete();
            $table->string('section', 20);
            $table->string('program_outcome')->nullable();
            $table->text('indicator');
            $table->text('target');
            $table->text('actual_accomplishment')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('ipcr_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipcr_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role', 20);
            $table->string('action', 20); // submitted | resubmitted | approved | returned | completed
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipcr_reviews');
        Schema::dropIfExists('ipcr_items');
        Schema::dropIfExists('ipcrs');
    }
};
