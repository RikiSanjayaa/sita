<?php

namespace App\Http\Controllers\File;

use App\Http\Controllers\Controller;
use App\Models\ThesisSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ThesisProposalDownloadController extends Controller
{
    public function __invoke(Request $request, ThesisSubmission $submission): StreamedResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        if ($user->hasRole('mahasiswa')) {
            abort_unless($submission->student_user_id === $user->id, 403);
        } elseif (! $user->hasRole('admin')) {
            abort(403);
        }

        $path = $submission->proposal_file_path;
        abort_if($path === null, 404);
        abort_unless(Storage::disk('public')->exists($path), 404);

        $fileName = basename($path);

        if ($request->boolean('inline')) {
            return Storage::disk('public')->response($path, $fileName, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => sprintf('inline; filename="%s"', $fileName),
            ]);
        }

        return Storage::disk('public')->download($path, $fileName);
    }
}
