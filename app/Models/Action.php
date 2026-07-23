<?php

namespace App\Models;

use App\Enums\ActionCode;
use App\Models\Concerns\RecordsAuditLogs;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'code',
    'name',
    'description',
])]
class Action extends Model
{
    use RecordsAuditLogs;

    protected string $auditModule = 'Master Aksi RBAC';

    protected array $auditEvents = ['created', 'updated', 'deleted'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'code' => ActionCode::class,
        ];
    }
}
