<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'entity_type' => 'nullable|string|max:50',
            'entity_id' => 'nullable|integer',
            'user_id' => 'nullable|integer',
            'action' => 'nullable|string|max:50',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = AuditLog::with('user:id,name,email');

        if ($request->filled('entity_type')) {
            $query->where('entity_type', $request->entity_type);
        }

        if ($request->filled('entity_id')) {
            $query->where('entity_id', $request->entity_id);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
        }

        $perPage = $request->integer('per_page', 50);

        return response()->json($query->orderByDesc('created_at')->paginate($perPage));
    }
}
