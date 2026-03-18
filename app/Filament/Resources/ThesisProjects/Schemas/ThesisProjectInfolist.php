<?php

namespace App\Filament\Resources\ThesisProjects\Schemas;

use App\Filament\Resources\ThesisProjects\Tables\ThesisProjectsTable;
use App\Models\MentorshipDocument;
use App\Models\ThesisDefense;
use App\Models\ThesisDocument;
use App\Models\ThesisProject;
use App\Models\ThesisProjectEvent;
use App\Models\ThesisProjectTitle;
use App\Models\ThesisRevision;
use App\Models\ThesisSupervisorAssignment;
use App\Support\Filament\BadgeStyles;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
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
                Section::make('Ringkasan Proyek')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('student.name')
                            ->label('Mahasiswa'),
                        TextEntry::make('student.mahasiswaProfile.nim')
                            ->label('NIM')
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
                            ->dateTime('d M Y H:i')
                            ->placeholder('-'),
                        TextEntry::make('completed_at')
                            ->label('Selesai')
                            ->dateTime('d M Y H:i')
                            ->placeholder('-'),
                        TextEntry::make('notes')
                            ->label('Catatan Admin')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),
                Section::make('Status Workflow')
                    ->description('Ringkasan singkat untuk membantu admin memahami kondisi proyek dan tindakan berikutnya.')
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
                            ->label('Sempro Terakhir')
                            ->state(fn(ThesisProject $record): string => self::latestDefenseSummary($record, 'sempro')),
                        TextEntry::make('latest_sidang_summary')
                            ->label('Sidang Terakhir')
                            ->state(fn(ThesisProject $record): string => self::latestDefenseSummary($record, 'sidang')),
                        TextEntry::make('active_supervisors_summary')
                            ->label('Pembimbing Aktif')
                            ->state(fn(ThesisProject $record): string => self::activeSupervisorsSummary($record))
                            ->columnSpanFull(),
                    ]),
                Tabs::make('Detail proyek')
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
                                    ])
                                    ->visible(fn(ThesisProject $record): bool => $record->supervisorAssignments->isNotEmpty()),
                            ]),
                        Tab::make('Workflow')
                            ->icon('heroicon-m-arrows-right-left')
                            ->schema([
                                Section::make('Sempro')
                                    ->schema([
                                        self::defenseEntry('sempro'),
                                    ])
                                    ->visible(fn(ThesisProject $record): bool => $record->defenses->contains(fn(ThesisDefense $defense): bool => $defense->type === 'sempro')),
                                Section::make('Sidang')
                                    ->schema([
                                        self::defenseEntry('sidang'),
                                    ])
                                    ->visible(fn(ThesisProject $record): bool => $record->defenses->contains(fn(ThesisDefense $defense): bool => $defense->type === 'sidang')),
                            ]),
                        Tab::make('Dokumen')
                            ->icon('heroicon-m-folder-open')
                            ->schema([
                                Section::make('Dokumen Tugas Akhir')
                                    ->schema([
                                        self::thesisDocumentsEntry(),
                                    ])
                                    ->visible(fn(ThesisProject $record): bool => $record->documents->isNotEmpty()),
                                Section::make('Dokumen Bimbingan Proyek')
                                    ->schema([
                                        self::mentorshipDocumentsEntry(),
                                    ])
                                    ->visible(fn(ThesisProject $record): bool => self::projectMentorshipDocuments($record)->isNotEmpty()),
                            ]),
                        Tab::make('Timeline')
                            ->icon('heroicon-m-clock')
                            ->schema([
                                Section::make('Timeline')
                                    ->schema([
                                        self::timelineEntry(),
                                    ])
                                    ->visible(fn(ThesisProject $record): bool => $record->events->isNotEmpty()),
                            ]),
                    ]),
            ]);
    }

    private static function titleHistoryEntry(): RepeatableEntry
    {
        return RepeatableEntry::make('title_history')
            ->label('')
            ->state(fn(ThesisProject $record): array => $record->titles
                ->sortByDesc('version_no')
                ->map(fn(ThesisProjectTitle $title): array => [
                    'version' => 'V'.$title->version_no,
                    'title_id' => $title->title_id,
                    'title_en' => $title->title_en,
                    'status' => self::titleStatusLabel($title->status),
                    'submitted_at' => $title->submitted_at?->format('d M Y H:i') ?? '-',
                    'decided_at' => $title->decided_at?->format('d M Y H:i') ?? '-',
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
            ->label('')
            ->state(fn(ThesisProject $record): array => $record->supervisorAssignments
                ->sortByDesc(fn(ThesisSupervisorAssignment $assignment): int => $assignment->started_at?->getTimestamp() ?? 0)
                ->map(fn(ThesisSupervisorAssignment $assignment): array => [
                    'lecturer' => $assignment->lecturer?->name ?? '-',
                    'nik' => $assignment->lecturer?->dosenProfile?->nik ?? '-',
                    'role' => $assignment->role === 'primary' ? 'Pembimbing 1' : 'Pembimbing 2',
                    'status' => $assignment->status,
                    'started_at' => $assignment->started_at?->format('d M Y') ?? '-',
                    'ended_at' => $assignment->ended_at?->format('d M Y') ?? '-',
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
                TextEntry::make('notes'),
            ])
            ->contained(false);
    }

    private static function defenseEntry(string $type): RepeatableEntry
    {
        return RepeatableEntry::make($type.'_attempts')
            ->label('')
            ->state(fn(ThesisProject $record): array => self::mapDefenseEntries($record, $type))
            ->table([
                TableColumn::make('Attempt'),
                TableColumn::make('Status'),
                TableColumn::make('Hasil'),
                TableColumn::make('Jadwal'),
                TableColumn::make('Lokasi'),
                TableColumn::make('Mode'),
                TableColumn::make('Penguji'),
                TableColumn::make('Revisi'),
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
                TextEntry::make('revisions')->listWithLineBreaks(),
            ])
            ->contained(false);
    }

    private static function thesisDocumentsEntry(): RepeatableEntry
    {
        return RepeatableEntry::make('thesis_documents')
            ->label('')
            ->state(fn(ThesisProject $record): array => $record->documents
                ->sortByDesc(fn(ThesisDocument $document): int => $document->uploaded_at?->getTimestamp() ?? 0)
                ->map(fn(ThesisDocument $document): array => [
                    'kind' => self::documentKindLabel($document->kind),
                    'title' => $document->title,
                    'version' => 'V'.$document->version_no,
                    'title_version' => $document->titleVersion?->title_id ?? '-',
                    'context' => self::documentContextLabel($document),
                    'uploaded_by' => $document->uploadedBy?->name ?? '-',
                    'uploaded_at' => $document->uploaded_at?->format('d M Y H:i') ?? '-',
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
                    ->formatStateUsing(fn(): string => 'Unduh')
                    ->url(fn(?string $state): ?string => $state)
                    ->openUrlInNewTab(),
            ])
            ->contained(false);
    }

    private static function mentorshipDocumentsEntry(): RepeatableEntry
    {
        return RepeatableEntry::make('mentorship_documents')
            ->label('')
            ->state(fn(ThesisProject $record): array => self::projectMentorshipDocuments($record)
                ->sortByDesc(fn(MentorshipDocument $document): int => $document->created_at?->getTimestamp() ?? 0)
                ->map(fn(MentorshipDocument $document): array => [
                    'title' => $document->title,
                    'category' => $document->category ?? '-',
                    'version' => 'V'.$document->version_number,
                    'lecturer' => $document->lecturer?->name ?? '-',
                    'status' => self::mentorshipDocumentStatusLabel($document->status),
                    'review_notes' => $document->revision_notes ?? '-',
                    'uploaded_at' => $document->created_at?->format('d M Y H:i') ?? '-',
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
                TextEntry::make('review_notes')->placeholder('-'),
                TextEntry::make('uploaded_at'),
                TextEntry::make('file_name')->placeholder('-'),
                TextEntry::make('stored_file_name')->placeholder('-'),
                TextEntry::make('download_url')
                    ->label('Unduh')
                    ->formatStateUsing(fn(): string => 'Unduh')
                    ->url(fn(?string $state): ?string => $state)
                    ->openUrlInNewTab(),
            ])
            ->contained(false);
    }

    private static function timelineEntry(): RepeatableEntry
    {
        return RepeatableEntry::make('timeline')
            ->label('')
            ->state(fn(ThesisProject $record): array => $record->events
                ->sortByDesc('occurred_at')
                ->map(fn(ThesisProjectEvent $event): array => [
                    'label' => $event->label,
                    'description' => $event->description ?? '-',
                    'actor' => $event->actor?->name ?? 'Sistem',
                    'occurred_at' => $event->occurred_at?->format('d M Y H:i') ?? '-',
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
                TextEntry::make('description'),
                TextEntry::make('actor'),
                TextEntry::make('occurred_at'),
            ])
            ->contained(false);
    }

    private static function nextAdminStepLabel(ThesisProject $record): string
    {
        if (in_array($record->state, ['completed', 'cancelled'], true)) {
            return 'Monitoring Saja';
        }

        $latestSempro = self::latestDefense($record, 'sempro');
        $latestSidang = self::latestDefense($record, 'sidang');

        return match ($record->phase) {
            'title_review', 'sempro' => $latestSempro instanceof ThesisDefense
                ? ($latestSempro->status === 'scheduled' ? 'Pantau Pelaksanaan Sempro' : 'Catat Hasil Sempro')
                : 'Jadwalkan Sempro',
            'research' => $record->activeSupervisorAssignments->count() < 2
                ? 'Lengkapi Pembimbing Aktif'
                : 'Pantau Bimbingan Penelitian',
            'sidang' => $latestSidang instanceof ThesisDefense
                ? ($latestSidang->status === 'scheduled' ? 'Pantau Pelaksanaan Sidang' : 'Selesaikan Hasil Sidang')
                : 'Jadwalkan Sidang',
            default => 'Monitoring Proyek',
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
        if ($record->open_revisions_count > 0) {
            return sprintf('%d revisi masih terbuka dan perlu ditindaklanjuti.', $record->open_revisions_count);
        }

        if ($record->activeSupervisorAssignments->isEmpty()) {
            return 'Belum ada pembimbing aktif yang tercatat pada proyek ini.';
        }

        if ($record->state === 'on_hold') {
            return 'Proyek sedang ditunda, cek catatan dan timeline terakhir.';
        }

        return 'Proyek aktif tanpa revisi terbuka. Fokus pada agenda dan progres fase saat ini.';
    }

    private static function latestDefenseSummary(ThesisProject $record, string $type): string
    {
        $defense = self::latestDefense($record, $type);

        if (! $defense instanceof ThesisDefense) {
            return '-';
        }

        return sprintf(
            '#%d - %s - %s',
            $defense->attempt_no,
            self::defenseStatusLabel($defense->status),
            $defense->scheduled_for?->format('d M Y H:i') ?? '-',
        );
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
                'result' => self::defenseResultLabel($defense->result),
                'scheduled_for' => $defense->scheduled_for?->format('d M Y H:i') ?? '-',
                'location' => $defense->location ?? '-',
                'mode' => ucfirst($defense->mode),
                'examiners' => $defense->examiners
                    ->sortBy('order_no')
                    ->map(fn($examiner): string => sprintf(
                        '%s (%s)',
                        $examiner->lecturer?->name ?? '-',
                        self::examinerDecisionLabel($examiner->decision),
                    ))
                    ->values()
                    ->all(),
                'revisions' => $defense->revisions
                    ->sortByDesc(fn(ThesisRevision $revision): int => $revision->created_at?->getTimestamp() ?? 0)
                    ->map(fn(ThesisRevision $revision): string => sprintf(
                        '%s - %s',
                        self::revisionStatusLabel($revision->status),
                        $revision->notes,
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
            'completed' => 'heroicon-m-check-badge',
            'cancelled' => 'heroicon-m-no-symbol',
            default => 'heroicon-m-tag',
        };
    }

    private static function defenseResultLabel(string $result): string
    {
        return match ($result) {
            'pending' => 'Pending',
            'pass' => 'Lulus',
            'pass_with_revision' => 'Lulus Revisi',
            'fail' => 'Tidak Lulus',
            default => ucwords(str_replace('_', ' ', $result)),
        };
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
            'proposal' => 'Proposal',
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
                $document->defense->type === 'sidang' ? 'Sidang' : 'Sempro',
                $document->defense->attempt_no,
            );
        }

        if ($document->revision instanceof ThesisRevision) {
            return 'Revisi';
        }

        return 'Proyek';
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
