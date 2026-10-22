<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mahasiswa\SearchLecturersRequest;
use App\Services\LecturerSearchService;
use Illuminate\Http\JsonResponse;

class LecturerSearchController extends Controller
{
    public function __construct(private readonly LecturerSearchService $lecturerSearchService) {}

    public function __invoke(SearchLecturersRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->lecturerSearchService->searchForStudent(
                student: $request->user(),
                search: $request->validated('q'),
                selectedIds: $request->validated('selected_ids', []),
            ),
        ]);
    }
}
