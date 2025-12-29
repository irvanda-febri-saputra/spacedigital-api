<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create super admin user
        User::updateOrCreate(
            ['email' => 'irvandafebrisaputra02@gmail.com'],
            [
                'name' => 'Irvanda Febri Saputra',
                'email' => 'irvandafebrisaputra02@gmail.com',
                'password' => Hash::make('admin123'), // Change this in production!
                'role' => 'super_admin',
                'status' => 'active',
            ]
        );

        // Optional: Create a demo user
        User::updateOrCreate(
            ['email' => 'user@spacedigital.com'],
            [
                'name' => 'Demo User',
                'email' => 'user@spacedigital.com',
                'password' => Hash::make('user123'), // Change this in production!
                'role' => 'user',
                'status' => 'active',
            ]
        );
    }
}
