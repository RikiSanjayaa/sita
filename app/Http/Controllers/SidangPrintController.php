<?php

namespace App\Http\Controllers;

use App\Models\ThesisDefense;
use App\Policies\SidangPaperPolicy;
use App\Services\SidangPaperPresenter;
use App\Services\SidangScoreService;
use App\Support\SidangDocumentTemplate;
use App\Support\SidangSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SidangPrintController extends Controller
{
    public function __invoke(Request $request, ThesisDefense $defense, SidangPaperPolicy $policy, SidangPaperPresenter $presenter): Response
    {
        abort_unless($policy->print($request->user(), $defense), 403);
        abort_unless($defense->scheduled_for !== null && $defense->status !== 'cancelled', 404);
        $form = $request->query('lembar');
        abort_unless($form === null || (is_string($form) && isset(SidangPaperController::FORMS[$form])), 404);
        $template = SidangDocumentTemplate::forProgram($defense->project->programStudi);
        abort_if($template === null, 422, 'Template dokumen prodi belum tersedia. Hubungi Admin prodi.');
        $fields = $presenter->fields($defense);
        $summary = app(SidangScoreService::class)->summary($defense);

        return response()->view($template['view'], [
            'defense' => $defense, 'fields' => $fields,
            'forms' => $form === null ? SidangPaperController::FORMS : [$form => SidangPaperController::FORMS[$form]],
            'selectedForm' => $form,
            'settings' => $summary['settings'] ?? SidangSettings::forProgram($defense->project->programStudi),
        ])->header('Cache-Control', 'private, no-store');
    }
}
