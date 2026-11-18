<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->string('designation')->nullable());
        Schema::table('ipcr_templates', function (Blueprint $table) {
            $table->foreignId('college_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('designation')->nullable();
        });
        Schema::table('rating_periods', fn (Blueprint $table) => $table->timestamp('roster_set_at')->nullable());
        Schema::create('period_employees', function (Blueprint $table) {
            $table->foreignId('rating_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['rating_period_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('period_employees');
        Schema::table('rating_periods', fn (Blueprint $t) => $t->dropColumn('roster_set_at'));
        Schema::table('ipcr_templates', function (Blueprint $t) {
            $t->dropConstrainedForeignId('college_id');
            $t->dropColumn('designation');
        });
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('designation'));
    }
};
