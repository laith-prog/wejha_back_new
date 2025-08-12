<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$searchControllerPath = __DIR__ . '/Modules/Community/app/Http/Controllers/SearchController.php';

if (file_exists($searchControllerPath)) {
    echo "SearchController exists at: $searchControllerPath\n";
    
    // Read the file content
    $content = file_get_contents($searchControllerPath);
    
    // Check if it uses area_size
    if (strpos($content, 'area_size') !== false) {
        echo "Found 'area_size' in SearchController\n";
        
        // Replace area_size with property_area
        $newContent = str_replace('area_size', 'property_area', $content);
        
        // Write the updated content back to the file
        file_put_contents($searchControllerPath, $newContent);
        
        echo "Updated SearchController to use 'property_area' instead of 'area_size'\n";
    } else {
        echo "SearchController does not use 'area_size'\n";
    }
} else {
    echo "SearchController does not exist at: $searchControllerPath\n";
} 