<?php

namespace App\Http\Controllers\Dosen;

use App\Enums\ThesisNoteVisibility;
use App\Http\Controllers\Controller;
use App\Http\Requests\Dosen\StoreThesisProjectNoteRequest;
use App\Http\Requests\Dosen\UpdateThesisProjectNoteRequest;
use App\Models\ThesisProject;
use App\Models\ThesisProjectNote;
use App\Services\ThesisProjectNoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ThesisProjectNoteController extends Controller
{
    public function __construct(
        private readonly ThesisProjectNoteService $noteService,
    ) {}

    public function store(StoreThesisProjectNoteRequest $request, ThesisProject $project): RedirectResponse
    {
        $validated = $request->validated();

        $this->noteService->create(
            $request->user(),
            $project,
            ThesisNoteVisibility::from($validated['visibility']),
            $validated['body'],
        );

        return back()->with('success', 'Catatan berhasil disimpan.');
    }

    public function update(UpdateThesisProjectNoteRequest $request, ThesisProjectNote $note): RedirectResponse
    {
        $validated = $request->validated();

        $this->noteService->update(
            $note,
            ThesisNoteVisibility::from($validated['visibility']),
            $validated['body'],
        );

        return back()->with('success', 'Catatan berhasil diperbarui.');
    }

    public function destroy(Request $request, ThesisProjectNote $note): RedirectResponse
    {
        abort_unless($request->user()?->can('delete', $note) === true, 403);

        $note->delete();

        return back()->with('success', 'Catatan berhasil dihapus.');
    }
}
