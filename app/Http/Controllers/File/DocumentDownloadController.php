<?php

namespace App\Http\Controllers\File;

use App\Http\Controllers\Controller;
use App\Models\MentorshipDocument;
use App\Services\DocumentAccessService;
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
        } elseif ($request->query('portal') === 'kaprodi') {
            abort_unless(app(DocumentAccessService::class)->workspace($user, $document, 'kaprodi', true), 403);
        } elseif ($user->hasRole('mahasiswa')) {
            abort_unless(app(DocumentAccessService::class)->workspace($user, $document, 'mahasiswa', true), 403);
        } else {
            $access = app(DocumentAccessService::class);
            $allowed = $access->workspace($user, $document, 'dosen', true)
                || $access->workspace($user, $document, 'kaprodi', true);
            abort_unless($allowed, 403);
        }

        abort_if($document->storage_disk === null || $document->storage_path === null, 404);
        abort_unless(Storage::disk($document->storage_disk)->exists($document->storage_path), 404);

        if ($request->boolean('inline')) {
            return Storage::disk($document->storage_disk)->response(
                $document->storage_path,
                $document->file_name,
                ['Content-Type' => $document->mime_type ?? 'application/octet-stream'],
            );
        }

        return Storage::disk($document->storage_disk)->download(
            $document->storage_path,
            $document->file_name,
        );
    }
}
