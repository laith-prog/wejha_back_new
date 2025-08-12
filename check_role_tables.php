<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Check user_role table
if (DB::getSchemaBuilder()->hasTable('user_role')) {
    echo "Structure of user_role table:\n";
    $columns = DB::select('SHOW COLUMNS FROM user_role');
    foreach ($columns as $column) {
        echo "- {$column->Field}: {$column->Type}\n";
    }
    
    // Get some sample data
    echo "\nSample data from user_role table:\n";
    $roles = DB::table('user_role')->get();
    foreach ($roles as $role) {
        echo "ID: {$role->id}, ";
        foreach ((array)$role as $key => $value) {
            if ($key !== 'id') {
                echo "$key: $value, ";
            }
        }
        echo "\n";
    }
}

// Check role_permission table
if (DB::getSchemaBuilder()->hasTable('role_permission')) {
    echo "\nStructure of role_permission table:\n";
    $columns = DB::select('SHOW COLUMNS FROM role_permission');
    foreach ($columns as $column) {
        echo "- {$column->Field}: {$column->Type}\n";
    }
}

// Check users table for role_id column
if (DB::getSchemaBuilder()->hasTable('users')) {
    echo "\nUsers table columns:\n";
    $columns = DB::select('SHOW COLUMNS FROM users');
    foreach ($columns as $column) {
        echo "- {$column->Field}: {$column->Type}\n";
    }
} 