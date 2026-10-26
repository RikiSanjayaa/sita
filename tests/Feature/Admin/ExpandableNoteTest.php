<?php

it('keeps the complete long note escaped and available behind a disclosure', function () {
    $note = str_repeat("Saran pembimbing tentang metode penelitian.\n", 12).'<script>alert("private note")</script>';
    $html = view('filament.infolists.expandable-note', ['getState' => fn (): string => $note])->render();

    expect($html)->toContain('<details', 'aria-label="Catatan lengkap"', e($note))
        ->not->toContain('<script>');
});

it('does not hide short notes or create an empty disclosure', function (?string $note) {
    $html = view('filament.infolists.expandable-note', ['getState' => fn (): ?string => $note])->render();

    expect($html)->not->toContain('<details');
    if (filled($note)) {
        expect($html)->toContain(e($note));
    }
})->with([null, '', '-', 'Silakan lanjut ke bab berikutnya.']);
