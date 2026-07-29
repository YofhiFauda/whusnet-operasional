<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$roles = ['Helpdesk', 'NOC', 'FOP', 'Teknisi'];
foreach ($roles as $role) {
    $r = Role::where('name', $role)->orWhere('code', strtolower($role))->first();
    if ($r) {
        $user = User::where('role_id', $r->id)->first();
        if ($user) {
            echo strtoupper($role).' EMAIL: '.$user->email."\n";
        }
    }
}
