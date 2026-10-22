<?php

namespace App\Http\Controllers\Kaprodi;

use App\Http\Controllers\Controller;
use App\Models\ProgramStudi;
use App\Models\ThesisProject;
use App\Services\KaprodiPortalService;
use App\Services\ThesisProjectNoteService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MahasiswaController extends Controller
{
    public function __construct(
        private readonly KaprodiPortalService $kaprodiPortalService,
        private readonly ThesisProjectNoteService $noteService,
    ) {}

    public function __invoke(Request $request): Response
    {
        $programStudi = $request->user()?->kaprodiAssignment?->programStudi;

        abort_unless($programStudi instanceof ProgramStudi, 403);

        $projects = ThesisProject::query()
            ->where('program_studi_id', $programStudi->id)
            ->get(['id', 'student_user_id', 'program_studi_id']);

        return Inertia::render('kaprodi/mahasiswa', [
            ...$this->kaprodiPortalService->studentIndex($programStudi),
            'projectNotes' => $this->noteService->payload($request->user(), $projects, allowWrite: false),
        ]);
    }
}
