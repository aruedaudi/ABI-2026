<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('academic_process_windows', 'requires_evaluation')) {
            return;
        }

        Schema::table('academic_process_windows', function (Blueprint $table) {
            $table->boolean('requires_evaluation')->default(false)->after('is_enabled');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('academic_process_windows', 'requires_evaluation')) {
            return;
        }

        Schema::table('academic_process_windows', function (Blueprint $table) {
            $table->dropColumn('requires_evaluation');
        });
    }
};