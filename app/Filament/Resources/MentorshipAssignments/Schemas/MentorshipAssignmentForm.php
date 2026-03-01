<?php

namespace App\Filament\Resources\MentorshipAssignments\Schemas;

use App\Enums\AdvisorType;
use App\Enums\AppRole;
use App\Enums\AssignmentStatus;
use App\Enums\ThesisSubmissionStatus;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MentorshipAssignmentForm
{
  public static function configure(Schema $schema): Schema
  {
    return $schema
      ->components([
        Section::make('Mahasiswa')
          ->schema([
            Select::make('student_user_id')
              ->label('Mahasiswa')
              ->options(fn(): array => User::query()
                ->whereHas('roles', static fn($q) => $q->where('name', AppRole::Mahasiswa->value))
                ->whereHas('thesisSubmissions', static fn($q) => $q->where('status', ThesisSubmissionStatus::SemproSelesai->value)
                  ->orWhere('status', ThesisSubmissionStatus::PembimbingDitetapkan->value))
                ->orderBy('name')
                ->get()
                ->mapWithKeys(fn(User $u) => [
                  $u->id => $u->name . ' (' . ($u->mahasiswaProfile?->nim ?? '-') . ')',
                ])
                ->all())
              ->searchable()
              ->preload()
              ->required()
              ->native(false)
              ->disabled(fn(string $operation): bool => $operation === 'edit'),
          ]),
        Section::make('Dosen Pembimbing')
          ->columns(2)
          ->schema([
            Select::make('lecturer_user_id')
              ->label('Dosen Pembimbing')
              ->options(fn(): array => User::query()
                ->whereHas('roles', static fn($q) => $q->where('name', AppRole::Dosen->value))
                ->orderBy('name')
                ->get()
                ->mapWithKeys(fn(User $u) => [
                  $u->id => $u->name . ' (' . ($u->dosenProfile?->nik ?? '-') . ')',
                ])
                ->all())
              ->searchable()
              ->preload()
              ->required()
              ->native(false),
            Select::make('advisor_type')
              ->label('Tipe')
              ->options([
                AdvisorType::Primary->value => 'Pembimbing 1',
                AdvisorType::Secondary->value => 'Pembimbing 2',
              ])
              ->required()
              ->native(false),
          ]),
        Section::make('Status & Catatan')
          ->columns(2)
          ->schema([
            Select::make('status')
              ->options([
                AssignmentStatus::Active->value => 'Aktif',
                AssignmentStatus::Ended->value => 'Selesai',
              ])
              ->default(AssignmentStatus::Active->value)
              ->required()
              ->native(false),
            Textarea::make('notes')
              ->label('Catatan')
              ->rows(2)
              ->columnSpanFull(),
          ]),
      ]);
  }
}
