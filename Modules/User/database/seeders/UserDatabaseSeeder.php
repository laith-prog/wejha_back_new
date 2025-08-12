<?php

namespace Modules\User\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            RolesTableSeeder::class,
        ]);

        // Add this block to create a user with a password
        \Modules\User\Entities\User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'fname' => 'Test',
                'lname' => 'User',
                'role_id' => 3,
                'password' => Hash::make('Password123'),
            ]
        );
    }
}
