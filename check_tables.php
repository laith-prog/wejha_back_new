<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Check if roles table exists
$tables = DB::select('SHOW TABLES');
echo "Tables in database:\n";
foreach ($tables as $table) {
    foreach ($table as $key => $value) {
        echo "- $value\n";
    }
}

// Check structure of roles table if it exists
if (DB::getSchemaBuilder()->hasTable('roles')) {
    echo "\nStructure of roles table:\n";
    $columns = DB::select('SHOW COLUMNS FROM roles');
    foreach ($columns as $column) {
        echo "- {$column->Field}: {$column->Type}\n";
    }
} 