<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $fields['examLabel'] }} #{{ $defense->attempt_no }} - {{ $fields['student'] }} - {{ $selectedForm === null ? 'Paket Cetak' : $forms[$selectedForm] }}</title>
    <style>
        @page { size: A4; margin: 12mm 15mm 12mm 20mm; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #ededed; color: #000; font: 12pt "Times New Roman", Times, serif; }
        .toolbar { font: 14px Arial, sans-serif; background: #fff; border-bottom: 1px solid #ccc; padding: 16px 24px; display: flex; flex-wrap: wrap; align-items: center; gap: 12px; }
        .toolbar p { flex: 1 1 280px; margin: 0; }
        .toolbar button, .toolbar a { font: inherit; padding: 12px 16px; border: 1px solid #444; border-radius: 6px; background: #fff; color: #111; text-decoration: none; cursor: pointer; }
        .toolbar button { background: #111; color: #fff; }
        .paper { width: 210mm; max-width: 100%; margin: 24px auto; padding: 12mm 15mm 12mm 20mm; background: #fff; min-height: 297mm; }
        .letterhead { display: flex; align-items: center; min-height: 32mm; gap: 5mm; margin-bottom: 6mm; }
        .letterhead img { width: 28mm; height: 25mm; object-fit: contain; }
        .letterhead > div { flex: 1; text-align: center; border-bottom: 3px double #000; padding-bottom: 2mm; }
        .letterhead strong { display: block; font-size: 12pt; }
        .letterhead .institution { font-size: 20pt; }
        .address { font-size: 9pt; }
        .contact { font-size: 8pt; }
        h1, h2 { text-align: center; font-size: 13pt; line-height: 1.25; margin: 0; font-weight: normal; }
        .identity-score + .rubric, .identity-score + table { margin-top: 3mm; }
        .paper:has(.identity-score) h1 { font-weight: bold; }
        h1 { margin-bottom: 6mm; }
        h1 span { display: block; }
        p { margin: 0 0 3mm; }
        .identity { margin: 5mm 0; }
        .identity dl { display: grid; grid-template-columns: 46mm minmax(0, 1fr); margin: 0; row-gap: 1.5mm; }
        .paper[data-form="nilai-penguji"], .paper[data-form="nilai-pembimbing"], .paper[data-form="rekap-nilai"], .paper[data-form="masukan"] { font-family: Arial, sans-serif; font-size: 10pt; }
        .letterhead { font-family: "Times New Roman", Times, serif; }
        .student-number { margin-bottom: 4mm; }
        .student-number td { padding: 1mm; vertical-align: middle; }
        .student-label { width: 29%; border: 0; }
        .student-name { width: 29%; }
        .student-gap { width: 4%; border: 0; }
        .nim-digit { width: 3.8%; text-align: center; }
        .identity-score dl { grid-template-columns: 54mm minmax(0, 1fr); row-gap: 1mm; }
        .identity-score dd { border-bottom: 1px solid #000; }
        dt, dd { margin: 0; }
        dd { position: relative; padding-left: 4mm; overflow-wrap: anywhere; }
        dd::before { content: ":"; position: absolute; left: 0; }
        table { border-collapse: collapse; width: 100%; table-layout: fixed; }
        th, td { border: 1px solid #000; padding: 1.5mm; vertical-align: top; overflow-wrap: anywhere; font-weight: normal; }
        th { text-align: center; font-weight: bold; }
        tr { break-inside: avoid; }
        .notes td { height: 8mm; }
        .notes td:first-child { white-space: pre-line; }
        .signature { margin: 7mm 0 0 auto; width: 72mm; text-align: center; break-inside: avoid; }
        .signature-space { margin-top: 18mm; min-height: 5mm; }
        .signature-space .blank { display: inline-block; min-width: 55mm; }
        .signature-space:empty::before { content: "(                                                      )"; white-space: pre; }
        .signatures { display: flex; justify-content: space-between; margin-top: 8mm; }
        .signatures .signature { margin: 0; }
        .signatures .signature-space { margin-top: 20mm; }
        .decision { font-weight: bold; margin: 5mm 0 5mm 12mm; padding-left: 6mm; }
        .decision li { margin-bottom: 5mm; }
        .attendance td { height: 12mm; }
        .attendance th { font-size: 10pt; font-weight: normal; }
        .rubric { font-size: 9pt; }
        .rubric td { padding: 1mm; }
        .rubric ul { margin: 1mm 0 0; padding-left: 4mm; }
        .rubric .total { height: 9mm; }
        .borderless { border: 0; }
        .total-shade { background: #aaa; }
        .grade-table { width: 40mm; font-size: 9pt; margin-top: 3mm; }
        .grade-table td { padding: .5mm 1mm; }
        .grade-table caption { text-align: left; margin-bottom: 1mm; }
        .score-footer { display: flex; align-items: start; justify-content: space-between; }
        .score-footer .signature { margin-top: 4mm; }
        .remark { font-size: 10pt; margin-top: 4mm; }
        .large-blank td { height: 120mm; }
        .questions td { height: 110mm; }
        .questions th:first-child { width: 6%; }
        .questions th:not(:first-child) { width: 47%; }
        @media screen and (max-width: 820px) { .paper { margin: 16px 0; padding: 16px; min-height: 0; } .letterhead { gap: 12px; } .letterhead img { width: 64px; height: 64px; } .letterhead .institution { font-size: 16pt; } .letterhead > div { min-width: 0; overflow-wrap: anywhere; } .identity dl { grid-template-columns: 42% minmax(0, 1fr); } .signatures .signature { width: 48%; } .signature-space .blank { min-width: 80%; } }
        @media print { body { background: #fff; } .toolbar { display: none; } .paper { width: auto; min-height: 0; padding: 0; margin: 0; break-after: page; } .paper:last-child { break-after: auto; } }
    </style>
</head>
<body>
    <nav class="toolbar" aria-label="Aksi cetak">
        <p>{{ $fields['examLabel'] }} #{{ $defense->attempt_no }} &middot; {{ $fields['student'] }}<br>Pilih kertas A4 dan nonaktifkan header serta footer browser saat mencetak.</p>
        <button type="button" onclick="window.print()">Cetak / Simpan PDF</button>
        <a href="{{ route('sidang.paper.download', ['defense' => $defense, 'lembar' => $selectedForm]) }}">Unduh Word (.docx)</a>
    </nav>
    <main>
        @foreach ($forms as $form => $label)
            <article class="paper" aria-label="{{ $label }}" data-form="{{ $form }}">
                @include('sidang.print.header')
                @include('sidang.print.form')
            </article>
        @endforeach
    </main>
</body>
</html>
