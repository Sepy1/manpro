<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vpn_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->json('allowed_destinations')->nullable();
            $table->timestamps();
        });

        Schema::table('vpn_clients', function (Blueprint $table) {
            $table->foreignId('vpn_group_id')->nullable()->after('branch_office')->constrained('vpn_groups')->nullOnDelete();
            $table->json('allowed_destinations')->nullable()->after('vpn_ip');
        });
    }

    public function down(): void
    {
        Schema::table('vpn_clients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vpn_group_id');
            $table->dropColumn('allowed_destinations');
        });
        Schema::dropIfExists('vpn_groups');
    }
};
