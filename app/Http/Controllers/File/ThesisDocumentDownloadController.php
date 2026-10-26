<?php

namespace App\Http\Controllers\File;

use App\Http\Controllers\Controller;
use App\Models\ThesisDocument;
use App\Services\DocumentAccessService;
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

        $portal = $request->query('portal') === 'kaprodi' ? 'kaprodi' : null;
        abort_unless(app(DocumentAccessService::class)->thesis($user, $document, $portal), 403);

        abort_if($document->storage_disk === null || $document->storage_path === null, 404);

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
