<?php

namespace App\Filament\Resources\ThesisProjects\Schemas;

use App\Filament\Resources\ThesisProjects\Tables\ThesisProjectsTable;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class ThesisProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Konteks Proyek')
                    ->description('Ringkasan identitas proyek untuk memastikan admin mengedit record yang tepat.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('student_name')
                            ->label('Mahasiswa')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('student_nim')
                            ->label('NIM')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('program_studi_name')
                            ->label('Program Studi')
                            ->disabled()
                            ->dehydrated(false),
                        TextInput::make('active_title')
                            ->label('Judul Aktif')
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull(),
                    ]),
                Section::make('Koreksi Administratif')
                    ->description('Gunakan halaman ini untuk membetulkan metadata proyek. Workflow operasional tetap lebih aman dijalankan dari aksi khusus pada halaman detail.')
                    ->columns(2)
                    ->schema([
                        Select::make('phase')
                            ->label('Fase')
                            ->options(ThesisProjectsTable::phaseOptions())
                            ->required()
                            ->native(false),
                        Select::make('state')
                            ->label('State')
                            ->options([
                                'active' => ThesisProjectsTable::stateLabel('active'),
                                'on_hold' => ThesisProjectsTable::stateLabel('on_hold'),
                                'completed' => ThesisProjectsTable::stateLabel('completed'),
                                'cancelled' => ThesisProjectsTable::stateLabel('cancelled'),
                            ])
                            ->required()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                                if ($state === 'completed') {
                                    $set('phase', 'completed');

                                    if (blank($get('completed_at'))) {
                                        $set('completed_at', now()->format('Y-m-d H:i:s'));
                                    }

                                    $set('cancelled_at', null);

                                    return;
                                }

                                if ($state === 'cancelled') {
                                    $set('phase', 'cancelled');

                                    if (blank($get('cancelled_at'))) {
                                        $set('cancelled_at', now()->format('Y-m-d H:i:s'));
                                    }

                                    $set('completed_at', null);

                                    return;
                                }

                                $set('completed_at', null);
                                $set('cancelled_at', null);
                            }),
                        DateTimePicker::make('started_at')
                            ->label('Mulai')
                            ->required()
                            ->native(false)
                            ->seconds(false),
                        DateTimePicker::make('completed_at')
                            ->label('Selesai')
                            ->visible(fn(Get $get): bool => $get('state') === 'completed')
                            ->required(fn(Get $get): bool => $get('state') === 'completed')
                            ->native(false)
                            ->seconds(false),
                        DateTimePicker::make('cancelled_at')
                            ->label('Dibatalkan Pada')
                            ->visible(fn(Get $get): bool => $get('state') === 'cancelled')
                            ->required(fn(Get $get): bool => $get('state') === 'cancelled')
                            ->native(false)
                            ->seconds(false),
                        Textarea::make('notes')
                            ->label('Catatan Admin')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
                Section::make('Override Judul & Proposal')
                    ->description('Admin dapat memperbarui judul dan proposal aktif kapan saja. Mahasiswa akan menerima notifikasi otomatis ketika data inti ini diubah.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title_id')
                            ->label('Judul Indonesia')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('title_en')
                            ->label('Judul Inggris')
                            ->maxLength(255),
                        Textarea::make('proposal_summary')
                            ->label('Ringkasan Proposal')
                            ->rows(6)
                            ->columnSpanFull(),
                        TextInput::make('current_proposal_file_name')
                            ->label('Proposal Aktif Saat Ini')
                            ->disabled()
                            ->dehydrated(false),
                        FileUpload::make('proposal_file')
                            ->label('Ganti File Proposal')
                            ->acceptedFileTypes([
                                'application/pdf',
                            ])
                            ->helperText('Kosongkan jika file proposal tidak berubah. Upload PDF baru jika admin perlu mengganti dokumen proposal aktif.')
                            ->storeFiles(false)
                            ->downloadable()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
