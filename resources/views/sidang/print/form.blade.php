@switch($form)
    @case('berita-acara')
        <h1>BERITA ACARA<span>{{ mb_strtoupper($fields['examTitle']) }}</span></h1>
        @include('sidang.print.identity')
        <p><u>Hasil Evaluasi Dosen Pembimbing dan Dewan Penguji memutuskan bahwa seminar dinyatakan:</u></p>
        <ol class="decision">
            @if ($fields['pass'] !== '')<li value="1">{{ $fields['pass'] }}</li>@endif
            @if ($fields['fail'] !== '')<li value="2">{{ $fields['fail'] }}</li>@endif
        </ol>
        <p style="text-align: right; margin-top: 10mm">{{ $fields['placeDate'] }}</p>
        <div class="signatures">
            @include('sidang.print.signature', ['signatureDate' => false, 'signatureRole' => 'Penguji 1', 'signatureName' => $fields['examiner1']])
            @include('sidang.print.signature', ['signatureDate' => false, 'signatureRole' => 'Penguji 2', 'signatureName' => $fields['examiner2']])
        </div>
        @break

    @case('revisi-penguji')
    @case('revisi-pembimbing')
        <h1>CATATAN PERBAIKAN {{ mb_strtoupper($fields['seminarTitle']) }}<span>(Sebagai Syarat Penilaian Yang Telah Diberikan)</span></h1>
        @include('sidang.print.identity')
        <p>Catatan Perbaikan:</p>
        <table class="notes"><tbody>
            @for ($row = 0; $row < 10; $row++)
                <tr><td>{{ $row === 0 ? ($fields[$form === 'revisi-penguji' ? 'examinerNotes' : 'supervisorNotes'] ?? '') : '' }}</td></tr>
            @endfor
        </tbody></table>
        @include('sidang.print.signature', ['signatureRole' => $form === 'revisi-penguji' ? 'Penguji 1 / 2,' : 'Pembimbing 1 / 2,', 'signatureNik' => ' '])
        @break

    @case('daftar-hadir')
        <h1>DAFTAR HADIR<span>DOSEN PENGUJI DAN MODERATOR {{ mb_strtoupper($fields['seminarTitle']) }}</span></h1>
        @include('sidang.print.identity')
        <p>UJIAN SEMINAR KE: I &ndash; II &ndash; III</p>
        <p>Keterangan: {{ trim($fields['pass'].' / '.$fields['fail'], ' /') }}</p>
        <table class="attendance"><colgroup><col style="width: 8%"><col style="width: 28%"><col style="width: 23%"><col style="width: 23%"><col style="width: 18%"></colgroup>
            <thead><tr><th>NO</th><th>DOSEN PENGUJI</th><th>DOSEN PENGUJI YANG TIDAK HADIR</th><th>DOSEN PENGUJI PENGGANTI</th><th>TANDA TANGAN</th></tr></thead>
            <tbody>
                <tr><td>1.</td><td>{{ $fields['examiner1'] }}</td><td></td><td></td><td></td></tr>
                <tr><td>2.</td><td>{{ $fields['examiner2'] }}</td><td></td><td></td><td></td></tr>
                <tr><td colspan="5"></td></tr>
                <tr><th colspan="2">MODERATOR</th><th>MODERATOR YANG TIDAK HADIR</th><th>MODERATOR PENGGANTI</th><th>TANDA TANGAN</th></tr>
                <tr><td colspan="2">{{ $fields['moderator'] }}</td><td></td><td></td><td></td></tr>
            </tbody>
        </table>
        @include('sidang.print.signature', ['signatureCaption' => 'Mengetahui,', 'signatureRole' => 'Ka.Prodi '.$fields['program'].',', 'signatureName' => $fields['head'], 'signatureNik' => $fields['headNik'] ?: ' '])
        @break

    @case('nilai-penguji')
    @case('nilai-pembimbing')
        <h1>FORMULIR PENILAIAN {{ $form === 'nilai-penguji' ? 'PENGUJI' : 'PEMBIMBING' }} {{ mb_strtoupper($fields['finalWork']) }}</h1>
        @include('sidang.print.identity', ['full' => false])
        @include('sidang.print.rubric', ['rubricKey' => $form === 'nilai-penguji' ? 'examiner_rubric' : 'supervisor_rubric'])
        <div class="score-footer">
            @if ($form === 'nilai-penguji')
                @include('sidang.print.grades')
            @else
                <p class="remark">Keterangan:<br>- Range nilai untuk setiap unsur adalah 0 - 100</p>
            @endif
            @include('sidang.print.signature', ['signatureCaption' => 'Yang menilai,', 'signatureRole' => $form === 'nilai-penguji' ? 'Penguji 1 / 2' : 'Pembimbing 1 / 2'])
        </div>
        @break

    @case('rekap-nilai')
        <h1>LEMBAR TOTAL PENILAIAN AKHIR {{ mb_strtoupper($fields['seminarTitle']) }}</h1>
        @include('sidang.print.identity', ['full' => false])
        <table><colgroup><col style="width: 9%"><col style="width: 39%"><col style="width: 17%"><col style="width: 17%"><col style="width: 18%"></colgroup>
            <thead><tr><th>NO.</th><th>YANG MEMBERIKAN NILAI</th><th>NILAI</th><th>BOBOT</th><th>HASIL</th></tr></thead>
            <tbody>
                @foreach (['examiner1' => 'Penguji I', 'examiner2' => 'Penguji II', 'supervisor' => 'Pembimbing'] as $key => $name)
                    <tr><td>{{ $loop->iteration }}.</td><td>{{ $name }}</td><td>{{ $fields[$key.'Score'] }}</td><td>{{ $fields[$key.'Weight'] }}</td><td>{{ $fields[$key.'Weighted'] }}</td></tr>
                @endforeach
                <tr><td colspan="2" class="total-shade"></td><th colspan="2">TOTAL NILAI</th><td>{{ $fields['total'] }}</td></tr>
            </tbody>
        </table>
        <div class="score-footer">
            <div>
                <p class="remark">Keterangan:<br>- Range nilai untuk setiap unsur adalah 0 - 100</p>
                @include('sidang.print.grades')
            </div>
            @include('sidang.print.signature', ['signatureRole' => 'Pembimbing'])
        </div>
        @break

    @case('masukan')
        <h1>FORMULIR MASUKAN<span>UNTUK DOSEN PEMBIMBING</span></h1>
        @include('sidang.print.identity', ['full' => false])
        <table class="large-blank"><thead><tr><th style="width: 9%">NO</th><th style="width: 66%">HAL YANG PERLU DIPERHATIKAN</th><th>PROSENTASE DARI 100 %</th></tr></thead><tbody><tr><td></td><td></td><td></td></tr></tbody></table>
        @include('sidang.print.signature', ['signatureRole' => 'Atas Nama Penguji,'])
        @break

    @case('tanya-jawab')
        <h1>DAFTAR PERTANYAAN PENGUJI<span>DAN JAWABAN PENYAJI</span></h1>
        @include('sidang.print.identity')
        <table class="questions"><thead><tr><th>NO</th><th>PERTANYAAN</th><th>JAWABAN</th></tr></thead><tbody><tr><td></td><td></td><td></td></tr></tbody></table>
        @include('sidang.print.signature', ['signatureRole' => 'Penguji'])
        @break
@endswitch
