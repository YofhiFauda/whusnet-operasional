<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Komentar FOP/CS/BD pada satu percobaan penarikan alat. Append-only — tidak
 * ada jalur edit/hapus (jejak review). Lihat migrasi
 * `create_device_retrieval_comments_table`.
 */
#[Fillable([
    'task_device_retrieval_id',
    'user_id',
    'body',
])]
class DeviceRetrievalComment extends Model
{
    /**
     * @return BelongsTo<TaskDeviceRetrieval, $this>
     */
    public function retrieval(): BelongsTo
    {
        return $this->belongsTo(TaskDeviceRetrieval::class, 'task_device_retrieval_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
