<?php

namespace App\Http\Controllers\Kaprodi;

use App\Http\Controllers\Controller;
use App\Models\ProgramStudi;
use App\Services\KaprodiPortalService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DokumenController extends Controller
{
    public function __construct(
        private readonly KaprodiPortalService $kaprodiPortalService,
    ) {}

    public function __invoke(Request $request): Response
    {
        $programStudi = $request->user()?->kaprodiAssignment?->programStudi;

        abort_unless($programStudi instanceof ProgramStudi, 403);

        return Inertia::render('kaprodi/dokumen', $this->kaprodiPortalService->documents($programStudi));
    }
}
