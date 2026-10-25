<div class="sita-revision-logs">
    @foreach ($getState() as $attempt)
        <section class="sita-revision-log" aria-label="Log revisi {{ $attempt['label'] }}">
            <h3>Log revisi {{ $attempt['label'] }}</h3>
            @if ($attempt['log']['examiners'] === [] && $attempt['log']['overrides'] === [])
                <p class="sita-revision-meta">Belum ada catatan revisi untuk ujian ini.</p>
            @else
                <p class="sita-revision-meta">Riwayat per putaran dan dokumen, dikelompokkan per penguji.</p>
            @endif
            @foreach ($attempt['log']['examiners'] as $examiner)
                <div class="sita-revision-group">
                    <h4>{{ $examiner['name'] }}</h4>
                    @foreach ($examiner['revisions'] as $revision)
                        <div class="sita-revision-group">
                            <div class="sita-revision-state">
                                <x-filament::badge :color="\App\Support\Filament\BadgeStyles::domainStatusColor('revision', $revision['status'])">{{ $revision['statusLabel'] }}</x-filament::badge>
                                <span class="sita-revision-meta">Diminta {{ $revision['requestedAt'] }}</span>
                            </div>
                            @if ($revision['dueAt'])
                                <p class="sita-revision-meta">Batas revisi: {{ $revision['dueAt'] }}</p>
                            @endif
                            @if ($revision['notes'] && !(count($revision['items']) === 1 && $revision['items'][0]['description'] === $revision['notes']))
                                <p class="sita-revision-copy">{{ $revision['notes'] }}</p>
                            @endif
                            @foreach ($revision['rounds'] as $round)
                                <div class="sita-revision-group">
                                    <div class="sita-revision-state">
                                        <h5>Putaran {{ $round['roundNo'] }}</h5>
                                        <x-filament::badge :color="\App\Support\Filament\BadgeStyles::domainStatusColor('revision', match ($round['status']) { 'closed' => 'resolved', 'awaiting_review' => 'submitted', default => 'open' })">{{ $round['statusLabel'] }}</x-filament::badge>
                                    </div>
                                    <dl class="sita-revision-meta">
                                        @foreach (['openedAt' => 'Dibuka', 'submittedAt' => 'Dikirim', 'reviewedAt' => 'Diverifikasi'] as $key => $label)
                                            @if ($round[$key])
                                                <div><dt>{{ $label }}: </dt><dd>{{ $round[$key] }}</dd></div>
                                            @endif
                                        @endforeach
                                    </dl>
                                    <p class="sita-revision-meta">{{ $round['itemStateLabel'] }}</p>
                                    @include('filament.infolists.revision-log-items', ['items' => $round['items']])
                                    @include('filament.infolists.revision-log-documents', ['documents' => $round['annotations'], 'label' => 'Naskah beranotasi'])
                                    @if ($round['manuscript'])
                                        @include('filament.infolists.revision-log-documents', ['documents' => [$round['manuscript']], 'label' => 'Naskah perbaikan'])
                                    @endif
                                </div>
                            @endforeach
                            @include('filament.infolists.revision-log-items', ['items' => $revision['unassignedItems']])
                            @include('filament.infolists.revision-log-documents', ['documents' => $revision['unassignedAnnotations'], 'label' => 'Naskah beranotasi'])
                            @if ($revision['rounds'] === [] && $revision['submittedAt'])
                                <p class="sita-revision-meta">Dikirim {{ $revision['submittedAt'] }}</p>
                            @endif
                            @if ($revision['resolvedAt'])
                                <p class="sita-revision-meta">Diselesaikan {{ $revision['resolvedAt'] }}@if ($revision['resolvedBy']) oleh {{ $revision['resolvedBy'] }}@endif</p>
                            @endif
                            @if ($revision['resolutionNotes'])
                                <p class="sita-revision-copy">Penyelesaian: {{ $revision['resolutionNotes'] }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endforeach
            @if ($attempt['log']['overrides'] !== [])
                <div class="sita-revision-group">
                    <h4>Override admin</h4>
                    <ol>
                        @foreach ($attempt['log']['overrides'] as $override)
                            <li>
                                <p><strong>{{ $override['actor'] }}</strong></p>
                                <p class="sita-revision-meta">{{ $override['occurredAt'] }}</p>
                                <p class="sita-revision-copy">{{ $override['description'] }}</p>
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endif
        </section>
    @endforeach
</div>
