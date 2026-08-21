<?php

use App\Enums\ScopeType;
use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use App\Services\UserScopeManagementService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Get NOC and FOP roles
$nocRole = Role::where('code', 'noc')->first();
$fopRole = Role::where('code', 'fop')->first();

if (! $nocRole) {
    echo "❌ Error: NOC role (code: noc) not found in database.\n";
    exit(1);
}

if (! $fopRole) {
    echo "❌ Error: FOP role (code: fop) not found in database.\n";
    exit(1);
}

$usersToCreate = [
    [
        'email' => 'noc1@gmail.com',
        'name' => 'NOC User 1',
        'phone' => '081111111111',
        'role_id' => $nocRole->id,
    ],
    [
        'email' => 'noc2@gmail.com',
        'name' => 'NOC User 2',
        'phone' => '081111111112',
        'role_id' => $nocRole->id,
    ],
    [
        'email' => 'fop1@gmail.com',
        'name' => 'FOP User 1',
        'phone' => '081111111113',
        'role_id' => $fopRole->id,
    ],
    [
        'email' => 'fop2@gmail.com',
        'name' => 'FOP User 2',
        'phone' => '081111111114',
        'role_id' => $fopRole->id,
    ],
];

$scopeService = app(UserScopeManagementService::class);

foreach ($usersToCreate as $userData) {
    $user = User::updateOrCreate(
        ['email' => $userData['email']],
        [
            'name' => $userData['name'],
            'phone' => $userData['phone'],
            'password' => Hash::make('password'),
            'status' => UserStatus::ACTIVE,
            'role_id' => $userData['role_id'],
            'email_verified_at' => now(),
        ]
    );

    // Sync scope to 'all_pop' (full access)
    $scopeService->syncUserRoleScope($user, ScopeType::ALL_POP->value, []);

    echo "✅ User {$userData['email']} ({$userData['name']}) created/updated with role: {$user->role->name} and full access scope (all_pop).\n";
}

echo "🎉 Seeding FOP & NOC users completed successfully.\n";
