<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        \App\Models\User::create([
            'name' => 'Teknisi Utama',
            'email' => 'teknisi@smartfix.com',
            'password' => bcrypt('password123'), // Password dienkripsi
            'role' => 'technician'
        ]);
    }
}
