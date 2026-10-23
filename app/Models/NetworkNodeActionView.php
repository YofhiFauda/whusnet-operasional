<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Penanda "sudah dibuka" per user per node. Badge perangkat baru dihitung dari
 * aksi infrastruktur yang `performed_at`-nya lebih baru dari `seen_at` user ini.
 */
#[Fillable(['user_id', 'network_node_id', 'seen_at'])]
class NetworkNodeActionView extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'network_node_id' => 'integer',
            'seen_at' => 'datetime',
        ];
    }
}
