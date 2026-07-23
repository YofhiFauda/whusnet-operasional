<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

        // Dua DISTINCT ini cuma mengisi dropdown filter, tapi keduanya menyapu
        // SELURUH audit_logs — tabel yang paling cepat tumbuh di sistem — setiap
        // kali halaman dibuka. Bahkan dengan index, DISTINCT di tabel besar tetap
        // mahal. Daftar modul & aksi praktis tidak pernah berubah (nilainya
        // ditentukan kode, bukan input user), jadi cache 5 menit sudah cukup.
        $modules = Cache::remember(
            'audit_logs.distinct_modules',
            now()->addMinutes(5),
            fn () => AuditLog::query()->select('module')->distinct()->orderBy('module')->pluck('module')
        );
        $actions = Cache::remember(
            'audit_logs.distinct_actions',
            now()->addMinutes(5),
            fn () => AuditLog::query()->select('action')->distinct()->orderBy('action')->pluck('action')
        );

        return view('audit-logs.index', compact('auditLogs', 'modules', 'actions', 'module', 'action', 'search'));
    }
}
