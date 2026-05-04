<?php

namespace App\Http\Controllers\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Support\StudentGuideContent;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PanduanController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $student = $request->user();
        abort_if($student === null, 401);

        $programStudi = $student->mahasiswaProfile?->programStudi;

        return Inertia::render('panduan', StudentGuideContent::toPageProps(
            $programStudi?->student_guide_content,
        ));
    }
}
