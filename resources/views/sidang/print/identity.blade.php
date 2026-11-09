<div @class(['identity', 'identity-score' => ! ($full ?? true)])>
    @if ($full ?? true)
        <p><u>{{ $fields['examTitle'] }}</u></p>
    @else
        <table class="student-number"><tbody><tr>
            <td class="student-label">Nama Mahasiswa/NIM</td><td class="student-name">{{ $fields['student'] }}</td><td class="student-gap"></td>
            @for ($digit = 1; $digit <= 10; $digit++)<td class="nim-digit">{{ $fields['nim'.$digit] }}</td>@endfor
        </tr></tbody></table>
    @endif
    <dl>
        @if ($full ?? true)<dt>Hari, Tgl. / Waktu</dt><dd>{{ $fields['schedule'] }}</dd>@endif
        <dt>Judul {{ $fields['finalWork'] }}</dt><dd>{{ $fields['title'] }}</dd>
        @if ($full ?? true)
            <dt>Nama Mahasiswa</dt><dd>{{ $fields['student'] }}</dd>
            <dt>NIM Mahasiswa</dt><dd>{{ $fields['nim'] }}</dd>
        @endif
        @if ($full ?? true)<dt>Program Studi / Jenjang</dt><dd>{{ $fields['program'] }}</dd>@endif
        @unless ($full ?? true)<dt>Hari, Tgl. / Waktu</dt><dd>{{ $fields['schedule'] }}</dd>@endunless
        <dt>Pembimbing Utama</dt><dd>{{ $fields['supervisor1'] }}</dd>
        <dt>Co. Pembimbing</dt><dd>{{ $fields['supervisor2'] }}</dd>
    </dl>
</div>
