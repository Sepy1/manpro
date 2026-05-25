<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('extern_cr_attachments', function (Blueprint $table) {
            $table->foreignId('extern_cr_history_id')
                ->nullable()
                ->after('extern_cr_id')
                ->constrained('extern_cr_histories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('extern_cr_attachments', function (Blueprint $table) {
            $table->dropForeign(['extern_cr_history_id']);
            $table->dropColumn('extern_cr_history_id');
        });
    }
};
