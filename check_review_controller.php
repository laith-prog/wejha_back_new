<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$reviewControllerPath = __DIR__ . '/Modules/Community/app/Http/Controllers/ReviewController.php';

if (file_exists($reviewControllerPath)) {
    echo "ReviewController exists at: $reviewControllerPath\n";
    
    // Read the file content
    $content = file_get_contents($reviewControllerPath);
    
    // Check if it uses service_provider_id
    if (strpos($content, 'service_provider_id') !== false) {
        echo "Found 'service_provider_id' in ReviewController\n";
        
        // Replace service_provider_id with provider_id
        $newContent = str_replace('service_provider_id', 'provider_id', $content);
        
        // Write the updated content back to the file
        file_put_contents($reviewControllerPath, $newContent);
        
        echo "Updated ReviewController to use 'provider_id' instead of 'service_provider_id'\n";
    } else {
        echo "ReviewController does not use 'service_provider_id'\n";
    }
} else {
    echo "ReviewController does not exist at: $reviewControllerPath\n";
} 