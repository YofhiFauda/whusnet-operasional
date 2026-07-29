<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$roles = Role::all();
foreach ($roles as $role) {
    echo 'Role: '.$role->name.' (code: '.$role->code.")\n";
}

$users = User::with('role')->take(5)->get();
foreach ($users as $user) {
    echo 'User: '.$user->email.' (role: '.($user->role ? $user->role->name : 'none').")\n";
}
