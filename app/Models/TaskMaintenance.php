<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskMaintenance extends Model
{
    protected $fillable = [
        'task_id',
        'kendala_teknis',
        'kabel',
        'modem',
        'patchcord',
        'sleeve',
        'lainnya',
        'opm_photo',
        'speedtest_photo',
    ];

    public function task()
    {
        return $this->belongsTo(Task::class);
    }
}
