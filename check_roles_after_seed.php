<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$roles = DB::table('roles')->get();

echo "Roles in database:\n";
foreach ($roles as $role) {
    echo "ID: {$role->id}, Name: {$role->name}, Display Name: {$role->display_name}\n";
} 