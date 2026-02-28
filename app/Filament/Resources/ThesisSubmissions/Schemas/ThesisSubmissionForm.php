<?php

namespace App\Filament\Resources\ThesisSubmissions\Schemas;

use App\Enums\AppRole;
use App\Enums\ThesisSubmissionStatus;
use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ThesisSubmissionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('student_user_id')
                    ->label('Mahasiswa')
                    ->options(fn (): array => User::query()
                        ->whereHas('roles', static fn ($query) => $query->where('name', AppRole::Mahasiswa->value))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->preload()
                    ->required()
                    ->native(false),
                TextInput::make('program_studi')
                    ->maxLength(255),
                TextInput::make('title_id')
                    ->label('Judul (Bahasa Indonesia)')
                    ->required()
                    ->maxLength(255),
                TextInput::make('title_en')
                    ->label('Judul (Bahasa Inggris)')
                    ->maxLength(255),
                Textarea::make('proposal_summary')
                    ->label('Ringkasan Proposal')
                    ->rows(4)
                    ->columnSpanFull(),
                Select::make('status')
                    ->options(array_combine(ThesisSubmissionStatus::values(), ThesisSubmissionStatus::values()))
                    ->required()
                    ->default(ThesisSubmissionStatus::IntakeCreated->value)
                    ->native(false),
                Toggle::make('is_active')
                    ->default(true)
                    ->required(),
                DateTimePicker::make('submitted_at'),
                DateTimePicker::make('approved_at'),
                Select::make('approved_by')
                    ->label('Disetujui oleh')
                    ->options(fn (): array => User::query()
                        ->whereHas('roles', static fn ($query) => $query->where('name', AppRole::Admin->value))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->preload()
                    ->native(false),
            ]);
    }
}
