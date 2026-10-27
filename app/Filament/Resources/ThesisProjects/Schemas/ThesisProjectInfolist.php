<?php

namespace App\Filament\Resources\ThesisProjects\Schemas;

use App\Filament\Resources\ThesisProjects\Tables\ThesisProjectsTable;
use App\Models\MentorshipDocument;
use App\Models\ThesisDefense;
use App\Models\ThesisDocument;
use App\Models\ThesisProject;
use App\Models\ThesisProjectEvent;
use App\Models\ThesisProjectNote;
use App\Models\ThesisProjectTitle;
use App\Models\ThesisRevision;
use App\Models\ThesisSupervisorAssignment;
use App\Models\User;
use App\Services\MentorshipSessionNoteService;
use App\Support\AcademicTerminology;
use App\Support\DefenseRevisionLog;
use App\Support\Filament\BadgeStyles;
use App\Support\WitaDateTime;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class ThesisProjectInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(fn(ThesisProject $record): string => 'Ringkasan '.AcademicTerminology::forProject($record)['finalWork'])
                    ->columns(2)
                    ->schema([
                        TextEntry::make('student.name')
                            ->label('Mahasiswa'),
                        TextEntry::make('student.email')
                            ->label('Email Mahasiswa')
                            ->copyable()
                            ->placeholder('-'),
                        TextEntry::make('student.phone_number')
                            ->label('Nomor HP')
                            ->copyable()
                            ->placeholder('Belum diisi'),
                        TextEntry::make('student.mahasiswaProfile.nim')
                            ->label('NIM')
                            ->copyable()
                            ->placeholder('-'),
                        TextEntry::make('programStudi.name')
                            ->label('Program Studi')
                            ->placeholder('-')
                            ->badge()
                            ->color(fn(?string $state): string => BadgeStyles::programStudiColor($state))
                            ->icon(BadgeStyles::programStudiIcon()),
                        TextEntry::make('active_title')
                            ->label('Judul Aktif')
                            ->state(fn(ThesisProject $record): string => self::resolveCurrentTitle($record)?->title_id ?? '-')
                            ->columnSpanFull(),
                        TextEntry::make('phase')
                            ->label('Fase')
                            ->badge()
                            ->color(fn(string $state): string => BadgeStyles::phaseColor($state))
                            ->icon(fn(string $state): string => BadgeStyles::phaseIcon($state))
                            ->formatStateUsing(fn(string $state): string => ThesisProjectsTable::phaseLabel($state)),
                        TextEntry::make('state')
                            ->label('State')
                            ->badge()
                            ->color(fn(string $state): string => BadgeStyles::stateColor($state))
                            ->formatStateUsing(fn(string $state): string => ThesisProjectsTable::stateLabel($state)),
                        TextEntry::make('started_at')
                            ->label('Mulai')
                            ->formatStateUsing(fn($state): string => WitaDateTime::translated($state, 'd M Y H:i'))
                            ->placeholder('-'),
                        TextEntry::make('completed_at')
                            ->label('Selesai')
                            ->formatStateUsing(fn($state): string => WitaDateTime::translated($state, 'd M Y H:i'))
                            ->placeholder('-'),
                        TextEntry::make('mentorship_valid_from')
                            ->label('Masa aktif bimbingan mulai')
                            ->formatStateUsing(fn($state): string => WitaDateTime::translated($state, 'd M Y', withLabel: false))
                            ->placeholder('-'),
                        TextEntry::make('mentorship_valid_until')
                            ->label('Masa aktif bimbingan berakhir')
                            ->formatStateUsing(fn($state): string => WitaDateTime::translated($state, 'd M Y', withLabel: false))
                            ->placeholder('-'),
                        TextEntry::make('notes')
                            ->label('Catatan Admin')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),
                Section::make('Status dan Langkah Berikutnya')
                    ->description(fn(ThesisProject $record): string => 'Ringkasan singkat untuk membantu admin memahami kondisi '.strtolower(AcademicTerminology::forProject($record)['finalWork']).' dan tindakan berikutnya.')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('next_admin_step')
                            ->label('Aksi Berikutnya')
                            ->state(fn(ThesisProject $record): string => self::nextAdminStepLabel($record))
                            ->badge()
                            ->color(fn(ThesisProject $record): string => self::nextAdminStepColor($record)),
                        TextEntry::make('workflow_attention')
                            ->label('Fokus Saat Ini')
                            ->state(fn(ThesisProject $record): string => self::workflowAttention($record))
                            ->placeholder('-'),
                        TextEntry::make('latest_sempro_summary')
                            ->label(fn(ThesisProject $record): string => AcademicTerminology::forProject($record)['proposalExamShort'].' Terakhir')
                            ->state(fn(ThesisProject $record): string => self::latestDefenseSummary($record, 'sempro')),
                        TextEntry::make('latest_sidang_summary')
                            ->label(fn(ThesisProject $record): string => AcademicTerminology::forProject($record)['finalExam'].' Terakhir')
                            ->state(fn(ThesisProject $record): string => self::latestDefenseSummary($record, 'sidang')),
                        TextEntry::make('active_supervisors_summary')
                            ->label('Pembimbing Aktif')
                            ->state(fn(ThesisProject $record): string => self::activeSupervisorsSummary($record))
                            ->columnSpanFull(),
                    ]),
                Tabs::make('Detail')
                    ->contained(false)
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('Ringkasan')
                            ->icon('heroicon-m-identification')
                            ->schema([
                                Section::make('Riwayat Judul')
                                    ->schema([
                                        self::titleHistoryEntry(),
                                    ]),
                                Section::make('Riwayat Pembimbing')
                                    ->schema([
                                        self::supervisorHistoryEntry(),
                                    ]),
                            ]),
                        Tab::make('Ujian')
                            ->icon('heroicon-m-academic-cap')
                            ->schema([
                                Section::make(fn(ThesisProject $record): string => 'Riwayat '.AcademicTerminology::forProject($record)['proposalExamShort'])
                                    ->schema([
                                        self::defenseEntry('sempro'),
                                        self::revisionLogEntry('sempro'),
                                    ]),
                                Section::make(fn(ThesisProject $record): string => 'Riwayat '.AcademicTerminology::forProject($record)['finalExam'])
                                    ->schema([
                                        self::defenseEntry('sidang'),
                                        self::revisionLogEntry('sidang'),
                                    ]),
                            ]),
                        Tab::make('Dokumen')
                            ->icon('heroicon-m-folder-open')
                            ->schema([
                                Section::make(fn(ThesisProject $record): string => 'Dokumen '.AcademicTerminology::forProject($record)['finalWork'])
                                    ->schema([
                                        self::thesisDocumentsEntry(),
                                    ]),
                                Section::make('Dokumen Bimbingan')
                                    ->schema([
                                        self::mentorshipDocumentsEntry(),
                                    ]),
                                Section::make('Ringkasan Arsip Bimbingan')
                                    ->description('Data bimbingan sudah diarsipkan. Chat, jadwal, dan dokumen draft dihapus; ringkasan di bawah dicatat sebelum penghapusan.')
                                    ->schema([
                                        TextEntry::make('archive_summary_lines')
                                            ->label('Ringkasan arsip')
                                            ->state(fn(ThesisProject $record): array => self::archiveSummaryLines($record))
                                            ->listWithLineBreaks(),
                                    ])
                                    ->visible(fn(ThesisProject $record): bool => $record->isArchived()),
                            ]),
                        Tab::make('Aktivitas')
                            ->icon('heroicon-m-clock')
                            ->schema([
                                Section::make('Riwayat Aktivitas')
                                    ->schema([
                                        self::timelineEntry(),
                                    ]),
                            ]),
                        Tab::make('Catatan')
                            ->icon('heroicon-m-chat-bubble-left-ellipsis')
                            ->schema([
                                Section::make('Catatan Dosen')
                                    ->description('Catatan pembimbing dan penguji. Hanya dapat dibaca di sini.')
                                    ->schema([
                                        self::projectNotesEntry(),
                                    ]),
                                Section::make('Catatan sesi bimbingan')
                                    ->description('Catatan mahasiswa dan dosen per sesi. Hanya dapat dibaca di sini.')
                                    ->schema([
                                        self::sessionNotesEntry(),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    private static function titleHistoryEntry(): RepeatableEntry
    {
        return RepeatableEntry::make('title_history')
            ->label('Riwayat judul')
            ->hiddenLabel()
            ->placeholder('Belum ada judul yang diajukan.')
            ->extraAttributes(['class' => 'sita-detail-table'])
            ->state(fn(ThesisProject $record): array => $record->titles
                ->sortByDesc('version_no')
                ->map(fn(ThesisProjectTitle $title): array => [
                    'version' => 'V'.$title->version_no,
                    'title_id' => $title->title_id,
                    'title_en' => $title->title_en,
                    'status' => self::titleStatusLabel($title->status),
                    'submitted_at' => WitaDateTime::translated($title->submitted_at, 'd M Y H:i'),
                    'decided_at' => WitaDateTime::translated($title->decided_at, 'd M Y H:i'),
                    'decided_by' => $title->decidedBy?->name ?? '-',
                ])
                ->values()
                ->all())
            ->table([
                TableColumn::make('Versi'),
                TableColumn::make('Judul'),
                TableColumn::make('Judul EN'),
                TableColumn::make('Status'),
                TableColumn::make('Submit'),
                TableColumn::make('Keputusan'),
                TableColumn::make('Diputuskan Oleh'),
            ])
            ->schema([
                TextEntry::make('version'),
                TextEntry::make('title_id')->placeholder('-'),
                TextEntry::make('title_en')->placeholder('-'),
                TextEntry::make('status')
                    ->badge()
                    ->color(fn(string $state): string => self::titleStatusColor($state))
                    ->icon(fn(string $state): string => self::titleStatusIcon($state)),
                TextEntry::make('submitted_at'),
                TextEntry::make('decided_at'),
                TextEntry::make('decided_by'),
            ])
            ->contained(false);
    }

    private static function supervisorHistoryEntry(): RepeatableEntry
    {
        return RepeatableEntry::make('supervisor_history')
            ->label('Riwayat pembimbing')
            ->hiddenLabel()
            ->placeholder('Belum ada pembimbing yang ditetapkan.')
            ->extraAttributes(['class' => 'sita-detail-table'])
            ->state(fn(ThesisProject $record): array => $record->supervisorAssignments
                ->sortByDesc(fn(ThesisSupervisorAssignment $assignment): int => $assignment->started_at?->getTimestamp() ?? 0)
                ->map(fn(ThesisSupervisorAssignment $assignment): array => [
                    'lecturer' => $assignment->lecturer?->name ?? '-',
                    'nik' => $assignment->lecturer?->dosenProfile?->nik ?? '-',
                    'role' => $assignment->role === 'primary' ? 'Pembimbing 1' : 'Pembimbing 2',
                    'status' => $assignment->status,
                    'started_at' => WitaDateTime::translated($assignment->started_at, 'd M Y', withLabel: false),
                    'ended_at' => WitaDateTime::translated($assignment->ended_at, 'd M Y', withLabel: false),
                    'notes' => $assignment->notes ?? '-',
                ])
                ->values()
                ->all())
            ->table([
                TableColumn::make('Dosen'),
                TableColumn::make('NIK'),
                TableColumn::make('Peran'),
                TableColumn::make('Status'),
                TableColumn::make('Mulai'),
                TableColumn::make('Selesai'),
                TableColumn::make('Catatan'),
            ])
            ->schema([
                TextEntry::make('lecturer'),
                TextEntry::make('nik'),
                TextEntry::make('role'),
                TextEntry::make('status')
                    ->badge()
                    ->color(fn(string $state): string => self::assignmentStatusColor($state))
                    ->icon(fn(string $state): string => self::assignmentStatusIcon($state)),
                TextEntry::make('started_at'),
                TextEntry::make('ended_at'),
                ViewEntry::make('notes')->view('filament.infolists.expandable-note'),
            ])
            ->contained(false);
    }

    private static function defenseEntry(string $type): RepeatableEntry
    {
        return RepeatableEntry::make($type.'_attempts')
            ->label('Riwayat ujian')
            ->hiddenLabel()
            ->placeholder(fn(ThesisProject $record): string => 'Belum ada riwayat '.AcademicTerminology::forProject($record)[$type === 'sidang' ? 'finalExam' : 'proposalExamShort'].'.')
            ->extraAttributes(['class' => 'sita-detail-table'])
            ->state(fn(ThesisProject $record): array => self::mapDefenseEntries($record, $type))
            ->table([
                TableColumn::make('Attempt'),
                TableColumn::make('Status'),
                TableColumn::make('Hasil'),
                TableColumn::make('Jadwal'),
                TableColumn::make('Lokasi'),
                TableColumn::make('Mode'),
                TableColumn::make('Penguji'),
            ])
            ->schema([
                TextEntry::make('attempt'),
                TextEntry::make('status')
                    ->badge()
                    ->color(fn(string $state): string => self::defenseStatusColor($state))
                    ->icon(fn(string $state): string => self::defenseStatusIcon($state)),
                TextEntry::make('result')
                    ->badge()
                    ->color(fn(string $state): string => self::defenseResultColor($state))
                    ->icon(fn(string $state): string => self::defenseResultIcon($state)),
                TextEntry::make('scheduled_for'),
                TextEntry::make('location'),
                TextEntry::make('mode'),
                TextEntry::make('examiners')->listWithLineBreaks(),
            ])
            ->contained(false);
    }

    private static function revisionLogEntry(string $type): ViewEntry
    {
        return ViewEntry::make($type.'_revision_log')
            ->label('Log revisi')
            ->hiddenLabel()
            ->default([])
            ->state(fn (ThesisProject $record): array => $record->defenses
                ->where('type', $type)->sortBy('attempt_no')
                ->map(fn (ThesisDefense $defense): array => [
                    'label' => AcademicTerminology::defenseLabel(AcademicTerminology::forProject($record), $type).' #'.$defense->attempt_no,
                    'log' => DefenseRevisionLog::forDefense($defense),
                ])->values()->all())
            ->view('filament.infolists.defense-revision-log');
    }

    private static function thesisDocumentsEntry(): RepeatableEntry
    {
        return RepeatableEntry::make('thesis_documents')
            ->label('Dokumen')
            ->hiddenLabel()
            ->placeholder('Belum ada dokumen.')
            ->extraAttributes(['class' => 'sita-detail-table sita-detail-table-sticky-end'])
            ->state(fn(ThesisProject $record): array => $record->documents
                ->sortByDesc(fn(ThesisDocument $document): int => $document->uploaded_at?->getTimestamp() ?? 0)
                ->map(fn(ThesisDocument $document): array => [
                    'kind' => self::documentKindLabel($document->kind),
                    'title' => $document->title,
                    'version' => 'V'.$document->version_no,
                    'title_version' => $document->titleVersion?->title_id ?? '-',
                    'context' => self::documentContextLabel($document),
                    'uploaded_by' => $document->uploadedBy?->name ?? '-',
                    'uploaded_at' => WitaDateTime::translated($document->uploaded_at, 'd M Y H:i'),
                    'file_name' => $document->file_name,
                    'stored_file_name' => $document->stored_file_name ?? '-',
                    'download_url' => route('files.thesis-documents.download', ['document' => $document]),
                ])
                ->values()
                ->all())
            ->table([
                TableColumn::make('Jenis'),
                TableColumn::make('Judul Dokumen'),
                TableColumn::make('Versi'),
                TableColumn::make('Versi Judul'),
                TableColumn::make('Konteks'),
                TableColumn::make('Diunggah Oleh'),
                TableColumn::make('Waktu Unggah'),
                TableColumn::make('Nama Upload'),
                TableColumn::make('Nama Storage'),
                TableColumn::make('Unduh'),
            ])
            ->schema([
                TextEntry::make('kind'),
                TextEntry::make('title')->placeholder('-'),
                TextEntry::make('version'),
                TextEntry::make('title_version')->placeholder('-'),
                TextEntry::make('context')->placeholder('-'),
                TextEntry::make('uploaded_by'),
                TextEntry::make('uploaded_at'),
                TextEntry::make('file_name')->placeholder('-'),
                TextEntry::make('stored_file_name')->placeholder('-'),
                TextEntry::make('download_url')
                    ->label('Unduh')
                    ->badge()
                    ->color('primary')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->formatStateUsing(fn(): string => 'Unduh')
                    ->url(fn(?string $state): ?string => $state)
                    ->openUrlInNewTab(),
            ])
            ->contained(false);
    }

    /**
     * @return list<string>
     */
    private static function archiveSummaryLines(ThesisProject $record): array
    {
        $summary = $record->archive_summary ?? [];
        $lines = [];

        foreach ($summary['completed_mentorship'] ?? [] as $row) {
            $lines[] = sprintf('%s: %d bimbingan selesai', $row['lecturer_name'] ?? 'Pembimbing', (int) ($row['completed'] ?? 0));
        }

        $lines[] = sprintf('Dokumen diunggah: %d', (int) ($summary['documents_uploaded'] ?? 0));
        $lines[] = sprintf('Pesan: %d', (int) ($summary['messages'] ?? 0));

        $lastActivity = $record->archivedLastActivity();
        $lines[] = 'Aktivitas terakhir: '.($lastActivity === null ? '-' : WitaDateTime::translated($lastActivity, 'd M Y', withLabel: false));

        return $lines;
    }

    private static function mentorshipDocumentsEntry(): RepeatableEntry
    {
        return RepeatableEntry::make('mentorship_documents')
            ->label('Dokumen bimbingan')
            ->hiddenLabel()
            ->placeholder(fn(ThesisProject $record): string => $record->isArchived() ? 'Dokumen bimbingan dihapus saat diarsipkan.' : 'Belum ada dokumen bimbingan.')
            ->extraAttributes(['class' => 'sita-detail-table sita-detail-table-sticky-end'])
            ->state(fn(ThesisProject $record): array => self::projectMentorshipDocuments($record)
                ->sortByDesc(fn(MentorshipDocument $document): int => $document->created_at?->getTimestamp() ?? 0)
                ->map(fn(MentorshipDocument $document): array => [
                    'title' => $document->title,
                    'category' => $document->category ?? '-',
                    'version' => 'V'.$document->version_number,
                    'lecturer' => $document->lecturer?->name ?? '-',
                    'status' => self::mentorshipDocumentStatusLabel($document->status),
                    'review_notes' => $document->revision_notes ?? '-',
                    'uploaded_at' => WitaDateTime::translated($document->created_at, 'd M Y H:i'),
                    'file_name' => $document->file_name,
                    'stored_file_name' => $document->stored_file_name ?? '-',
                    'download_url' => route('files.documents.download', ['document' => $document, 'escalated' => 1]),
                ])
                ->values()
                ->all() ?? [])
            ->table([
                TableColumn::make('Judul'),
                TableColumn::make('Kategori'),
                TableColumn::make('Versi'),
                TableColumn::make('Tujuan'),
                TableColumn::make('Status'),
                TableColumn::make('Catatan Revisi'),
                TableColumn::make('Waktu Unggah'),
                TableColumn::make('Nama Upload'),
                TableColumn::make('Nama Storage'),
                TableColumn::make('Unduh'),
            ])
            ->schema([
                TextEntry::make('title')->placeholder('-'),
                TextEntry::make('category')->placeholder('-'),
                TextEntry::make('version'),
                TextEntry::make('lecturer')->label('Tujuan'),
                TextEntry::make('status')
                    ->badge()
                    ->color(fn(string $state): string => self::mentorshipStatusColor($state))
                    ->icon(fn(string $state): string => self::mentorshipStatusIcon($state)),
                ViewEntry::make('review_notes')->view('filament.infolists.expandable-note'),
                TextEntry::make('uploaded_at'),
                TextEntry::make('file_name')->placeholder('-'),
                TextEntry::make('stored_file_name')->placeholder('-'),
                TextEntry::make('download_url')
                    ->label('Unduh')
                    ->badge()
                    ->color('primary')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->formatStateUsing(fn(): string => 'Unduh')
                    ->url(fn(?string $state): ?string => $state)
                    ->openUrlInNewTab(),
            ])
            ->contained(false);
    }

    private static function timelineEntry(): RepeatableEntry
    {
        return RepeatableEntry::make('timeline')
            ->label('Riwayat aktivitas')
            ->hiddenLabel()
            ->placeholder('Belum ada aktivitas.')
            ->extraAttributes(['class' => 'sita-detail-table'])
            ->state(fn(ThesisProject $record): array => $record->events
                ->sortByDesc('occurred_at')
                ->map(fn(ThesisProjectEvent $event): array => [
                    'label' => $event->label,
                    'description' => $event->description ?? '-',
                    'actor' => $event->actor?->name ?? 'Sistem',
                    'occurred_at' => WitaDateTime::translated($event->occurred_at, 'd M Y H:i'),
                ])
                ->values()
                ->all())
            ->table([
                TableColumn::make('Event'),
                TableColumn::make('Deskripsi'),
                TableColumn::make('Aktor'),
                TableColumn::make('Waktu'),
            ])
            ->schema([
                TextEntry::make('label'),
                ViewEntry::make('description')->view('filament.infolists.expandable-note'),
                TextEntry::make('actor'),
                TextEntry::make('occurred_at'),
            ])
            ->contained(false);
    }

    private static function projectNotesEntry(): RepeatableEntry
    {
        return RepeatableEntry::make('project_notes')
            ->label('Catatan dosen')
            ->hiddenLabel()
            ->placeholder('Belum ada catatan dosen.')
            ->extraAttributes(['class' => 'sita-detail-table'])
            ->state(fn(ThesisProject $record): array => $record->lecturerNotes
                ->sortByDesc('created_at')
                ->map(fn(ThesisProjectNote $note): array => [
                    'author' => $note->author?->name ?? '-',
                    'visibility' => $note->visibility->label(),
                    'body' => $note->body,
                    'created_at' => WitaDateTime::translated($note->created_at, 'd M Y H:i'),
                ])
                ->values()
                ->all())
            ->table([
                TableColumn::make('Penulis'),
                TableColumn::make('Visibilitas'),
                TableColumn::make('Catatan'),
                TableColumn::make('Waktu'),
            ])
            ->schema([
                TextEntry::make('author'),
                TextEntry::make('visibility')
                    ->badge()
                    ->color(fn(string $state): string => $state === 'Internal dosen' ? 'warning' : 'success'),
                ViewEntry::make('body')->view('filament.infolists.expandable-note'),
                TextEntry::make('created_at'),
            ])
            ->contained(false);
    }

    private static function sessionNotesEntry(): ViewEntry
    {
        return ViewEntry::make('mentorship_session_notes')
            ->label('Catatan sesi bimbingan')
            ->hiddenLabel()
            ->default([])
            ->state(function (ThesisProject $record): array {
                $viewer = auth()->user();

                if (! $viewer instanceof User) {
                    return [];
                }

                return app(MentorshipSessionNoteService::class)->forProject($record, $viewer);
            })
            ->view('filament.infolists.mentorship-session-notes');
    }

    private static function nextAdminStepLabel(ThesisProject $record): string
    {
        if (in_array($record->state, ['completed', 'cancelled'], true)) {
            return 'Monitoring Saja';
        }

        $latestSempro = self::latestDefense($record, 'sempro');
        $latestSidang = self::latestDefense($record, 'sidang');
        $terms = AcademicTerminology::forProject($record);

        return match ($record->phase) {
            'title_review', 'sempro' => $latestSempro instanceof ThesisDefense
                ? self::nextSemproStepLabel($record, $latestSempro)
            : 'Jadwalkan '.$terms['proposalExamShort'],
            'research' => $record->activeSupervisorAssignments->count() < 2
                ? 'Lengkapi Pembimbing Aktif'
                : 'Pantau Bimbingan Penelitian',
            'sidang' => $latestSidang instanceof ThesisDefense
                ? self::nextSidangStepLabel($record, $latestSidang)
            : 'Jadwalkan '.$terms['finalExam'],
            default => 'Monitoring '.$terms['finalWork'],
        };
    }

    private static function nextAdminStepColor(ThesisProject $record): string
    {
        if (in_array($record->state, ['completed', 'cancelled'], true)) {
            return 'gray';
        }

        if ($record->open_revisions_count > 0) {
            return 'warning';
        }

        return match ($record->phase) {
            'title_review', 'sempro' => 'info',
            'research' => 'primary',
            'sidang' => 'success',
            default => 'gray',
        };
    }

    private static function workflowAttention(ThesisProject $record): string
    {
        $terms = AcademicTerminology::forProject($record);
        if ($record->open_revisions_count > 0) {
            return sprintf('%d revisi masih terbuka dan perlu ditindaklanjuti.', $record->open_revisions_count);
        }

        $awaitingDefense = collect([$record->defenses->where('type', 'sempro')->sortByDesc('attempt_no')->first(), $record->defenses->where('type', 'sidang')->sortByDesc('attempt_no')->first()])
            ->first(fn(?ThesisDefense $defense): bool => $defense instanceof ThesisDefense && $defense->status === 'awaiting_finalization');

        if ($awaitingDefense instanceof ThesisDefense) {
            return sprintf('Keputusan dosen untuk %s #%d sudah lengkap dan menunggu finalisasi admin.', $awaitingDefense->type, $awaitingDefense->attempt_no);
        }

        if ($record->activeSupervisorAssignments->isEmpty()) {
            return 'Belum ada pembimbing aktif yang tercatat.';
        }

        if ($record->state === 'on_hold') {
            return $terms['finalWork'].' sedang ditunda, cek catatan dan timeline terakhir.';
        }

        return $terms['finalWork'].' aktif tanpa revisi terbuka. Fokus pada agenda dan progres fase saat ini.';
    }

    private static function latestDefenseSummary(ThesisProject $record, string $type): string
    {
        $defense = self::latestDefense($record, $type);

        if (! $defense instanceof ThesisDefense) {
            return '-';
        }

        return sprintf(
            '#%d - %s - %s - %s',
            $defense->attempt_no,
            self::defenseStatusLabel($defense->status),
            self::defenseResultLabel($defense->result, $type),
            self::formatDefenseSchedule($defense),
        );
    }

    private static function nextSemproStepLabel(ThesisProject $record, ThesisDefense $defense): string
    {
        $label = AcademicTerminology::forProject($record)['proposalExamShort'];

        if ($defense->status === 'awaiting_finalization') {
            return 'Tetapkan Hasil '.$label;
        }

        if ($defense->status === 'scheduled') {
            $decidedCount = $defense->examiners->where('decision', '!=', 'pending')->count();

            return $decidedCount === 0
                ? 'Tunggu Nilai Penguji '.$label
                : 'Tunggu Nilai Penguji Lain';
        }

        if ($defense->status === 'completed') {
            return match ($defense->result) {
                'fail' => 'Jadwalkan '.$label.' Ulang',
                'pass_with_revision' => $record->open_revisions_count > 0 ? 'Pantau Revisi '.$label : 'Tetapkan Pembimbing Aktif',
                default => 'Monitoring '.$label,
            };
        }

        return 'Jadwalkan '.$label;
    }

    private static function nextSidangStepLabel(ThesisProject $record, ThesisDefense $defense): string
    {
        $label = AcademicTerminology::forProject($record)['finalExam'];

        if ($defense->status === 'awaiting_finalization') {
            return 'Tetapkan Hasil '.$label;
        }

        if ($defense->status === 'scheduled') {
            $decidedCount = $defense->examiners->where('decision', '!=', 'pending')->count();

            return $decidedCount === 0
                ? 'Tunggu Nilai Panel '.$label
                : 'Tunggu Nilai Panel Lain';
        }

        if ($defense->status === 'completed') {
            return match ($defense->result) {
                'fail' => 'Jadwalkan '.$label.' Ulang',
                'pass_with_revision' => $record->open_revisions_count > 0 ? 'Pantau Revisi '.$label : 'Monitoring '.$label,
                'pass' => 'Monitoring Saja',
                default => 'Monitoring '.$label,
            };
        }

        return 'Jadwalkan '.$label;
    }

    private static function activeSupervisorsSummary(ThesisProject $record): string
    {
        if ($record->activeSupervisorAssignments->isEmpty()) {
            return '-';
        }

        return $record->activeSupervisorAssignments
            ->sortBy('role')
            ->map(fn(ThesisSupervisorAssignment $assignment): string => sprintf(
                '%s: %s',
                $assignment->role === 'primary' ? 'Pembimbing 1' : 'Pembimbing 2',
                $assignment->lecturer?->name ?? '-',
            ))
            ->implode(' | ');
    }

    private static function latestDefense(ThesisProject $record, string $type): ?ThesisDefense
    {
        return $record->defenses
            ->where('type', $type)
            ->sortByDesc('attempt_no')
            ->first();
    }

    private static function resolveCurrentTitle(ThesisProject $record): ?ThesisProjectTitle
    {
        $approved = $record->titles
            ->where('status', 'approved')
            ->sortByDesc('version_no')
            ->first();

        if ($approved instanceof ThesisProjectTitle) {
            return $approved;
        }

        return $record->latestTitle;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function mapDefenseEntries(ThesisProject $record, string $type): array
    {
        return $record->defenses
            ->where('type', $type)
            ->sortBy('attempt_no')
            ->map(fn(ThesisDefense $defense): array => [
                'attempt' => '#'.$defense->attempt_no,
                'status' => self::defenseStatusLabel($defense->status),
                'result' => self::defenseResultLabel($defense->result, $type),
                'scheduled_for' => self::formatDefenseSchedule($defense),
                'location' => $defense->location ?? '-',
                'mode' => ucfirst($defense->mode),
                'examiners' => $defense->examiners
                    ->sortBy('order_no')
                    ->map(fn($examiner): string => sprintf(
                        '%s - %s - %s%s',
                        $examiner->lecturer?->name ?? '-',
                        self::defenseRoleLabel($examiner->role, $examiner->order_no),
                        self::examinerDecisionLabel($examiner->decision),
                        $examiner->score !== null ? sprintf(' - Nilai %s', $examiner->score) : '',
                    ))
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    private static function titleStatusLabel(string $status): string
    {
        return match ($status) {
            'draft' => 'Draft',
            'submitted' => 'Diajukan',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            'superseded' => 'Diganti',
            'withdrawn' => 'Ditarik',
            default => ucwords(str_replace('_', ' ', $status)),
        };
    }

    private static function titleStatusColor(string $status): string
    {
        return match ($status) {
            'draft' => 'gray',
            'submitted' => 'info',
            'approved' => 'success',
            'rejected' => 'danger',
            'superseded' => 'warning',
            'withdrawn' => 'gray',
            default => 'gray',
        };
    }

    private static function titleStatusIcon(string $status): string
    {
        return match ($status) {
            'draft' => 'heroicon-m-pencil-square',
            'submitted' => 'heroicon-m-paper-airplane',
            'approved' => 'heroicon-m-check-circle',
            'rejected' => 'heroicon-m-x-circle',
            'superseded' => 'heroicon-m-arrow-path',
            'withdrawn' => 'heroicon-m-arrow-uturn-left',
            default => 'heroicon-m-tag',
        };
    }

    private static function defenseStatusLabel(string $status): string
    {
        return match ($status) {
            'draft' => 'Draft',
            'scheduled' => 'Dijadwalkan',
            'awaiting_finalization' => 'Menunggu Finalisasi',
            'completed' => 'Selesai',
            'cancelled' => 'Dibatalkan',
            default => ucwords(str_replace('_', ' ', $status)),
        };
    }

    private static function defenseStatusColor(string $status): string
    {
        return match ($status) {
            'draft' => 'gray',
            'scheduled' => 'info',
            'awaiting_finalization' => 'warning',
            'completed' => 'success',
            'cancelled' => 'danger',
            default => 'gray',
        };
    }

    private static function defenseStatusIcon(string $status): string
    {
        return match ($status) {
            'draft' => 'heroicon-m-pencil-square',
            'scheduled' => 'heroicon-m-calendar-days',
            'awaiting_finalization' => 'heroicon-m-exclamation-circle',
            'completed' => 'heroicon-m-check-badge',
            'cancelled' => 'heroicon-m-no-symbol',
            default => 'heroicon-m-tag',
        };
    }

    private static function defenseResultLabel(string $result, string $defenseType = 'sempro'): string
    {
        return match ($result) {
            'pending' => 'Pending',
            'pass' => 'Lulus',
            'pass_with_revision' => $defenseType === 'sidang' ? 'Lulus dengan Syarat' : 'Lulus',
            'fail' => 'Tidak Lulus',
            default => ucwords(str_replace('_', ' ', $result)),
        };
    }

    private static function formatDefenseSchedule(ThesisDefense $defense): string
    {
        return WitaDateTime::translatedDateRange($defense->scheduled_for, $defense->scheduled_until);
    }

    private static function defenseResultColor(string $result): string
    {
        return match ($result) {
            'pending' => 'gray',
            'pass' => 'success',
            'pass_with_revision' => 'warning',
            'fail' => 'danger',
            default => 'gray',
        };
    }

    private static function defenseResultIcon(string $result): string
    {
        return match ($result) {
            'pending' => 'heroicon-m-clock',
            'pass' => 'heroicon-m-check-circle',
            'pass_with_revision' => 'heroicon-m-exclamation-triangle',
            'fail' => 'heroicon-m-x-circle',
            default => 'heroicon-m-tag',
        };
    }

    private static function examinerDecisionLabel(string $decision): string
    {
        return match ($decision) {
            'pending' => 'Pending',
            'pass' => 'Lulus',
            'pass_with_revision' => 'Revisi',
            'fail' => 'Gagal',
            default => ucwords(str_replace('_', ' ', $decision)),
        };
    }

    private static function defenseRoleLabel(string $role, int $orderNo): string
    {
        return match ($role) {
            'primary_supervisor' => 'Pembimbing 1',
            'secondary_supervisor' => 'Pembimbing 2',
            default => 'Penguji',
        };
    }

    private static function revisionStatusLabel(string $status): string
    {
        return match ($status) {
            'open' => 'Terbuka',
            'submitted' => 'Dikirim',
            'resolved' => 'Selesai',
            'cancelled' => 'Dibatalkan',
            default => ucwords(str_replace('_', ' ', $status)),
        };
    }

    private static function assignmentStatusColor(string $status): string
    {
        return match ($status) {
            'active' => 'success',
            'inactive', 'ended' => 'gray',
            'cancelled' => 'danger',
            default => 'gray',
        };
    }

    private static function assignmentStatusIcon(string $status): string
    {
        return match ($status) {
            'active' => 'heroicon-m-check-circle',
            'inactive', 'ended' => 'heroicon-m-pause-circle',
            'cancelled' => 'heroicon-m-x-circle',
            default => 'heroicon-m-tag',
        };
    }

    private static function documentKindLabel(string $kind): string
    {
        return match ($kind) {
            'proposal' => AcademicTerminology::neutral()['proposalDocument'],
            'revision_submission' => 'Dokumen Revisi',
            'final_manuscript' => 'Naskah Akhir',
            'supporting_document' => 'Lampiran',
            default => ucwords(str_replace('_', ' ', $kind)),
        };
    }

    private static function documentContextLabel(ThesisDocument $document): string
    {
        if ($document->defense instanceof ThesisDefense) {
            return sprintf(
                '%s #%d',
                AcademicTerminology::defenseLabel(AcademicTerminology::forProject($document->project), $document->defense->type, true),
                $document->defense->attempt_no,
            );
        }

        if ($document->revision instanceof ThesisRevision) {
            return 'Revisi';
        }

        return AcademicTerminology::forProject($document->project)['finalWork'];
    }

    private static function mentorshipDocumentStatusLabel(string $status): string
    {
        return match ($status) {
            'approved' => 'Disetujui',
            'needs_revision' => 'Perlu Revisi',
            'submitted' => 'Dikirim',
            default => ucwords(str_replace('_', ' ', $status)),
        };
    }

    private static function mentorshipStatusColor(string $status): string
    {
        return match ($status) {
            'Disetujui' => 'success',
            'Perlu Revisi' => 'warning',
            'Dikirim' => 'info',
            default => 'gray',
        };
    }

    private static function mentorshipStatusIcon(string $status): string
    {
        return match ($status) {
            'Disetujui' => 'heroicon-m-check-circle',
            'Perlu Revisi' => 'heroicon-m-exclamation-triangle',
            'Dikirim' => 'heroicon-m-paper-airplane',
            default => 'heroicon-m-tag',
        };
    }

    /**
     * @return \Illuminate\Support\Collection<int, MentorshipDocument>
     */
    private static function projectMentorshipDocuments(ThesisProject $record)
    {
        $documents = $record->student?->mentorshipDocumentsAsStudent;

        if ($documents === null) {
            return collect();
        }

        $endedAt = $record->completed_at ?? $record->cancelled_at;

        return $documents->filter(function (MentorshipDocument $document) use ($record, $endedAt): bool {
            $createdAt = $document->created_at;

            if ($createdAt === null) {
                return false;
            }

            if ($record->started_at !== null && $createdAt->lt($record->started_at)) {
                return false;
            }

            if ($endedAt !== null && $createdAt->gt($endedAt)) {
                return false;
            }

            return true;
        });
    }
}
