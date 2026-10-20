<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Indexes for the grouped analytics queries (per period, per unit, per reviewer step). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ipcrs', function (Blueprint $table) {
            $table->index(['rating_period_id', 'status'], 'ipcrs_period_status_index');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index(['department_id', 'role', 'active'], 'users_department_role_active_index');
            $table->index(['college_id', 'role', 'active'], 'users_college_role_active_index');
        });

        Schema::table('ipcr_reviews', function (Blueprint $table) {
            $table->index(['ipcr_id', 'created_at'], 'ipcr_reviews_ipcr_created_index');
            $table->index(['role', 'action'], 'ipcr_reviews_role_action_index');
        });

        Schema::table('ipcr_items', function (Blueprint $table) {
            $table->index(['ipcr_id', 'section'], 'ipcr_items_ipcr_section_index');
        });
    }

    public function down(): void
    {
        Schema::table('ipcr_items', fn (Blueprint $table) => $table->dropIndex('ipcr_items_ipcr_section_index'));
        Schema::table('ipcr_reviews', function (Blueprint $table) {
            $table->dropIndex('ipcr_reviews_role_action_index');
            $table->dropIndex('ipcr_reviews_ipcr_created_index');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_college_role_active_index');
            $table->dropIndex('users_department_role_active_index');
        });
        Schema::table('ipcrs', fn (Blueprint $table) => $table->dropIndex('ipcrs_period_status_index'));
    }
};
