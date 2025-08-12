<?php

// Load Laravel framework
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

// Check if customer role exists
$customerRole = DB::table('roles')->where('id', 3)->orWhere('name', 'customer')->first();

if (!$customerRole) {
    echo "Customer role not found. Creating it...\n";
    
    DB::table('roles')->insert([
        'id' => 3,
        'name' => 'customer',
        'display_name' => 'Customer',
        'description' => 'Can book services and manage their own bookings',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    
    echo "Customer role created successfully!\n";
} else {
    echo "Customer role already exists.\n";
}

// Display all roles
$roles = DB::table('roles')->get();
echo "\nAll roles in the database:\n";
foreach ($roles as $role) {
    echo "ID: {$role->id}, Name: {$role->name}, Display Name: {$role->display_name}\n";
}

echo "\nDone.\n"; 