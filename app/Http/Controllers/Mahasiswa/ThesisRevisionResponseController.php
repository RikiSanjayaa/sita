<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mahasiswa\RespondToRevisionRequest;
use App\Models\ThesisRevision;
use App\Services\ThesisDefenseRevisionService;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

class ThesisRevisionResponseController extends Controller
{
    public function __construct(
        private readonly ThesisDefenseRevisionService $revisionService,
    ) {}

    public function store(RespondToRevisionRequest $request, ThesisRevision $revision): RedirectResponse
    {
        $student = $request->user();
        abort_if($student === null, 401);

        try {
            $this->revisionService->submitResponses(
                $student,
                $revision,
                $request->responses(),
                $request->file('manuscript'),
            );
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Perbaikan berhasil dikirim ke penguji.');
    }
}
