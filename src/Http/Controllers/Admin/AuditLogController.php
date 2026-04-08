<?php

namespace Escalated\Laravel\Http\Controllers\Admin;

use Escalated\Laravel\Contracts\EscalatedUiRenderer;
use Escalated\Laravel\Escalated;
use Escalated\Laravel\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AuditLogController extends Controller
{
    public function __construct(
        protected EscalatedUiRenderer $renderer,
    ) {}

    public function index(Request $request): mixed
    {
        $query = AuditLog::with('user')
            ->orderByDesc('created_at');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }

        if ($request->filled('auditable_type')) {
            $query->where('auditable_type', $request->input('auditable_type'));
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->input('date_to').' 23:59:59');
        }

        $userModel = Escalated::userModel();

        return $this->renderer->render('Escalated/Admin/AuditLog/Index', [
            'logs' => $query->paginate(50)->withQueryString(),
            'filters' => $request->only(['user_id', 'action', 'auditable_type', 'date_from', 'date_to']),
            'users' => $userModel::select('id', 'name')->orderBy('name')->get(),
            'actions' => ['created', 'updated', 'deleted'],
            'resourceTypes' => AuditLog::select('auditable_type')->distinct()->pluck('auditable_type'),
        ]);
    }
}
