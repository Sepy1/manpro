<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pc_inventories', function (Blueprint $table) {
            $table->string('kode_kantor', 50)->change();
            $table->foreignId('kantor_id')->nullable()->after('kode_kantor')->constrained('kantors')->nullOnDelete();
            $table->foreignId('kas_kantor_id')->nullable()->after('kantor_id')->constrained('kas_kantor')->nullOnDelete();
            $table->string('kode_kas', 80)->nullable()->after('kas_kantor_id');
        });
    }

    public function down(): void
    {
        Schema::table('pc_inventories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('kas_kantor_id');
            $table->dropConstrainedForeignId('kantor_id');
            $table->dropColumn('kode_kas');
            $table->string('kode_kantor', 3)->change();
        });
    }
};
