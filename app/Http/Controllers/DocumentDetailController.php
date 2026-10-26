<?php

namespace App\Http\Controllers;

use App\Models\MentorshipDocument;
use App\Models\ThesisDocument;
use App\Services\DocumentDetailService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DocumentDetailController extends Controller
{
    public function __construct(private readonly DocumentDetailService $documents) {}

    public function workspace(Request $request, MentorshipDocument $document): Response
    {
        $role = $request->routeIs('dosen.*') ? 'dosen' : ($request->routeIs('mahasiswa.*') ? 'mahasiswa' : 'kaprodi');

        return Inertia::render('documents/show', $this->documents->workspace($request->user(), $document, $role));
    }

    public function thesis(Request $request, ThesisDocument $document): Response
    {
        $role = $request->routeIs('dosen.*') ? 'dosen' : 'kaprodi';

        $payload = $this->documents->thesis($request->user(), $document, $role);
        $returnTo = $request->query('returnTo');
        if ($role === 'dosen' && is_string($returnTo)
            && str_starts_with($returnTo, '/dosen/seminar-proposal?')
            && parse_url($returnTo, PHP_URL_PATH) === '/dosen/seminar-proposal'
            && parse_url($returnTo, PHP_URL_HOST) === null) {
            parse_str((string) parse_url($returnTo, PHP_URL_QUERY), $query);
            if (($query['defense'] ?? null) === (string) $document->defense_id) {
                $payload['navigation']['listUrl'] = $returnTo;
            }
        }

        return Inertia::render('documents/show', $payload);
    }
}
