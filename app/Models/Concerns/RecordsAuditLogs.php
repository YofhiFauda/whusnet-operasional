<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

trait RecordsAuditLogs
{
    protected static function bootRecordsAuditLogs(): void
    {
        static::created(function ($model): void {
            if (! $model->shouldAuditEvent('created')) {
                return;
            }

            $model->writeModelAuditLog('create', null, $model->auditAttributes());
        });

        static::updated(function ($model): void {
            if (! $model->shouldAuditEvent('updated')) {
                return;
            }

            $changed = array_values(array_diff(array_keys($model->getChanges()), $model->auditIgnoredFields()));

            if ($changed === []) {
                return;
            }

            $oldValues = [];
            $newValues = [];

            foreach ($changed as $field) {
                $oldValues[$field] = $model->getOriginal($field);
                $newValues[$field] = $model->getAttribute($field);
            }

            $model->writeModelAuditLog('update', $oldValues, $newValues);
        });

        static::deleted(function ($model): void {
            if (! $model->shouldAuditEvent('deleted')) {
                return;
            }

            $model->writeModelAuditLog('delete', $model->auditAttributes(), null);
        });
    }

    protected function shouldAuditEvent(string $event): bool
    {
        return in_array($event, $this->auditEvents(), true);
    }

    /**
     * @return array<int, string>
     */
    protected function auditEvents(): array
    {
        return property_exists($this, 'auditEvents')
            ? $this->auditEvents
            : ['updated', 'deleted'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function auditAttributes(): array
    {
        return Arr::except($this->attributesToArray(), $this->auditIgnoredFields());
    }

    /**
     * @return array<int, string>
     */
    protected function auditIgnoredFields(): array
    {
        $default = ['created_at', 'updated_at', 'remember_token'];
        $custom = property_exists($this, 'auditHidden') ? $this->auditHidden : [];

        return array_values(array_unique(array_merge($default, $custom)));
    }

    protected function auditModule(): string
    {
        if (property_exists($this, 'auditModule')) {
            return $this->auditModule;
        }

        return Str::headline(class_basename($this));
    }

    /**
     * @param array<string, mixed>|null $oldValues
     * @param array<string, mixed>|null $newValues
     */
    protected function writeModelAuditLog(string $action, ?array $oldValues, ?array $newValues): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'module' => $this->auditModule(),
            'action' => $action,
            'auditable_type' => static::class,
            'auditable_id' => $this->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'created_at' => now(),
        ]);
    }
}
