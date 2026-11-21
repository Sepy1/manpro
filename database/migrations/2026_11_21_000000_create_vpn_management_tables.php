<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vpn_clients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('username')->unique();
            $table->string('common_name')->unique();
            $table->string('department')->nullable();
            $table->string('branch_office')->nullable();
            $table->string('inventory_number')->nullable();
            $table->ipAddress('vpn_ip')->unique();
            $table->date('certificate_issued_at')->nullable();
            $table->date('certificate_expires_at')->nullable();
            $table->string('status', 20)->default('active');
            $table->string('certificate_status', 20)->default('not_generated');
            $table->timestamp('last_connected_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'certificate_status']);
        });

        Schema::create('vpn_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vpn_client_id')->nullable()->constrained('vpn_clients')->nullOnDelete();
            $table->string('common_name');
            $table->ipAddress('real_ip')->nullable();
            $table->ipAddress('vpn_ip')->nullable();
            $table->timestamp('connected_at');
            $table->timestamp('disconnected_at')->nullable();
            $table->unsignedBigInteger('bytes_received')->default(0);
            $table->unsignedBigInteger('bytes_sent')->default(0);
            $table->string('disconnect_reason')->nullable();
            $table->timestamps();
            $table->index(['common_name', 'connected_at']);
            $table->index(['connected_at', 'disconnected_at']);
        });

        Schema::create('vpn_admin_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vpn_client_id')->nullable()->constrained('vpn_clients')->nullOnDelete();
            $table->string('action');
            $table->text('description')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->timestamps();
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vpn_admin_activities');
        Schema::dropIfExists('vpn_connections');
        Schema::dropIfExists('vpn_clients');
    }
};
