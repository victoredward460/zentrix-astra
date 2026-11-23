<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademyLessonProgress;
use App\Models\AcademyQuizAttempt;
use App\Models\FileCheck;
use App\Models\ScanRun;
use App\Services\Admin\PlatformAuditLogger;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ModuleRecordsController extends Controller
{
    public function scans(Request $request, PlatformAuditLogger $auditLogger): View
    {
        $auditLogger->record($request->user(), 'admin.scan_list_viewed');

        return view('admin.scans.index', [
            'scans' => ScanRun::query()
                ->with(['user:id,name,email', 'securityAsset:id,hostname'])
                ->withCount('findings')
                ->latest('requested_at')
                ->paginate(20),
        ]);
    }

    public function showScan(
        ScanRun $scanRun,
        PlatformAuditLogger $auditLogger,
        Request $request,
    ): View {
        $auditLogger->record($request->user(), 'admin.scan_run_viewed', $scanRun);
        $scanRun->load(['user:id,name,email', 'securityAsset', 'checkResults', 'findings']);

        return view('admin.scans.show', ['scanRun' => $scanRun]);
    }

    public function fileChecks(Request $request, PlatformAuditLogger $auditLogger): View
    {
        $auditLogger->record($request->user(), 'admin.file_check_list_viewed');

        return view('admin.file-checks.index', [
            'fileChecks' => FileCheck::query()
                ->with('user:id,name,email')
                ->latest()
                ->paginate(20),
        ]);
    }

    public function showFileCheck(
        FileCheck $fileCheck,
        PlatformAuditLogger $auditLogger,
        Request $request,
    ): View {
        $auditLogger->record($request->user(), 'admin.file_check_viewed', $fileCheck);
        $fileCheck->load('user:id,name,email');

        return view('admin.file-checks.show', ['fileCheck' => $fileCheck]);
    }

    public function academyProgress(
        PlatformAuditLogger $auditLogger,
        Request $request,
    ): View {
        $auditLogger->record($request->user(), 'admin.academy_progress_viewed');

        return view('admin.academy.progress', [
            'progressRecords' => AcademyLessonProgress::query()
                ->with(['user:id,name,email', 'lesson.course'])
                ->latest('updated_at')
                ->paginate(15, ['*'], 'progress_page'),
            'quizAttempts' => AcademyQuizAttempt::query()
                ->with(['user:id,name,email', 'quiz.lesson'])
                ->latest('submitted_at')
                ->paginate(15, ['*'], 'attempts_page'),
        ]);
    }
}
