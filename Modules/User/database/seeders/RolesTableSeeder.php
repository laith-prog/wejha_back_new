<?php

namespace Modules\User\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RolesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if the roles table exists
        if (Schema::hasTable('roles')) {
            // Clear existing roles
            DB::table('roles')->truncate();
            
            // Insert roles
            DB::table('roles')->insert([
                [
                    'id' => 1,
                    'name' => 'admin',
                    'display_name' => 'Administrator',
                    'description' => 'System Administrator',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'id' => 2,
                    'name' => 'service_provider',
                    'display_name' => 'Service Provider',
                    'description' => 'Can publish listings and services',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'id' => 3,
                    'name' => 'customer',
                    'display_name' => 'Customer',
                    'description' => 'Regular user',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
            
            $this->command->info('Roles table seeded successfully.');
        } else {
            $this->command->error('Roles table does not exist.');
        }
    }
} 