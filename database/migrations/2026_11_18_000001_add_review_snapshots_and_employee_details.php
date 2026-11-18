<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive only. Existing rows keep their values; the new columns stay empty for
 * them, and the application labels those records as having no saved details.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Each approve/return/complete decision keeps the per-target ratings and remarks it was made with.
        Schema::table('ipcr_reviews', function (Blueprint $table) {
            $table->json('item_ratings')->nullable();
        });

        // Employee details as they were when the IPCR was (re)submitted.
        Schema::table('ipcrs', function (Blueprint $table) {
            $table->timestamp('snapshot_taken_at')->nullable();
            $table->string('snapshot_name')->nullable();
            $table->string('snapshot_employee_id', 50)->nullable();
            $table->string('snapshot_position')->nullable();
            $table->string('snapshot_employee_type', 20)->nullable();
            $table->string('snapshot_designation')->nullable();
            $table->unsignedBigInteger('snapshot_college_id')->nullable()->index();
            $table->unsignedBigInteger('snapshot_department_id')->nullable()->index();
            $table->string('snapshot_college_name')->nullable();
            $table->string('snapshot_department_name')->nullable();
        });

        // Employee-added targets removed after the IPCR was reviewed are kept, marked removed.
        Schema::table('ipcr_items', function (Blueprint $table) {
            $table->timestamp('removed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ipcr_items', fn (Blueprint $table) => $table->dropColumn('removed_at'));
        Schema::table('ipcrs', function (Blueprint $table) {
            $table->dropIndex(['snapshot_college_id']);
            $table->dropIndex(['snapshot_department_id']);
            $table->dropColumn([
                'snapshot_taken_at', 'snapshot_name', 'snapshot_employee_id', 'snapshot_position', 'snapshot_employee_type',
                'snapshot_designation', 'snapshot_college_id', 'snapshot_department_id', 'snapshot_college_name', 'snapshot_department_name',
            ]);
        });
        Schema::table('ipcr_reviews', fn (Blueprint $table) => $table->dropColumn('item_ratings'));
    }
};
