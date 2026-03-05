<?php

namespace App\Filament\Resources\Sempros\Schemas;

use App\Enums\AppRole;
use App\Enums\SemproExaminerDecision;
use App\Enums\SemproStatus;
use App\Models\ThesisSubmission;
use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SemproForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Thesis Submission')
                    ->schema([
                        Select::make('thesis_submission_id')
                            ->label('Judul & Proposal')
                            ->options(fn(): array => ThesisSubmission::query()
                                ->with('student.mahasiswaProfile')
                                ->orderByDesc('id')
                                ->get()
                                ->mapWithKeys(static fn(ThesisSubmission $submission): array => [
                                    $submission->id => sprintf(
                                        '%s (%s) — %s',
                                        $submission->student?->name ?? '-',
                                        $submission->student?->mahasiswaProfile?->nim ?? '-',
                                        mb_substr($submission->title_id ?? '-', 0, 60),
                                    ),
                                ])
                                ->all())
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false)
                            ->disabled(fn(string $operation): bool => $operation === 'edit'),
                    ]),
                Section::make('Jadwal Sempro')
                    ->columns(2)
                    ->schema([
                        Select::make('status')
                            ->options(collect(SemproStatus::cases())
                                ->mapWithKeys(fn(SemproStatus $s) => [
                                    $s->value => match ($s) {
                                        SemproStatus::Draft => 'Draft',
                                        SemproStatus::Scheduled => 'Dijadwalkan',
                                        SemproStatus::RevisionOpen => 'Revisi',
                                        SemproStatus::Approved => 'Selesai',
                                    },
                                ])->all())
                            ->required()
                            ->default(SemproStatus::Draft->value)
                            ->native(false),
                        Select::make('mode')
                            ->options([
                                'offline' => 'Offline',
                                'online' => 'Online',
                            ])
                            ->required()
                            ->default('offline')
                            ->native(false),
                        DateTimePicker::make('scheduled_for')
                            ->label('Tanggal & Waktu'),
                        TextInput::make('location')
                            ->label('Lokasi')
                            ->maxLength(255),
                        DateTimePicker::make('revision_due_at')
                            ->label('Batas Revisi'),
                    ]),
                Section::make('Dosen Penguji')
                    ->description('Tambahkan dosen penguji untuk sempro ini. Minimal 2 penguji.')
                    ->schema([
                        Repeater::make('examiner_user_ids')
                            ->label('Penguji')
                            ->defaultItems(2)
                            ->minItems(2)
                            ->schema([
                                Select::make('user_id')
                                    ->label('Dosen Penguji')
                                    ->options(fn(): array => User::query()
                                        ->whereHas('roles', static fn($query) => $query->where('name', AppRole::Dosen->value))
                                        ->orderBy('name')
                                        ->get()
                                        ->mapWithKeys(fn(User $u) => [
                                            $u->id => $u->name.' ('.($u->dosenProfile?->nik ?? '-').')',
                                        ])
                                        ->all())
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->native(false),
                                Select::make('decision')
                                    ->label('Keputusan')
                                    ->options(collect(SemproExaminerDecision::cases())
                                        ->mapWithKeys(fn(SemproExaminerDecision $d) => [
                                            $d->value => match ($d) {
                                                SemproExaminerDecision::Pending => 'Pending',
                                                SemproExaminerDecision::NeedsRevision => 'Perlu Revisi',
                                                SemproExaminerDecision::Approved => 'Disetujui',
                                            },
                                        ])->all())
                                    ->default(SemproExaminerDecision::Pending->value)
                                    ->native(false),
                                TextInput::make('score')
                                    ->label('Nilai')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->step(0.01)
                                    ->placeholder('0-100'),
                            ])
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
