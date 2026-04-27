<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class TiProjectSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@example.com')->first();

        if (! $admin) {
            $admin = User::query()->first();
        }

        if (! $admin) {
            return;
        }

        $projectTopics = [
            'Implementasi SSO Internal',
            'Migrasi Server Aplikasi',
            'Pembangunan Data Warehouse',
            'Integrasi API Pembayaran',
            'Upgrade Infrastruktur Jaringan',
            'Pengembangan Mobile App Karyawan',
            'Dashboard Monitoring Operasional',
            'Automasi Backup Harian',
            'Implementasi SIEM',
            'Refactor Aplikasi Helpdesk',
            'Portal Layanan TI',
            'Sistem Notifikasi Multi Channel',
            'Peningkatan Performa Database',
            'Integrasi Single Source of Truth',
            'Digitalisasi Proses Pengadaan TI',
            'Implementasi CI/CD Pipeline',
            'Hardening Keamanan Aplikasi',
            'Sistem Manajemen Aset TI',
            'Modernisasi Aplikasi Legacy',
            'Pusat Dokumentasi Teknis',
        ];

        $stepTemplates = [
            'Analisis kebutuhan',
            'Desain solusi',
            'Development',
            'Testing',
            'UAT',
            'Deployment',
            'Monitoring pasca rilis',
        ];

        $statusOptions = ['planned', 'in_progress', 'completed', 'delayed'];
        $categoryOptions = Project::CATEGORIES;

        foreach ($projectTopics as $index => $topic) {
            $baseDate = Carbon::now()->subDays(40 - ($index * 2));
            $periodStart = $baseDate->copy();
            $periodEnd = $baseDate->copy()->addDays(45);
            $deadline = $periodEnd->copy()->subDays(3);
            $projectStatus = Arr::random($statusOptions);

            $project = Project::query()->create([
                'user_id' => $admin->id,
                'name' => "Project TI {$topic}",
                'category' => Arr::random($categoryOptions),
                'description' => "Inisiatif TI untuk {$topic} agar proses bisnis lebih efisien dan terukur.",
                'url' => 'https://ti.example.local/project-' . ($index + 1),
                'pic' => 'Tim TI Internal',
                'deadline' => $deadline->toDateString(),
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'status' => $projectStatus,
            ]);

            $stepCount = random_int(4, 7);
            $completedThreshold = random_int(1, $stepCount);

            for ($i = 0; $i < $stepCount; $i++) {
                $startDate = $periodStart->copy()->addDays($i * 5);
                $endDate = $startDate->copy()->addDays(3);
                $stepDeadline = $endDate->copy()->addDays(1);

                $stepStatus = $i < $completedThreshold
                    ? 'completed'
                    : Arr::random(['planned', 'in_progress', 'delayed']);

                $project->steps()->create([
                    'sort_order' => $i,
                    'step_name' => $stepTemplates[$i % count($stepTemplates)],
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                    'deadline' => $stepDeadline->toDateString(),
                    'description' => "Aktivitas {$topic} tahap " . ($i + 1),
                    'pic' => 'Tim TI',
                    'follow_up' => 'Monitoring dan evaluasi progres mingguan.',
                    'status' => $stepStatus,
                ]);
            }
        }
    }
}
