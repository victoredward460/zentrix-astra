<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserRoleRequest;
use App\Http\Requests\Admin\UpdateUserStatusRequest;
use App\Models\User;
use App\Services\Admin\PlatformAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request, PlatformAuditLogger $auditLogger): View
    {
        $this->authorize('viewAny', User::class);
        $auditLogger->record($request->user(), 'admin.user_list_viewed');

        return view('admin.users.index', [
            'users' => User::query()->orderBy('id')->paginate(20),
        ]);
    }

    public function updateRole(
        UpdateUserRoleRequest $request,
        User $user,
        PlatformAuditLogger $auditLogger,
    ): RedirectResponse {
        $role = UserRole::from($request->validated('role'));

        DB::transaction(function () use ($request, $user, $role, $auditLogger): void {
            $target = User::query()->lockForUpdate()->findOrFail($user->id);
            $administrators = User::query()
                ->where('role', UserRole::Administrator->value)
                ->where('is_active', true)
                ->lockForUpdate()
                ->get(['id']);

            if (
                $target->is_active
                && $target->role === UserRole::Administrator
                && $role !== UserRole::Administrator
                && $administrators->count() <= 1
            ) {
                throw ValidationException::withMessages([
                    'role' => 'The last administrator cannot be demoted.',
                ]);
            }

            if ($target->role === $role) {
                return;
            }

            $oldRole = $target->role->value;
            $target->forceFill(['role' => $role])->save();
            $auditLogger->record($request->user(), 'admin.user_role_updated', $target, [
                'old_role' => $oldRole,
                'new_role' => $role->value,
            ]);
        });

        return back()->with('status', 'The user role has been updated.');
    }

    public function updateStatus(
        UpdateUserStatusRequest $request,
        User $user,
        PlatformAuditLogger $auditLogger,
    ): RedirectResponse {
        $isActive = (bool) $request->validated('is_active');

        $changed = DB::transaction(function () use ($request, $user, $isActive, $auditLogger): bool {
            $target = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($target->is_active === $isActive) {
                return false;
            }

            if (! $isActive && $target->role === UserRole::Administrator) {
                $activeAdministrators = User::query()
                    ->where('role', UserRole::Administrator->value)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->count();

                if ($activeAdministrators <= 1) {
                    throw ValidationException::withMessages([
                        'is_active' => 'The last active administrator cannot be disabled.',
                    ]);
                }
            }

            $oldStatus = $target->is_active;
            $target->forceFill([
                'is_active' => $isActive,
                'remember_token' => $isActive ? $target->remember_token : Str::random(60),
            ])->save();

            if (! $isActive && config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))
                    ->where('user_id', $target->id)
                    ->delete();
            }

            $auditLogger->record(
                $request->user(),
                'admin.user_status_updated',
                $target,
                ['old_active' => $oldStatus, 'new_active' => $isActive],
            );

            return true;
        });

        return back()->with(
            'status',
            $changed ? 'The account status has been updated.' : 'The account already has that status.',
        );
    }
}
