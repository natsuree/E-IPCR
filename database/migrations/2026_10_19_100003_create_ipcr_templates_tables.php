<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ipcr_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rating_period_id')->constrained()->cascadeOnDelete();
            $table->string('employee_type', 20); // teaching | nonteaching
            $table->unsignedInteger('version');
            $table->string('status', 20)->default('draft'); // draft | published
            $table->timestamp('published_at')->nullable();
            $table->foreignId('copied_from_id')->nullable()->constrained('ipcr_templates')->nullOnDelete();
            $table->string('commitment_period');
            $table->text('commitment_statement');
            $table->json('rating_scale');
            $table->json('signatories');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['rating_period_id', 'employee_type', 'version']);
        });

        Schema::create('template_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipcr_template_id')->constrained()->cascadeOnDelete();
            $table->string('section', 20); // strategic | core | support
            $table->string('program_outcome')->nullable();
            $table->text('indicator');
            $table->text('target');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('template_change_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipcr_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_change_logs');
        Schema::dropIfExists('template_items');
        Schema::dropIfExists('ipcr_templates');
    }
};
