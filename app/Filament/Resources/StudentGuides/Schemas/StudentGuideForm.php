<?php

namespace App\Filament\Resources\StudentGuides\Schemas;

use App\Models\ProgramStudi;
use App\Support\StudentGuideContent;
use App\Support\WitaDateTime;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StudentGuideForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Prodi')
                    ->columns(2)
                    ->schema([
                        Placeholder::make('program_studi_name')
                            ->label('Program Studi')
                            ->content(fn(?ProgramStudi $record): string => $record?->name ?? '-'),
                        Placeholder::make('student_guide_updated_at')
                            ->label('Terakhir disimpan')
                            ->content(fn(?ProgramStudi $record): string => WitaDateTime::format($record?->student_guide_updated_at)),
                    ]),
                Section::make('Header Panduan')
                    ->description('Bagian pembuka yang muncul di halaman panduan mahasiswa.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('hero_title')
                            ->label('Judul halaman')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('search_hint')
                            ->label('Hint pencarian')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('hero_subtitle')
                            ->label('Subtitle halaman')
                            ->required()
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
                Section::make('Panduan Utama')
                    ->description('Langkah, ketentuan, atau tips yang menjadi konten utama tiap prodi.')
                    ->schema([
                        Repeater::make('guidance_cards')
                            ->label('Kartu panduan')
                            ->default(StudentGuideContent::defaults()['guidance_cards'])
                            ->minItems(1)
                            ->collapsed()
                            ->itemLabel(fn(array $state): ?string => $state['title'] ?? null)
                            ->schema([
                                Hidden::make('id'),
                                TextInput::make('title')
                                    ->label('Judul')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('badge')
                                    ->label('Badge')
                                    ->required()
                                    ->maxLength(255),
                                Select::make('icon')
                                    ->label('Ikon')
                                    ->options(StudentGuideContent::iconOptions())
                                    ->required()
                                    ->native(false),
                                Select::make('action')
                                    ->label('Tombol aksi')
                                    ->options(StudentGuideContent::actionOptions())
                                    ->required()
                                    ->native(false),
                                Textarea::make('description')
                                    ->label('Deskripsi singkat')
                                    ->required()
                                    ->rows(2)
                                    ->columnSpanFull(),
                                TagsInput::make('bullets')
                                    ->label('Poin panduan')
                                    ->required()
                                    ->helperText('Tulis satu poin lalu tekan Enter.')
                                    ->nestedRecursiveRules(['min:3', 'max:255'])
                                    ->columnSpanFull(),
                                TagsInput::make('keywords')
                                    ->label('Kata kunci pencarian')
                                    ->required()
                                    ->helperText('Dipakai untuk membantu fitur pencarian panduan.')
                                    ->nestedRecursiveRules(['min:2', 'max:100'])
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->columnSpanFull(),
                    ]),
                Section::make('FAQ')
                    ->description('Pertanyaan yang sering muncul dari mahasiswa di prodi ini.')
                    ->schema([
                        Repeater::make('faq_items')
                            ->label('Item FAQ')
                            ->default(StudentGuideContent::defaults()['faq_items'])
                            ->minItems(1)
                            ->collapsed()
                            ->itemLabel(fn(array $state): ?string => $state['question'] ?? null)
                            ->schema([
                                Hidden::make('id'),
                                TextInput::make('question')
                                    ->label('Pertanyaan')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                                Textarea::make('answer')
                                    ->label('Jawaban')
                                    ->required()
                                    ->rows(4)
                                    ->columnSpanFull(),
                                TagsInput::make('tags')
                                    ->label('Tag pencarian')
                                    ->required()
                                    ->nestedRecursiveRules(['min:2', 'max:100'])
                                    ->columnSpanFull(),
                            ])
                            ->columnSpanFull(),
                    ]),
                Section::make('Dokumen Template')
                    ->description('File template, ketentuan, atau dokumen pendukung yang bisa diunduh mahasiswa.')
                    ->schema([
                        Repeater::make('template_docs')
                            ->label('Dokumen template')
                            ->default(StudentGuideContent::defaults()['template_docs'])
                            ->minItems(1)
                            ->collapsed()
                            ->itemLabel(fn(array $state): ?string => $state['title'] ?? null)
                            ->schema([
                                Hidden::make('id'),
                                Hidden::make('file_name'),
                                TextInput::make('title')
                                    ->label('Judul dokumen')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('format')
                                    ->label('Format')
                                    ->required()
                                    ->maxLength(50),
                                TextInput::make('badge')
                                    ->label('Badge')
                                    ->maxLength(255),
                                FileUpload::make('file_path')
                                    ->label('File template')
                                    ->disk('public')
                                    ->directory(fn(?ProgramStudi $record): string => 'guide-templates/program-studi-'.($record?->getKey() ?? 'draft'))
                                    ->acceptedFileTypes([
                                        'application/pdf',
                                        'application/msword',
                                        'application/vnd.ms-excel',
                                        'application/vnd.ms-powerpoint',
                                        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                        'application/zip',
                                    ])
                                    ->downloadable()
                                    ->storeFileNamesIn('file_name')
                                    ->columnSpanFull(),
                                Textarea::make('description')
                                    ->label('Deskripsi')
                                    ->required()
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ])
                            ->columns(3)
                            ->columnSpanFull(),
                    ]),
                Section::make('Bantuan')
                    ->description('Konten bantuan di bagian bawah halaman panduan.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('help_title')
                            ->label('Judul bantuan')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('help_box_title')
                            ->label('Judul box bantuan')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('help_description')
                            ->label('Deskripsi bantuan')
                            ->required()
                            ->rows(3),
                        Textarea::make('help_box_description')
                            ->label('Isi box bantuan')
                            ->required()
                            ->rows(3),
                        TextInput::make('message_template_title')
                            ->label('Judul format pesan')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TagsInput::make('message_template_steps')
                            ->label('Langkah format pesan')
                            ->required()
                            ->helperText('Tulis satu langkah lalu tekan Enter.')
                            ->nestedRecursiveRules(['min:3', 'max:255'])
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
