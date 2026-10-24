<?php

namespace App\Http\Controllers\Dosen;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\DosenBimbinganService;
use App\Services\DosenDashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DosenBimbinganService $dosenBimbinganService,
        private readonly DosenDashboardService $dosenDashboardService,
    ) {}

    public function __invoke(Request $request): Response
    {
        $lecturer = $request->user();
        abort_if($lecturer === null, 401);
        $lecturer->loadMissing('activeDosenProgramStudiAssignments.programStudi');

        $assignments = $this->dosenBimbinganService->activeAssignmentsWithStudent($lecturer);
        $supervision = $this->dosenDashboardService->supervision($lecturer, $assignments);

        return Inertia::render('dosen/dashboard', [
            'summary' => [
                'lecturerName' => $lecturer->name,
                'programStudi' => $this->programStudiSummary($lecturer),
                'concentration' => $this->concentrationSummary($lecturer),
                'activeStudentCount' => $supervision['projects']->count(),
                'capacityLimit' => $this->dosenBimbinganService->lecturerQuota($lecturer),
            ],
            'actionItems' => $this->dosenDashboardService->actionItems($lecturer, $supervision['attention']),
            'agenda' => $this->dosenDashboardService->agenda($lecturer),
            'students' => $this->dosenDashboardService->studentCards($lecturer, $supervision),
        ]);
    }

    private function programStudiSummary(User $lecturer): ?string
    {
        $summary = $lecturer->activeDosenProgramStudiAssignments
            ->pluck('programStudi.name')
            ->filter()
            ->unique()
            ->values()
            ->implode(', ');

        return $summary !== '' ? $summary : $lecturer->dosenProfile?->programStudi?->name;
    }

    private function concentrationSummary(User $lecturer): ?string
    {
        $summary = $lecturer->activeDosenProgramStudiAssignments
            ->pluck('concentration')
            ->filter()
            ->unique()
            ->values()
            ->implode(', ');

        return $summary !== '' ? $summary : $lecturer->dosenProfile?->concentration;
    }
}
