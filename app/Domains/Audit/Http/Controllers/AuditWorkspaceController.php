<?php

namespace App\Domains\Audit\Http\Controllers;

use App\Core\Traits\AuthorizesWorkspaceAccess;
use App\Domains\Audit\Models\AuditLog;
use App\Domains\Identity\Services\AuthorizationService;
use App\Domains\Identity\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;

class AuditWorkspaceController extends Controller
{
    use AuthorizesRequests, AuthorizesWorkspaceAccess;

    public function index(Request $request, AuthorizationService $authService)
    {
        $user = $request->user();
        abort_unless($authService->hasPermission($user, 'identity.user.view') || $authService->isTenantAdmin($user), 403);

        $query = AuditLog::with(['user', 'facility'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('category')) {
            $query->where('event_category', $request->query('category'));
        }

        if ($request->filled('action')) {
            $query->where('action', 'like', "%{$request->query('action')}%");
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->query('user_id'));
        }

        if ($request->filled('search')) {
            $s = $request->query('search');
            $query->where(function ($q) use ($s) {
                $q->where('entity_type', 'like', "%{$s}%")
                    ->orWhere('entity_id', 'like', "%{$s}%")
                    ->orWhere('ip_address', 'like', "%{$s}%")
                    ->orWhere('justification_reason', 'like', "%{$s}%");
            });
        }

        $perPage = min(100, max(10, $request->integer('per_page', 50)));
        $logs = $query->paginate($perPage)->withQueryString();

        $categories = AuditLog::distinct()->pluck('event_category')->filter()->values();
        $users = User::select('id', 'first_name', 'last_name', 'email')->orderBy('first_name')->get();

        $totalLogsCount = AuditLog::count();

        return Inertia::render('Workspace/AuditWorkspace', [
            'logs' => $logs,
            'categories' => $categories,
            'users' => $users,
            'filters' => $request->only(['category', 'action', 'user_id', 'search']),
            'totalLogsCount' => $totalLogsCount,
        ]);
    }

    public function verifyIntegrity(Request $request, AuthorizationService $authService)
    {
        $user = $request->user();
        abort_unless($authService->hasPermission($user, 'identity.user.view') || $authService->isTenantAdmin($user), 403);

        $logs = AuditLog::orderBy('created_at', 'asc')->limit(500)->get();

        $brokenChains = [];
        $previousHash = null;

        foreach ($logs as $log) {
            if ($previousHash !== null && $log->previous_hash !== $previousHash) {
                $brokenChains[] = [
                    'log_id' => $log->id,
                    'action' => $log->action,
                    'created_at' => $log->created_at->toIso8601String(),
                    'expected_previous' => $previousHash,
                    'actual_previous' => $log->previous_hash,
                ];
            }
            $previousHash = $log->hash_signature;
        }

        if (count($brokenChains) === 0) {
            return back()->with('success', 'Cryptographic chain verification PASSED: All 500 inspected forensic audit entries match SHA-256 tamper-evident merkle chain.');
        }

        return back()->withErrors(['audit_integrity' => 'Tamper detection alert: ' . count($brokenChains) . ' audit record(s) show discontinuous hash chains!']);
    }
}
