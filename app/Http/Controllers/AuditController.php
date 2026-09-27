<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request): View
    {
        if (! $request->user()->isAdmin()) {
            abort(403);
        }

        $query = AuditLog::query()->with(['user', 'tenant']);

        if ($search = $request->query('q')) {
            $query->where(function ($query) use ($search) {
                $query->where('summary', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('tenant', fn ($q) => $q->where('name', 'like', "%{$search}%"));
            });
        }

        if ($action = $request->query('action')) {
            $query->where('action', $action);
        }

        if ($from = $request->query('de')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($until = $request->query('ate')) {
            $query->whereDate('created_at', '<=', $until);
        }

        $actions = AuditLog::query()->distinct()->orderBy('action')->pluck('action');

        $logs = $query->latest('id')->paginate(25)->withQueryString();

        return view('auditoria.index', [
            'logs' => $logs,
            'actions' => $actions,
            'filters' => $request->query(),
            'isSuper' => $request->user()->isSuperAdmin(),
        ]);
    }
}
