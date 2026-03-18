<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // Create Admin User
        User::updateOrCreate(
            ['mobile' => '9999999999'],
            [
                'name'       => 'Admin',
                'mobile'     => '9999999999',
                'password'   => Hash::make('admin123'),
                'role'       => 'admin',
                'commission' => 0,
            ]
        );

        $this->command->info('Admin user created successfully.');
        $this->command->info('Mobile   : 9999999999');
        $this->command->info('Password : admin123');
    }
}