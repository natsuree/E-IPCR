<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('template_items', fn (Blueprint $table) => $table->unsignedBigInteger('copied_item_id')->nullable());
        Schema::table('ipcr_items', fn (Blueprint $table) => $table->boolean('retired')->default(false));
        $groups = \Illuminate\Support\Facades\DB::table('ipcrs')->join('users', 'users.id', '=', 'ipcrs.user_id')
            ->where('ipcrs.status', 'draft')->whereNull('ipcrs.deleted_at')->whereNotNull('users.employee_type')
            ->distinct()->get(['ipcrs.rating_period_id', 'users.employee_type']);
        foreach ($groups as $group) {
            app(\App\Services\DraftTemplateUpdate::class)->forPeriod($group->rating_period_id, $group->employee_type);
        }
    }

    public function down(): void
    {
        Schema::table('ipcr_items', fn (Blueprint $table) => $table->dropColumn('retired'));
        Schema::table('template_items', fn (Blueprint $table) => $table->dropColumn('copied_item_id'));
    }
};
