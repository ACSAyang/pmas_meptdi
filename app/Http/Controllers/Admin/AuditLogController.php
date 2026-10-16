<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Csv;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditLog::query()->with('user')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($q) => $q->where('action', 'like', '%'.$request->input('q').'%')->orWhere('description', 'like', '%'.$request->input('q').'%')->orWhere('reason', 'like', '%'.$request->input('q').'%')))
            ->when($request->filled('user'), fn ($q) => $q->where('user_id', $request->integer('user')))
            ->when($request->filled('entity'), fn ($q) => $q->where('entity_type', $request->input('entity')))
            ->when($request->filled('from'), fn ($q) => $q->where('created_at', '>=', $request->date('from')->startOfDay()))
            ->when($request->filled('to'), fn ($q) => $q->where('created_at', '<=', $request->date('to')->endOfDay()))
            ->latest('created_at')->latest('id');

        if ($request->input('format') === 'csv') {
            return Csv::download('audit-trail-'.now()->format('Ymd-His').'.csv', ['Timestamp', 'User', 'Action', 'Entity', 'Entity ID', 'Description', 'Reason', 'IP'],
                $query->limit(10000)->get()->map(fn ($l) => [$l->created_at->format('Y-m-d H:i:s'), $l->user?->username ?? 'system', $l->action, $l->entity_type, $l->entity_id, $l->description, $l->reason, $l->ip_address]));
        }

        return view('admin.audit.index', [
            'logs' => $query->paginate(30)->withQueryString(),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'entities' => AuditLog::query()->whereNotNull('entity_type')->distinct()->orderBy('entity_type')->pluck('entity_type'),
            'filters' => $request->only(['q', 'user', 'entity', 'from', 'to']),
        ]);
    }

    public function show(AuditLog $log): View
    {
        return view('admin.audit.show', ['log' => $log->load('user')]);
    }
}
