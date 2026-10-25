@foreach ($documents as $document)
    <div class="sita-revision-state">
        <a href="{{ $document['viewUrl'] }}" target="_blank" rel="noreferrer">{{ $label }}: {{ $document['fileName'] }}</a>
        <a href="{{ $document['downloadUrl'] }}" aria-label="Unduh {{ strtolower($label) }} {{ $document['fileName'] }}">Unduh</a>
    </div>
@endforeach
