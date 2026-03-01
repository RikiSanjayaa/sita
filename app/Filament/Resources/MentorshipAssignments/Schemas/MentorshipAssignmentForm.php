<?php

namespace App\Filament\Resources\MentorshipAssignments\Schemas;

use App\Enums\AppRole;
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
    $dosenOptions = fn(): array => User::query()
      ->whereHas('roles', static fn($q) => $q->where('name', AppRole::Dosen->value))
      ->orderBy('name')
      ->get()
      ->mapWithKeys(fn(User $u) => [
        $u->id => $u->name . ' (' . ($u->dosenProfile?->nik ?? '-') . ')',
      ])
      ->all();

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
            Select::make('pembimbing_1')
              ->label('Pembimbing 1')
              ->options($dosenOptions)
              ->searchable()
              ->preload()
              ->required()
              ->native(false),
            Select::make('pembimbing_2')
              ->label('Pembimbing 2')
              ->options($dosenOptions)
              ->searchable()
              ->preload()
              ->required()
              ->native(false),
          ]),
        Section::make('Catatan')
          ->schema([
            Textarea::make('notes')
              ->label('Catatan')
              ->rows(2),
          ]),
      ]);
  }
}
