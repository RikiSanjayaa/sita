<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Berkas {{ $terms['finalExam'] }} - {{ $project->student?->name }}</title>
    <style>
        @page { size: A4 portrait; margin: 14mm 16mm; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #eef2f7; color: #121722; font: 11pt/1.38 Georgia, serif; }
        .toolbar { padding: 14px 24px; background: #fff; border-bottom: 1px solid #d5dde8; font: 14px system-ui, sans-serif; }
        .toolbar button { padding: 8px 14px; border: 1px solid #1d4ed8; border-radius: 6px; background: #1d4ed8; color: white; font: inherit; cursor: pointer; }
        .sheet { max-width: 210mm; padding: 14mm 16mm; margin: 12px auto; background: white; }
        header { display: flex; align-items: center; justify-content: center; gap: 16px; text-align: center; border-bottom: 3px double; padding-bottom: 8px; }
        header img { width: 48px; height: 48px; }
        header strong { display: block; font-size: 18pt; }
        h1 { font-size: 14pt; text-align: center; margin: 20px 0 12px; }
        h2 { font-size: 12pt; margin-top: 18px; }
        dl { display: grid; grid-template-columns: 38mm 1fr; gap: 3px 12px; margin-bottom: 16px; }
        dt { font-weight: bold; }
        dd { margin: 0; overflow-wrap: anywhere; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #323d4f; padding: 5px 7px; text-align: left; }
        tr, .signatures { break-inside: avoid; }
        .blank td { height: 28px; }
        .signatures { display: flex; flex-wrap: wrap; gap: 16px; margin-top: 24px; }
        .signature { min-width: 42mm; margin-top: 8px; }
        .signature-space { height: 40px; }
        @media print {
            body { background: white; }
            .toolbar { display: none; }
            .sheet { margin: 0; padding: 0; max-width: none; }
            .sheet + .sheet { break-before: page; }
            thead { display: table-header-group; }
        }
        @media screen and (max-width: 540px) {
            .sheet { padding: 18px; overflow-x: auto; }
            dl { grid-template-columns: 7em 1fr; }
        }
    </style>
</head>
<body>
@php
    $program = $project->programStudi;
    $panel = $defense->examiners->sortBy('order_no')->values();
    $supervisors = $panel->filter(fn ($member) => $member->isSupervisorRole())->sortBy('role')->values();
    $scores = $defense->sidang_paper['scores'] ?? [];
    $completed = $defense->status === 'completed';
    $examiners = $panel->where('role', 'examiner')->values();
    $assessors = [
        'examiner_1' => $examiners->get(0)?->lecturer?->name,
        'examiner_2' => $examiners->get(1)?->lecturer?->name,
        'supervisor' => $supervisors->count() === 1 ? $supervisors->first()?->lecturer?->name : null,
    ];
    $head = $program?->kaprodiAssignments->firstWhere('is_primary', true)?->user;
    $copies = [
        'nilai-penguji' => $panel->where('role', 'examiner')->values()->all(),
        'nilai-pembimbing' => $scores === [] ? $supervisors->all() : [$panel->find($scores['supervisor']['panel_id'])],
        'revisi-penguji' => $panel->where('role', 'examiner')->values()->all(),
        'revisi-pembimbing' => $supervisors->all(),
    ];
@endphp
<div class="toolbar">
    <button type="button" onclick="window.print()">Cetak / Simpan sebagai PDF</button>
</div>
@foreach ($forms as $key => $heading)
    @foreach (($copies[$key] ?? []) ?: [null] as $member)
        <article class="sheet">
            <header>
                <img src="{{ asset('Logo-UBG.svg') }}" alt="">
                <div>
                    <strong>{{ $settings['heading'] }}</strong>
                    <div>{{ $program?->faculty?->name }}</div>
                    <div>Program Studi {{ $terms['degreeLevel'] }} {{ $program?->name }}</div>
                </div>
            </header>
            <h1>{{ $heading }} {{ $terms['finalExam'] }}</h1>
            <dl>
                <dt>Mahasiswa</dt><dd>{{ $project->student?->name }}</dd>
                <dt>NIM</dt><dd>{{ $project->student?->mahasiswaProfile?->nim }}</dd>
                <dt>Program Studi</dt><dd>{{ $terms['degreeLevel'] }} {{ $program?->name }}</dd>
                <dt>Judul {{ $terms['finalWork'] }}</dt><dd>{{ $defense->titleVersion?->title_id }}</dd>
                <dt>Jadwal</dt><dd>{{ \App\Support\WitaDateTime::translatedDateRange($defense->scheduled_for, $defense->scheduled_until) }}</dd>
                <dt>Lokasi / Mode</dt><dd>{{ $defense->location }} / {{ $defense->mode }}</dd>
                <dt>Percobaan</dt><dd>{{ $defense->attempt_no }}</dd>
                @foreach ($supervisors as $supervisor)
                    <dt>{{ $supervisor->role === 'primary_supervisor' ? 'Pembimbing 1' : 'Pembimbing 2' }}</dt><dd>{{ $supervisor->lecturer?->name }}</dd>
                @endforeach
                @if ($defense->moderator)
                    <dt>Moderator</dt><dd>{{ $defense->moderator->name }}</dd>
                @endif
                @if ($defense->sidang_sk)
                    <dt>Nomor SK</dt><dd>{{ $defense->sidang_sk }}</dd>
                @endif
            </dl>
            @if ($key === 'berita-acara')
                <h2>Keputusan</h2>
                <p>Berdasarkan evaluasi dosen pembimbing dan dewan penguji, mahasiswa dinyatakan</p>
                @if ($completed)
                    <p>{{ match ($defense->result) { 'pass', 'pass_with_revision' => 'LULUS', 'fail' => 'TIDAK LULUS', default => '........................' } }}</p>
                @else
                    <p>□ LULUS &nbsp;&nbsp;&nbsp; □ TIDAK LULUS</p>
                @endif
                @if ($completed && $defense->sidang_paper)
                    <p>Nilai akhir {{ $defense->sidang_paper['value'] }} ({{ $defense->sidang_paper['grade'] }})</p>
                @endif
                <div class="signatures">
                    @foreach ($panel as $person)
                        <div class="signature">
                            {{ $person->isSupervisorRole() ? 'Pembimbing' : 'Penguji' }}
                            <div class="signature-space"></div>{{ $person->lecturer?->name }}
                        </div>
                    @endforeach
                </div>
            @elseif ($key === 'daftar-hadir')
                <table><thead><tr><th>Dosen</th><th>Peran</th><th>Tidak Hadir</th><th>Pengganti</th><th>Tanda Tangan</th></tr></thead><tbody>
                    @foreach ($panel as $person)
                        <tr class="blank"><td>{{ $person->lecturer?->name }}</td><td>{{ $person->isSupervisorRole() ? 'Pembimbing' : 'Penguji' }}</td><td></td><td></td><td></td></tr>
                    @endforeach
                    <tr class="blank"><td>{{ $defense->moderator?->name }}</td><td>Moderator</td><td></td><td></td><td></td></tr>
                </tbody></table>
            @elseif (in_array($key, ['nilai-penguji', 'nilai-pembimbing'], true))
                <table><thead><tr><th>No.</th><th>Unsur yang Dinilai</th><th>Nilai</th><th>Bobot</th><th>Hasil</th></tr></thead><tbody>
                    @foreach (\App\Support\SidangSettings::rubric($settings[$key === 'nilai-penguji' ? 'examiner_rubric' : 'supervisor_rubric']) as $component)
                        <tr class="blank"><td>{{ $loop->iteration }}</td><td>{{ $component['label'] }}</td><td></td><td>{{ $component['weight'] }}%</td><td></td></tr>
                    @endforeach
                    <tr class="blank"><th colspan="4">Jumlah nilai</th><td>{{ $completed ? $member?->score : null }}</td></tr>
                </tbody></table>
                <p>Nilai tiap unsur 0–100.
                    @foreach (\App\Support\SidangSettings::GRADE_KEYS as $index => $gradeKey)
                        {{ \App\Support\SidangSettings::GRADES[$index] }} ≥ {{ $settings[$gradeKey] }} ·
                    @endforeach
                    E &lt; {{ $settings['d'] }}
                </p>
            @elseif ($key === 'rekap-nilai')
                <table><thead><tr><th>Penilai</th><th>Nama</th><th>Bobot</th><th>Nilai</th></tr></thead><tbody>
                    @foreach (\App\Services\SidangPaperService::ROLES as $role => $label)
                        <tr class="blank"><td>{{ $label }}</td><td>{{ $scores[$role]['name'] ?? $assessors[$role] }}</td><td>{{ $settings[$role] }}%</td><td>{{ $completed ? ($scores[$role]['value'] ?? null) : null }}</td></tr>
                    @endforeach
                    <tr class="blank"><th colspan="3">Nilai akhir / Grade</th><td>{{ $completed && $defense->sidang_paper ? $defense->sidang_paper['value'].' / '.$defense->sidang_paper['grade'] : '' }}</td></tr>
                </tbody></table>
            @elseif (in_array($key, ['revisi-penguji', 'revisi-pembimbing'], true))
                <table><thead><tr><th>Catatan Perbaikan</th></tr></thead><tbody>
                    @if ($completed && $member)
                        @foreach ($defense->revisions->where('requested_by_user_id', $member->lecturer_user_id) as $revision)
                            @if (filled($revision->notes))<tr><td>{{ $revision->notes }}</td></tr>@endif
                            @foreach ($revision->items as $item)
                                <tr><td>{{ $item->description }}</td></tr>
                            @endforeach
                        @endforeach
                    @endif
                    @for ($line = 0; $line < 8; $line++)<tr class="blank"><td></td></tr>@endfor
                </tbody></table>
            @else
                <table><thead><tr><th>No.</th><th>{{ $key === 'masukan' ? 'Hal yang Perlu Diperhatikan' : 'Pertanyaan' }}</th><th>{{ $key === 'masukan' ? 'Persentase' : 'Jawaban' }}</th></tr></thead><tbody>
                    @for ($line = 1; $line <= 8; $line++)<tr class="blank"><td>{{ $line }}</td><td></td><td></td></tr>@endfor
                </tbody></table>
            @endif
            <div class="signatures">
                <div class="signature">
                    {{ $settings['city'] }}, {{ \App\Support\WitaDateTime::translated($defense->scheduled_for, 'd F Y', withLabel: false) }}
                    @if ($member)
                        <div>Yang menilai,</div>
                        <div class="signature-space"></div>{{ $member->lecturer?->name }}
                        <div>{{ $member->lecturer?->dosenProfile?->nik ? 'NIK '.$member->lecturer->dosenProfile->nik : '' }}</div>
                    @elseif ($key === 'daftar-hadir')
                        <div>Mengetahui, Ketua Program Studi</div>
                        <div class="signature-space"></div>{{ $head?->name }}
                        <div>{{ $head?->dosenProfile?->nik ? 'NIK '.$head->dosenProfile->nik : '' }}</div>
                    @endif
                </div>
            </div>
        </article>
    @endforeach
@endforeach
</body>
</html>
