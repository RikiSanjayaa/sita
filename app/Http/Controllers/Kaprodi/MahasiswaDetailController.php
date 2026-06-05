<?php

namespace App\Http\Controllers\Kaprodi;

use App\Http\Controllers\Controller;
use App\Models\ProgramStudi;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MahasiswaDetailController extends Controller
{
    public function __invoke(Request $request, User $student): RedirectResponse
    {
        $programStudi = $request->user()?->kaprodiAssignment?->programStudi;
        $profile = $student->mahasiswaProfile;

        abort_unless($programStudi instanceof ProgramStudi, 403);
        abort_unless($profile !== null && (int) $profile->program_studi_id === (int) $programStudi->id, 404);

        return redirect()->route('users.profile.show', ['user' => $student->id]);
    }
}
