<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Checking service_provider_reviews table structure:\n";
try {
    $columns = DB::select('SHOW COLUMNS FROM service_provider_reviews');
    foreach ($columns as $column) {
        echo "- {$column->Field}: {$column->Type}\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\nChecking service_provider_stats table structure:\n";
try {
    $columns = DB::select('SHOW COLUMNS FROM service_provider_stats');
    foreach ($columns as $column) {
        echo "- {$column->Field}: {$column->Type}\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}