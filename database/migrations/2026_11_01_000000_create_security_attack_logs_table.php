<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_attack_logs', function (Blueprint $table) {
            $table->id();
            $table->string('device');
            $table->string('severity', 50)->index();
            $table->text('attack');
            $table->string('attackid', 100);
            $table->string('subtype', 100);
            $table->string('action', 50);
            $table->string('srcip', 45);
            $table->unsignedSmallInteger('srcport');
            $table->string('dstip', 45);
            $table->unsignedSmallInteger('dstport');
            $table->string('service', 100);
            $table->string('method', 50)->default('-');
            $table->text('url');
            $table->string('event_time', 100)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_attack_logs');
    }
};
