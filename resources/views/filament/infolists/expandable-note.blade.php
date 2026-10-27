@php
    $note = (string) ($getState() ?? '');
    $isLong = mb_strlen($note) > 140 || substr_count($note, "\n") > 2;
@endphp

@if (trim($note) === '' || $note === '-')
    <span>-</span>
@elseif ($isLong)
    <details class="sita-expandable-note">
        <summary>
            <span class="sita-note-preview">{{ \Illuminate\Support\Str::limit($note, 140) }}</span>
            <span class="sita-note-open">Baca lengkap</span>
            <span class="sita-note-close">Tutup catatan</span>
        </summary>
        <div class="sita-note-full" role="region" aria-label="Catatan lengkap">{{ $note }}</div>
    </details>
@else
    <div class="sita-note-full">{{ $note }}</div>
@endif
