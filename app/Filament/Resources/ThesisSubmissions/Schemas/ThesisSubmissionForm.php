<?php

namespace App\Filament\Resources\ThesisSubmissions\Schemas;

use App\Enums\AppRole;
use App\Enums\ThesisSubmissionStatus;
use App\Models\User;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ThesisSubmissionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Mahasiswa')
                    ->columns(2)
                    ->schema([
                        Select::make('student_user_id')
                            ->label('Mahasiswa')
                            ->options(fn(): array => User::query()
                                ->whereHas('roles', static fn($query) => $query->where('name', AppRole::Mahasiswa->value))
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn(User $user) => [
                                    $user->id => $user->name . ' (' . ($user->mahasiswaProfile?->nim ?? '-') . ')',
                                ])
                                ->all())
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false)
                            ->disabled(fn(string $operation): bool => $operation === 'edit'),
                        TextInput::make('program_studi')
                            ->label('Program Studi')
                            ->maxLength(255),
                    ]),
                Section::make('Detail Proposal')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title_id')
                            ->label('Judul (Bahasa Indonesia)')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('title_en')
                            ->label('Judul (Bahasa Inggris)')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Textarea::make('proposal_summary')
                            ->label('Ringkasan Proposal')
                            ->rows(4)
                            ->columnSpanFull(),
                        FileUpload::make('proposal_file_path')
                            ->label('File Proposal')
                            ->disk('public')
                            ->directory('proposal_files')
                            ->acceptedFileTypes(['application/pdf'])
                            ->maxSize(10240),
                    ]),
                Section::make('Status')
                    ->columns(2)
                    ->schema([
                        Select::make('status')
                            ->options(collect(ThesisSubmissionStatus::cases())
                                ->mapWithKeys(fn(ThesisSubmissionStatus $s) => [
                                    $s->value => ucwords(str_replace('_', ' ', $s->value)),
                                ])->all())
                            ->required()
                            ->default(ThesisSubmissionStatus::MenungguPersetujuan->value)
                            ->native(false),
                        Toggle::make('is_active')
                            ->default(true)
                            ->required(),
                    ]),
            ]);
    }
}
