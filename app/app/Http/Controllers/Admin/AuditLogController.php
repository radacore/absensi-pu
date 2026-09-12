<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        abort_if(Auth::guard('web')->user()->role !== 'super_admin', 403, 'Menu ini hanya untuk Super Admin.');

        $filters = $request->validate([
            'action' => ['nullable', 'string', 'max:100'],
            'actor' => ['nullable', 'string', 'max:150'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'q' => ['nullable', 'string', 'max:150'],
        ]);

        $query = AuditLog::query()->orderByDesc('created_at');

        if (! empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }
        if (! empty($filters['actor'])) {
            $query->where('actor_name', 'like', '%'.$filters['actor'].'%');
        }
        if (! empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('created_at', '<=', $filters['to']);
        }
        if (! empty($filters['q'])) {
            $q = $filters['q'];
            $query->where(function ($qq) use ($q) {
                $qq->where('description', 'like', "%{$q}%")
                    ->orWhere('subject_label', 'like', "%{$q}%");
            });
        }

        $logs = $query->limit(500)->get()->map(fn (AuditLog $log) => [
            'id' => $log->id,
            'action' => $log->action,
            'actorType' => $log->actor_type,
            'actorName' => $log->actor_name,
            'subjectType' => $log->subject_type,
            'subjectLabel' => $log->subject_label,
            'description' => $log->description,
            'meta' => $log->meta,
            'ip' => $log->ip,
            'createdAt' => $log->created_at?->timezone('Asia/Makassar')->format('Y-m-d H:i:s'),
        ])->all();

        $actions = AuditLog::select('action')->distinct()->orderBy('action')->pluck('action')->all();

        return Inertia::render('Admin/AuditLog', [
            'logs' => $logs,
            'filters' => $filters,
            'actions' => $actions,
        ]);
    }
}
