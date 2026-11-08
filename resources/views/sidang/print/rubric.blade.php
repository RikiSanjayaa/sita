@php
    $default = $settings[$rubricKey] === \App\Support\SidangSettings::DEFAULTS[$rubricKey];
    $details = $rubricKey === 'examiner_rubric' ? [
        ['Penguasaan teori dasar', 'Penguasaan masalah', 'Kemampuan menganalisa', 'Perancangan'],
        ['Kemampuan untuk memahami pertanyaan penguji dan memberikan tanggapan dan jawaban yang sesuai.', 'Memiliki wawasan yang luas terkait '.mb_strtolower($fields['finalWork']).' yang diangkat'],
        ['Orisinalitas (program/alat dibuat sendiri)', 'Kontribusi nilai aplikatif', 'Output valid'],
        ['Kelengkapan teori yang digunakan sebagai referensi untuk mendukung penulisan '.mb_strtolower($fields['finalWork']).'.', 'Kesesuaian dalam mengutip referensi dengan aturan di dalam buku pedoman penulisan '.$fields['finalWork'].'/TA '.$fields['institution'].'.'],
    ] : [
        ['Penguasaan teori dasar', 'Penguasaan masalah', 'Kemampuan menganalisa', 'Perancangan'],
        ['Kemampuan memahami pengarahan pembimbing', 'Kemampuan melakukan perbaikan sesuai saran pembimbing', 'Kemampuan mengembangkan menjadi lebih baik'],
        ['Orisinalitas (program dibuat sendiri)', 'Output valid', 'Kemampuan pemrograman/implementasi'],
        ['Kemampuan mengutip referensi', 'Penguasaan metode penelitian'],
    ];
@endphp
<table class="rubric"><colgroup><col style="width: 8%"><col style="width: 56%"><col style="width: 12%"><col style="width: 12%"><col style="width: 12%"></colgroup>
    <thead><tr><th>NO</th><th>UNSUR YANG DINILAI</th><th>NILAI</th><th>BOBOT</th><th>HASIL</th></tr></thead>
    <tbody>
        @foreach (\App\Support\SidangSettings::rubric($settings[$rubricKey]) as $row)
            <tr>
                <td>{{ $loop->iteration }}.</td>
                <td>{{ $row['label'] }}
                    @if ($default && isset($details[$loop->index]))
                        <ul>@foreach ($details[$loop->index] as $detail)<li>{{ $detail }}</li>@endforeach</ul>
                    @endif
                </td>
                <td></td><td>{{ $row['weight'] }} %</td><td></td>
            </tr>
        @endforeach
        <tr class="total"><td colspan="3" class="borderless"></td><td>Nilai {{ $fields['finalWork'] }}</td><td></td></tr>
    </tbody>
</table>
