<?php

namespace App\Http\Controllers\Kaprodi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kaprodi\SearchLecturersRequest;
use App\Models\ThesisProject;
use App\Services\LecturerSearchService;
use Illuminate\Http\JsonResponse;

class LecturerSearchController extends Controller
{
    public function __construct(private readonly LecturerSearchService $lecturerSearchService) {}

    public function __invoke(SearchLecturersRequest $request): JsonResponse
    {
        $project = ThesisProject::query()
            ->with('activeSupervisorAssignments')
            ->findOrFail((int) $request->validated('project_id'));

        abort_unless(
            (int) $project->program_studi_id === (int) $request->user()?->kaprodiProgramStudiId(),
            404,
        );

        return response()->json([
            'data' => $this->lecturerSearchService->search(
                project: $project,
                search: $request->validated('q'),
                purpose: (string) $request->validated('purpose'),
                selectedIds: $request->validated('selected_ids', []),
            ),
        ]);
    }
}
