<?php

namespace App\Http\Controllers\File;

use App\Http\Controllers\Controller;
use App\Models\ThesisDefenseExaminer;
use App\Models\ThesisDocument;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ThesisDocumentDownloadController extends Controller
{
    public function __invoke(Request $request, ThesisDocument $document): StreamedResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $document->loadMissing('project');
        $project = $document->project;
        abort_if($project === null, 404);

        if ($user->hasRole('super_admin')) {
            // Super admin may access any thesis document.
        } elseif ($user->hasRole('admin')) {
            $prodiId = $user->adminProgramStudiId();

            abort_unless($prodiId === null || $project->program_studi_id === $prodiId, 403);
        } elseif ($user->hasRole('mahasiswa')) {
            abort_unless($project->student_user_id === $user->id, 403);
        } elseif ($user->hasRole('dosen')) {
            $isSupervisor = $project->supervisorAssignments()
                ->where('lecturer_user_id', $user->id)
                ->exists();

            $isExaminer = ThesisDefenseExaminer::query()
                ->where('lecturer_user_id', $user->id)
                ->whereHas('defense', static fn($query) => $query->where('project_id', $project->id))
                ->exists();

            abort_unless($isSupervisor || $isExaminer, 403);
        } else {
            abort(403);
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk($document->storage_disk);

        abort_unless($disk->exists($document->storage_path), 404);

        if ($request->boolean('inline')) {
            return $disk->response(
                $document->storage_path,
                $document->file_name,
                [
                    'Content-Type' => $document->mime_type ?? 'application/octet-stream',
                    'Content-Disposition' => sprintf('inline; filename="%s"', $document->file_name),
                ],
            );
        }

        return $disk->download(
            $document->storage_path,
            $document->file_name,
        );
    }
}
