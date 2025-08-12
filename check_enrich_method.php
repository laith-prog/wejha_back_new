<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$searchControllerPath = __DIR__ . '/Modules/Community/app/Http/Controllers/SearchController.php';

if (file_exists($searchControllerPath)) {
    $content = file_get_contents($searchControllerPath);
    
    // Find the enrichVehicleListings method
    if (preg_match('/private\s+function\s+enrichVehicleListings\s*\([^)]*\)\s*\{([^{}]*(?:\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}[^{}]*)*)\}/s', $content, $matches)) {
        echo "Found enrichVehicleListings method:\n\n";
        echo $matches[0] . "\n\n";
        
        // Check if it contains a reference to the name column in users
        if (preg_match('/users.*name|name.*users/', $matches[0])) {
            echo "Method contains reference to 'name' column in users table\n";
        } else {
            echo "No direct reference to 'name' column in users table found in this method\n";
        }
    } else {
        echo "Could not find enrichVehicleListings method\n";
    }
} else {
    echo "SearchController does not exist at: $searchControllerPath\n";
} 