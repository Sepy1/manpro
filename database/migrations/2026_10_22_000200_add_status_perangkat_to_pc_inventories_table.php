<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pc_inventories', function (Blueprint $table) {
            $table->string('status_perangkat', 30)
                ->default('Inventaris')
                ->after('jabatan')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('pc_inventories', function (Blueprint $table) {
            $table->dropIndex(['status_perangkat']);
            $table->dropColumn('status_perangkat');
        });
    }
};
