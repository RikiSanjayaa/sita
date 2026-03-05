<?php

namespace App\Filament\Resources\MentorshipAssignments\Schemas;

use App\Enums\AdvisorType;
use App\Models\MentorshipAssignment;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MentorshipAssignmentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Mahasiswa')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('student.name')
                            ->label('Nama Mahasiswa'),
                        TextEntry::make('student.mahasiswaProfile.nim')
                            ->label('NIM')
                            ->placeholder('-'),
                        TextEntry::make('student.mahasiswaProfile.programStudi.name')
                            ->label('Program Studi')
                            ->placeholder('-'),
                    ]),
                Section::make('Dosen Pembimbing')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('lecturer.name')
                            ->label('Pembimbing 1'),
                        TextEntry::make('secondary_advisor_name')
                            ->label('Pembimbing 2')
                            ->state(function (?MentorshipAssignment $record): string {
                                if ($record === null) {
                                    return '-';
                                }

                                $secondary = MentorshipAssignment::query()
                                    ->where('student_user_id', $record->student_user_id)
                                    ->where('advisor_type', AdvisorType::Secondary->value)
                                    ->where('status', $record->status)
                                    ->with('lecturer')
                                    ->first();

                                return $secondary?->lecturer?->name ?? '-';
                            }),
                        TextEntry::make('lecturer.dosenProfile.nik')
                            ->label('NIK Pembimbing 1')
                            ->placeholder('-'),
                        TextEntry::make('secondary_advisor_nik')
                            ->label('NIK Pembimbing 2')
                            ->state(function (?MentorshipAssignment $record): string {
                                if ($record === null) {
                                    return '-';
                                }

                                $secondary = MentorshipAssignment::query()
                                    ->where('student_user_id', $record->student_user_id)
                                    ->where('advisor_type', AdvisorType::Secondary->value)
                                    ->where('status', $record->status)
                                    ->with('lecturer.dosenProfile')
                                    ->first();

                                return $secondary?->lecturer?->dosenProfile?->nik ?? '-';
                            }),
                    ]),
                Section::make('Status & Detail')
                    ->columns(2)
                    ->schema([
                    TextEntry::make('status')
                            ->badge()
                            ->formatStateUsing(fn(string $state): string => match ($state) {
                                'active' => 'Aktif',
                                'ended' => 'Selesai',
                                default => ucfirst($state),
                            }),
                    TextEntry::make('assignedBy.name')
                            ->label('Ditetapkan Oleh')
                            ->placeholder('-'),
                    TextEntry::make('started_at')
                            ->label('Tanggal Mulai')
                            ->dateTime('d M Y H:i')
                            ->placeholder('-'),
                    TextEntry::make('ended_at')
                            ->label('Tanggal Selesai')
                            ->dateTime('d M Y H:i')
                            ->placeholder('-'),
                    TextEntry::make('notes')
                            ->label('Catatan')
                            ->placeholder('-')
                            ->columnSpanFull(),
                ]),
                Section::make('Progress Bimbingan')
                    ->columns(3)
                    ->schema([
                    TextEntry::make('schedules_count')
                            ->label('Jadwal Bimbingan')
                            ->state(fn(?MentorshipAssignment $record): int => $record?->schedules?->count() ?? 0),
                    TextEntry::make('documents_count')
                            ->label('Dokumen')
                            ->state(fn(?MentorshipAssignment $record): int => $record?->documents?->count() ?? 0),
                ]),
            ]);
    }
}
