<x-filament-widgets::widget class="sita-admin-agenda">
    @php
        $exams = $this->getExams();
        $days = $exams->groupBy(fn ($exam) => \App\Support\WitaDateTime::translated($exam->scheduled_for, 'l, j F Y'));
    @endphp

    <section aria-labelledby="admin-agenda-heading">
        <header class="sita-dashboard-heading">
            <h2 id="admin-agenda-heading">Agenda ujian {{ $this::DAYS_AHEAD }} hari ke depan</h2>
            <p>Jadwal dalam WITA.</p>
        </header>

        @if ($exams->isEmpty())
            <div class="sita-agenda-empty">
                <x-filament::icon icon="heroicon-o-calendar" aria-hidden="true" />
                <div>
                    <h3>Tidak ada ujian terjadwal</h3>
                    <p>Tidak ada ujian yang dijadwalkan dalam {{ $this::DAYS_AHEAD }} hari ke depan.</p>
                </div>
            </div>
        @else
            @foreach ($days as $day => $scheduled)
                <div class="sita-agenda-day">
                    <h3>{{ $day }}</h3>
                    <ol>
                        @foreach ($scheduled as $exam)
                            <li>
                                <a href="{{ \App\Filament\Resources\ThesisProjects\ThesisProjectResource::getUrl('view', ['record' => $exam->project]) }}" class="sita-agenda-link">
                                    <time datetime="{{ $exam->scheduled_for->toIso8601String() }}">
                                        {{ \App\Support\WitaDateTime::translated($exam->scheduled_for, 'H:i') }}
                                        @if ($exam->scheduled_until)
                                            <span>sampai {{ \App\Support\WitaDateTime::translated($exam->scheduled_until, 'H:i') }}</span>
                                        @endif
                                    </time>
                                    <span class="sita-agenda-person">
                                        <strong>{{ $exam->project?->student?->name ?? '-' }}</strong>
                                        <span>{{ \App\Support\AcademicTerminology::defenseLabel(\App\Support\AcademicTerminology::forProject($exam->project), $exam->type) }} #{{ $exam->attempt_no }}</span>
                                        <span>{{ trim(implode(' - ', array_filter([$exam->location, $exam->mode]))) ?: '-' }}</span>
                                    </span>
                                    <x-filament::icon icon="heroicon-m-arrow-up-right" aria-hidden="true" />
                                </a>
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endforeach
        @endif
    </section>
</x-filament-widgets::widget>
