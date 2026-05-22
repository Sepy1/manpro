<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kas_kantor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kantor_id')->constrained('kantors')->cascadeOnDelete();
            $table->string('kode_kas', 80);
            $table->string('nama_kas')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['kantor_id', 'kode_kas']);
        });

        if (Schema::hasColumn('kantors', 'kantor_kas')) {
            $rows = DB::table('kantors')->whereNotNull('kantor_kas')->get(['id', 'kantor_kas']);
            foreach ($rows as $row) {
                $label = trim((string) $row->kantor_kas);
                if ($label === '') {
                    continue;
                }
                DB::table('kas_kantor')->insert([
                    'kantor_id' => $row->id,
                    'kode_kas' => '001',
                    'nama_kas' => $label,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            Schema::table('kantors', function (Blueprint $table) {
                $table->dropColumn('kantor_kas');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kas_kantor');

        Schema::table('kantors', function (Blueprint $table) {
            if (! Schema::hasColumn('kantors', 'kantor_kas')) {
                $table->string('kantor_kas')->nullable()->after('nama_kantor');
            }
        });
    }
};
