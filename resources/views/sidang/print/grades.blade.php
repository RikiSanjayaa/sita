<table class="grade-table">
    <caption>Range Nilai</caption>
    <tbody>
        @foreach ([...\App\Support\SidangSettings::GRADES, 'E'] as $index => $grade)
            <tr><td>{{ $fields['gradeRange'.$index] }}</td><td>{{ $grade }}</td></tr>
        @endforeach
    </tbody>
</table>
