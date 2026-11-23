<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboardService): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return view('dashboard', [
            'dashboard' => $dashboardService->forUser($user),
            'user' => $user,
        ]);
    }
}
