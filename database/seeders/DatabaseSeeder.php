<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Admin User',
            'email' => 'it@bkkjateng.co.id',
            'role' => 'admin',
            'password' => Hash::make('bkkjtg123'),
        ]);

        User::factory()->create([
            'name' => 'Regular User',
            'email' => 'user1512412@bkkjateng.co.id',
            'role' => 'user',
            'password' => Hash::make('asd31d123d%!@#'),
        ]);

        $this->call(TiProjectSeeder::class);
    }
}
