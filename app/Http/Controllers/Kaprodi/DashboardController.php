<?php

namespace App\Http\Controllers\Kaprodi;

use App\Http\Controllers\Controller;
use App\Models\KaprodiAssignment;
use App\Services\KaprodiPortalService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly KaprodiPortalService $kaprodiPortalService,
    ) {}

    public function __invoke(Request $request): Response
    {
        $assignment = $request->user()?->kaprodiAssignment;

        abort_unless($assignment instanceof KaprodiAssignment && $assignment->programStudi !== null, 403);

        return Inertia::render('kaprodi/dashboard', $this->kaprodiPortalService->dashboard($assignment->programStudi, $assignment));
    }
}
