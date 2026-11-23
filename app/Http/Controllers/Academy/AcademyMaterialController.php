<?php

namespace App\Http\Controllers\Academy;

use App\Exceptions\AgentProviderException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GenerateAcademyMaterialRequest;
use App\Models\AcademyLearningMaterial;
use App\Services\Academy\AcademyMaterialGenerator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use RuntimeException;

class AcademyMaterialController extends Controller
{
    public function generate(
        GenerateAcademyMaterialRequest $request,
        AcademyMaterialGenerator $generator,
    ): RedirectResponse {
        $user = $request->user();

        if (! $user->hasAiProviderConsent()) {
            return back()->withInput()->withErrors([
                'ai_material' => 'Enable AI features in your profile before generating learning materials.',
            ]);
        }

        $validated = $request->validated();

        try {
            $content = $generator->generate(
                $validated['topic'],
                $validated['level'],
                $user->aiModel(),
            );
        } catch (AgentProviderException $exception) {
            return back()->withInput()->withErrors([
                'ai_material' => $exception->userMessage(),
            ]);
        }

        $html = view('academy.material-document', [
            'content' => $content,
            'level' => $validated['level'],
        ])->render();
        $pdf = Pdf::loadView('academy.material-pdf', [
            'content' => $content,
            'level' => $validated['level'],
        ])->setPaper('a4')->output();

        if (! str_starts_with(mb_strtolower($html), '<!doctype html>')
            || strlen($html) > 250_000
            || ! str_starts_with($pdf, '%PDF-')
            || strlen($pdf) > 8_388_608) {
            Log::warning('Generated Academy material failed document validation.', [
                'user_id' => $user->id,
                'html_bytes' => strlen($html),
                'pdf_bytes' => strlen($pdf),
            ]);

            return back()->withInput()->withErrors([
                'ai_material' => 'Generated material could not be validated. No files were saved.',
            ]);
        }

        $publicId = (string) Str::uuid();
        $directory = (string) Str::uuid();
        $htmlPath = $directory.'/'.Str::uuid().'.html';
        $pdfPath = $directory.'/'.Str::uuid().'.pdf';
        $disk = Storage::disk('academy-materials');
        $storedPaths = [];
        $recordCreated = false;

        try {
            if (! $disk->put($htmlPath, $html)) {
                throw new RuntimeException('Unable to store the generated Academy HTML document.');
            }
            $storedPaths[] = $htmlPath;

            if (! $disk->put($pdfPath, $pdf)) {
                throw new RuntimeException('Unable to store the generated Academy PDF document.');
            }
            $storedPaths[] = $pdfPath;

            $material = DB::transaction(fn (): AcademyLearningMaterial => AcademyLearningMaterial::query()->create([
                'user_id' => $user->id,
                'public_id' => $publicId,
                'title' => $content['title'],
                'topic' => $validated['topic'],
                'level' => $validated['level'],
                'content' => $content,
                'html_path' => $htmlPath,
                'pdf_path' => $pdfPath,
                'published_at' => now(),
            ]));
            $recordCreated = true;
        } finally {
            if (! $recordCreated) {
                foreach ($storedPaths as $storedPath) {
                    if ($disk->exists($storedPath) && ! $disk->delete($storedPath)) {
                        Log::error('Failed to remove an unclaimed Academy material file.', [
                            'path' => $storedPath,
                        ]);
                    }
                }
            }
        }

        $shareUrl = URL::signedRoute('academy.materials.shared', [
            'material' => $material->public_id,
        ]);

        return redirect()
            ->route($request->routeIs('admin.*') ? 'admin.academy.index' : 'academy.index')
            ->with('status', 'HTML and PDF learning materials were generated and published.')
            ->with('material-share-url', $shareUrl);
    }

    public function showShared(AcademyLearningMaterial $material): \Illuminate\View\View
    {
        abort_unless($material->published_at !== null, 404);

        return view('academy.material-shared', [
            'material' => $material,
            'htmlDownloadUrl' => URL::signedRoute('academy.materials.shared.download', [
                'material' => $material->public_id,
                'format' => 'html',
            ]),
            'pdfDownloadUrl' => URL::signedRoute('academy.materials.shared.download', [
                'material' => $material->public_id,
                'format' => 'pdf',
            ]),
        ]);
    }

    public function downloadShared(AcademyLearningMaterial $material, string $format): StreamedResponse
    {
        abort_unless($material->published_at !== null, 404);

        return $this->downloadMaterial($material, $format);
    }

    public function downloadOwned(Request $request, AcademyLearningMaterial $material, string $format): StreamedResponse
    {
        abort_unless($material->user_id === $request->user()->id, 404);

        return $this->downloadMaterial($material, $format);
    }

    public function destroy(Request $request, AcademyLearningMaterial $material): RedirectResponse
    {
        abort_unless($material->user_id === $request->user()->id, 404);

        $disk = Storage::disk('academy-materials');
        foreach ([$material->html_path, $material->pdf_path] as $path) {
            if ($disk->exists($path) && ! $disk->delete($path)) {
                abort(500, 'Generated material could not be removed from private storage.');
            }
        }

        $material->delete();

        return back()->with('status', 'Learning material deleted and its shared link revoked.');
    }

    private function downloadMaterial(AcademyLearningMaterial $material, string $format): StreamedResponse
    {
        [$path, $mimeType] = match ($format) {
            'html' => [$material->html_path, 'text/html; charset=UTF-8'],
            'pdf' => [$material->pdf_path, 'application/pdf'],
            default => abort(404),
        };
        $disk = Storage::disk('academy-materials');
        abort_unless($disk->exists($path), 404);

        $filename = Str::slug($material->title).'-'.$material->public_id.'.'.$format;

        return $disk->download($path, $filename, [
            'Content-Type' => $mimeType,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
