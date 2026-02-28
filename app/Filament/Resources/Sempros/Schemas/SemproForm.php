<?php

namespace App\Filament\Resources\Sempros\Schemas;

use App\Enums\AppRole;
use App\Enums\SemproStatus;
use App\Models\ThesisSubmission;
use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SemproForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('thesis_submission_id')
                    ->label('Thesis Submission')
                    ->options(fn (): array => ThesisSubmission::query()
                        ->with('student')
                        ->orderByDesc('id')
                        ->get()
                        ->mapWithKeys(static fn (ThesisSubmission $submission): array => [
                            $submission->id => sprintf(
                                '#%d - %s',
                                $submission->id,
                                $submission->student?->name ?? $submission->title_id,
                            ),
                        ])
                        ->all())
                    ->searchable()
                    ->preload()
                    ->required()
                    ->native(false),
                Select::make('status')
                    ->options(array_combine(SemproStatus::values(), SemproStatus::values()))
                    ->required()
                    ->default(SemproStatus::Draft->value)
                    ->native(false),
                DateTimePicker::make('scheduled_for'),
                TextInput::make('location')
                    ->maxLength(255),
                Select::make('mode')
                    ->options([
                        'offline' => 'offline',
                        'online' => 'online',
                    ])
                    ->required()
                    ->default('offline')
                    ->native(false),
                DateTimePicker::make('revision_due_at'),
                Repeater::make('examiner_user_ids')
                    ->label('Penguji')
                    ->defaultItems(2)
                    ->minItems(2)
                    ->maxItems(2)
                    ->schema([
                        Select::make('user_id')
                            ->label('Dosen Penguji')
                            ->options(fn (): array => User::query()
                                ->whereHas('roles', static fn ($query) => $query->where('name', AppRole::Dosen->value))
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false),
                    ])
                    ->columnSpanFull(),
                DateTimePicker::make('approved_at'),
                Select::make('approved_by')
                    ->label('Approved By')
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
