<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$roles = DB::table('roles')->get();

echo "Available roles:\n";
foreach ($roles as $role) {
    echo "ID: {$role->id}, Name: {$role->name}\n";
} 