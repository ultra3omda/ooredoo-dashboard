<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.audit-logs.index');
    }

    public function getData(Request $request)
    {
        $query = DB::table('permission_audit_logs')
            ->orderBy('created_at', 'desc');

        // Filters
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('user_name', 'like', "%$search%")
                  ->orWhere('user_email', 'like', "%$search%")
                  ->orWhere('changed_by_name', 'like', "%$search%")
                  ->orWhere('details', 'like', "%$search%");
            });
        }

        if ($action = $request->input('action')) {
            $query->where('action', $action);
        }

        if ($dateFrom = $request->input('date_from')) {
            $query->where('created_at', '>=', $dateFrom . ' 00:00:00');
        }

        if ($dateTo = $request->input('date_to')) {
            $query->where('created_at', '<=', $dateTo . ' 23:59:59');
        }

        $total = $query->count();
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 20;
        $logs = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        // Stats
        $stats = DB::table('permission_audit_logs')
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN action = 'grant_full_access' THEN 1 ELSE 0 END) as full_access_grants,
                SUM(CASE WHEN action = 'restrict_campaigns' THEN 1 ELSE 0 END) as restrictions,
                COUNT(DISTINCT user_id) as users_affected,
                COUNT(DISTINCT changed_by_id) as admins_active
            ")
            ->first();

        return response()->json([
            'logs' => $logs,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage),
            'stats' => $stats,
        ]);
    }

    /**
     * Log a permission change (static helper for use from other controllers)
     */
    public static function logPermissionChange(
        int $userId,
        ?string $userName,
        ?string $userEmail,
        int $changedById,
        ?string $changedByName,
        ?string $changedByEmail,
        string $action,
        ?string $oldValue,
        ?string $newValue,
        ?string $details,
        ?string $ipAddress = null
    ): void {
        DB::table('permission_audit_logs')->insert([
            'user_id' => $userId,
            'user_name' => $userName,
            'user_email' => $userEmail,
            'changed_by_id' => $changedById,
            'changed_by_name' => $changedByName,
            'changed_by_email' => $changedByEmail,
            'action' => $action,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'details' => $details,
            'ip_address' => $ipAddress,
            'created_at' => now(),
        ]);
    }
}
