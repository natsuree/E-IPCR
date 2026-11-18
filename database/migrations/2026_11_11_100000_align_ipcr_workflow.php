<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ipcr_items', fn (Blueprint $table) => $table->text('evidence_url')->nullable());
        $this->routePendingRecords();
    }

    public function routePendingRecords(): void
    {
        // Route existing in-flight records to the first outstanding approval of this submission.
        DB::table('ipcrs')->whereIn('status', ['submitted', 'with_dean', 'with_hr'])
            ->chunkById(100, function ($records) {
                foreach ($records as $record) {
                    $reviews = DB::table('ipcr_reviews')->where('ipcr_id', $record->id);
                    $start = (clone $reviews)->whereIn('action', ['submitted', 'resubmitted'])->max('id');
                    $approved = $start ? $reviews->where('id', '>', $start)->where('action', 'approved')->pluck('role') : collect();
                    foreach (['depthead' => 'submitted', 'dean' => 'with_dean', 'vpaa' => 'with_vpaa', 'hr' => 'with_hr'] as $role => $status) {
                        if (! $approved->contains($role)) {
                            DB::table('ipcrs')->where('id', $record->id)->where('status', $record->status)->update(['status' => $status]);
                            break;
                        }
                    }
                }
            });
    }

    public function down(): void
    {
        // The old application cannot safely interpret VPAA reviews or recover earlier queue positions.
        throw new RuntimeException('Restore a pre-upgrade backup to roll back the workflow and evidence migration.');
    }
};
