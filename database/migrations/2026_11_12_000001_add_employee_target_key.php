<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ipcr_items', function (Blueprint $table) {
            $table->uuid('employee_target_key')->nullable();
            $table->unique(['ipcr_id', 'employee_target_key']);
        });
    }

    public function down(): void
    {
        Schema::table('ipcr_items', function (Blueprint $table) {
            $table->dropUnique(['ipcr_id', 'employee_target_key']);
            $table->dropColumn('employee_target_key');
        });
    }
};
