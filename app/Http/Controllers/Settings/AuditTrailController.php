<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\SystemAudit;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;

class AuditTrailController extends Controller
{
    public function index(Request $request)
    {
        $query = SystemAudit::with('user:id,name,email')->latest();

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('auditable_type', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $audits = $query->paginate(20)->withQueryString()->through(function ($audit) {
            $audit->auditable_type_short = class_basename($audit->auditable_type);
            return $audit;
        });

        return Inertia::render('Settings/AuditTrail/Index', [
            'audits' => $audits,
            'filters' => $request->only(['start_date', 'end_date', 'action', 'search']),
            'actions' => SystemAudit::select('action')->distinct()->pluck('action'),
        ]);
    }
}
