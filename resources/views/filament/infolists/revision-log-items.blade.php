@if ($items !== [])
    <ol>
        @foreach ($items as $index => $item)
            <li class="sita-revision-item">
                <div class="sita-revision-state">
                    <strong>{{ $index + 1 }}. {{ $item['location'] }}</strong>
                    @if ($item['priorityLabel'])
                        <span>{{ $item['priorityLabel'] }}</span>
                    @endif
                    <x-filament::badge :color="\App\Support\Filament\BadgeStyles::domainStatusColor('revision', match ($item['status']) { 'accepted' => 'resolved', 'addressed' => 'submitted', default => 'open' })">{{ $item['statusLabel'] }}</x-filament::badge>
                </div>
                <p class="sita-revision-copy">{{ $item['description'] }}</p>
                @if ($item['studentResponse'])
                    <p class="sita-revision-copy">Tanggapan mahasiswa: {{ $item['studentResponse'] }}@if ($item['studentResponseLocation']) ({{ $item['studentResponseLocation'] }})@endif</p>
                @endif
                @if ($item['verificationComment'])
                    <p class="sita-revision-copy">Komentar penguji: {{ $item['verificationComment'] }}</p>
                @endif
                @if ($item['verifiedAt'])
                    <p class="sita-revision-meta">Diverifikasi {{ $item['verifiedAt'] }}@if ($item['verifiedBy']) oleh {{ $item['verifiedBy'] }}@endif</p>
                @endif
            </li>
        @endforeach
    </ol>
@endif
