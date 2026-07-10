<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $module = trim((string) $request->query('module', ''));
        $action = trim((string) $request->query('action', ''));
        $search = trim((string) $request->query('search', ''));

        $query = AuditLog::query()
            ->with('user')
            ->latest('created_at')
            ->latest('id');

        if ($module !== '') {
            $query->where('module', $module);
        }

        if ($action !== '') {
            $query->where('action', $action);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('auditable_type', 'like', "%{$search}%")
                    ->orWhere('auditable_id', $search)
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $auditLogs = $query->paginate(15)->withQueryString();
        $modules = AuditLog::query()->select('module')->distinct()->orderBy('module')->pluck('module');
        $actions = AuditLog::query()->select('action')->distinct()->orderBy('action')->pluck('action');

        return view('audit-logs.index', compact('auditLogs', 'modules', 'actions', 'module', 'action', 'search'));
    }
}
