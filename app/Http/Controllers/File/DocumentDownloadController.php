<?php

namespace App\Http\Controllers\File;

use App\Http\Controllers\Controller;
use App\Models\MentorshipDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentDownloadController extends Controller
{
    public function __invoke(Request $request, MentorshipDocument $document): StreamedResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        if ($user->hasRole('admin')) {
            abort_unless($request->boolean('escalated'), 403);
        } elseif ($user->hasRole('mahasiswa')) {
            abort_unless($document->student_user_id === $user->id, 403);
        } elseif ($user->hasRole('dosen')) {
            abort_unless($document->lecturer_user_id === $user->id, 403);
        } else {
            abort(403);
        }

        abort_if($document->storage_disk === null || $document->storage_path === null, 404);
        abort_unless(Storage::disk($document->storage_disk)->exists($document->storage_path), 404);

        return Storage::disk($document->storage_disk)->download(
            $document->storage_path,
            $document->file_name,
        );
    }
}
