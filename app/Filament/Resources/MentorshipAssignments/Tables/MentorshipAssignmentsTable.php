<?php

namespace App\Filament\Resources\MentorshipAssignments\Tables;

use App\Enums\AdvisorType;
use App\Enums\AssignmentStatus;
use App\Models\MentorshipAssignment;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MentorshipAssignmentsTable
{
  public static function configure(Table $table): Table
  {
    return $table
      ->modifyQueryUsing(fn($query) => $query->where('advisor_type', AdvisorType::Primary->value))
      ->columns([
        TextColumn::make('student.name')
          ->label('Mahasiswa')
          ->searchable()
          ->sortable()
          ->description(fn(?MentorshipAssignment $record): string => $record?->student?->mahasiswaProfile?->nim ?? '-'),
        TextColumn::make('student.mahasiswaProfile.programStudi.name')
          ->label('Program Studi')
          ->searchable()
          ->sortable()
          ->toggleable(isToggledHiddenByDefault: true),
        TextColumn::make('lecturer.name')
          ->label('Pembimbing 1')
          ->searchable()
          ->sortable()
          ->description(fn(?MentorshipAssignment $record): string => $record?->lecturer?->dosenProfile?->nik ?? '-'),
        TextColumn::make('secondary_advisor')
          ->label('Pembimbing 2')
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

            return $secondary?->lecturer?->name ?? '-';
          })
          ->description(function (?MentorshipAssignment $record): string {
            if ($record === null) {
              return '';
            }

            $secondary = MentorshipAssignment::query()
              ->where('student_user_id', $record->student_user_id)
              ->where('advisor_type', AdvisorType::Secondary->value)
              ->where('status', $record->status)
              ->with('lecturer.dosenProfile')
              ->first();

            return $secondary?->lecturer?->dosenProfile?->nik ?? '';
          }),
        BadgeColumn::make('status')
          ->colors([
            'success' => AssignmentStatus::Active->value,
            'gray' => AssignmentStatus::Ended->value,
          ])
          ->formatStateUsing(fn(string $state): string => match ($state) {
            'active' => 'Aktif',
            'ended' => 'Selesai',
            default => ucfirst($state),
          }),
        TextColumn::make('started_at')
          ->label('Tanggal Mulai')
          ->dateTime('d M Y')
          ->sortable()
          ->placeholder('-'),
        TextColumn::make('assignedBy.name')
          ->label('Ditetapkan Oleh')
          ->placeholder('-')
          ->toggleable(isToggledHiddenByDefault: true),
      ])
      ->filters([
        SelectFilter::make('status')
          ->options([
            'active' => 'Aktif',
            'ended' => 'Selesai',
          ]),
      ])
      ->recordActions([
        ViewAction::make(),
      ])
      ->toolbarActions([
        BulkActionGroup::make([
          DeleteBulkAction::make(),
        ]),
      ]);
  }
}
