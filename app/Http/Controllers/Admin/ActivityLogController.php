<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformAuditEvent;
use App\Services\Admin\PlatformAuditLogger;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityLogController extends Controller
{
    public function index(Request $request, PlatformAuditLogger $auditLogger): View
    {
        $auditLogger->record($request->user(), 'admin.activity_log_viewed');

        return view('admin.activity.index', [
            'scope' => 'all',
            'events' => PlatformAuditEvent::query()
                ->with('actor:id,name,email')
                ->latest('created_at')
                ->paginate(30),
        ]);
    }

    public function security(Request $request, PlatformAuditLogger $auditLogger): View
    {
        $auditLogger->record($request->user(), 'admin.security_events_viewed');

        return view('admin.activity.index', [
            'scope' => 'security',
            'events' => PlatformAuditEvent::query()
                ->with('actor:id,name,email')
                ->where(function ($query): void {
                    $query->where('event_key', 'like', 'auth.%')
                        ->orWhere('event_key', 'like', 'admin.user_%')
                        ->orWhere('event_key', 'like', 'admin.scan_%')
                        ->orWhere('event_key', 'like', 'admin.file_check_%')
                        ->orWhere('event_key', 'like', 'admin.safe_navigation_%')
                        ->orWhere('event_key', 'like', 'admin.academy_%')
                        ->orWhere('event_key', 'like', 'admin.configuration_%')
                        ->orWhere('event_key', 'admin.feature_access_changed')
                        ->orWhere('event_key', 'admin.security_events_viewed');
                })
                ->latest('created_at')
                ->paginate(30),
        ]);
    }
}
