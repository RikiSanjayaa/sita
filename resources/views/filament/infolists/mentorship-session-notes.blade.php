<div class="sita-session-notes" aria-label="Catatan sesi bimbingan">
    @forelse ($getState() as $session)
        <article class="sita-session-note-context" data-monitoring-session="{{ $session['id'] }}">
            <h3>{{ $session['topic'] }}</h3>
            <div class="sita-session-note-state">
                <x-filament::badge :color="\App\Support\Filament\BadgeStyles::domainStatusColor('schedule', $session['status'])">
                    {{ match ($session['status']) {
                        'pending' => 'Menunggu persetujuan',
                        'approved' => 'Disetujui',
                        'scheduled' => 'Terjadwal',
                        'rescheduled' => 'Dijadwalkan ulang',
                        'rejected' => 'Ditolak',
                        'completed' => 'Selesai',
                        'cancelled' => 'Dibatalkan',
                        default => $session['status'],
                    } }}
                </x-filament::badge>
            </div>
            <dl class="sita-session-note-facts">
                <div><dt>Mahasiswa</dt><dd>{{ $session['student'] }}</dd></div>
                <div><dt>Dosen</dt><dd>{{ $session['lecturer'] }}</dd></div>
                <div>
                    <dt>Waktu sesi</dt>
                    <dd>
                        @if ($session['scheduledAt'])
                            <time datetime="{{ $session['scheduledAt'] }}">{{ \App\Support\WitaDateTime::translated(\Carbon\CarbonImmutable::parse($session['scheduledAt']), 'd M Y H:i') }}</time>
                        @else
                            Waktu sesi tidak tercatat
                        @endif
                    </dd>
                </div>
            </dl>
            @forelse ($session['sessionNotes']['entries'] as $entry)
                <section class="sita-session-note-entry" aria-label="Catatan {{ $entry['authorName'] }}">
                    <h4>{{ $entry['authorName'] }}</h4>
                    <p class="sita-session-note-meta">{{ $entry['authorRole'] }}</p>
                    @if ($entry['updatedAt'])
                        <p class="sita-session-note-meta">Diperbarui <time datetime="{{ $entry['updatedAt'] }}">{{ \App\Support\WitaDateTime::translated(\Carbon\CarbonImmutable::parse($entry['updatedAt']), 'd M Y H:i') }}</time></p>
                    @endif
                    <p class="sita-session-note-body">{{ $entry['body'] }}</p>
                </section>
            @empty
                <p class="sita-session-note-meta">Belum ada catatan sesi bimbingan.</p>
            @endforelse
            @if ($session['sessionNotes']['readOnlyReason'])
                <p class="sita-session-note-meta">{{ $session['sessionNotes']['readOnlyReason'] }}</p>
            @endif
        </article>
    @empty
        <p class="sita-session-note-meta">Belum ada catatan sesi bimbingan.</p>
    @endforelse
</div>
