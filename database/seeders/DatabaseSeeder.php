<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::create([
            'name' => 'AJ Ice Creams',
            'gst_number' => null,
            'full_address' => null,
            'contact_number' => null,
            'opening_balance' => 0,
        ]);

        User::create([
            'company_id' => $company->id,
            'name' => 'Super Admin',
            'phone_number' => '9999999999',
            'password' => Hash::make('123456'),
            'type' => 'admin',
        ]);

        $this->command->info('Seeded company "AJ Ice Creams" and admin login 9999999999 / 123456');
    }
}
