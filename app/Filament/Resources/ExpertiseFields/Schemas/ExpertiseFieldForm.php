<?php

namespace App\Filament\Resources\ExpertiseFields\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ExpertiseFieldForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama Bidang')
                    ->hintIcon(
                        'heroicon-m-information-circle',
                        'Bidang keilmuan adalah kompetensi dosen yang dapat digunakan lintas prodi. Satu dosen dapat memiliki beberapa bidang keilmuan.',
                    )
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                        if (blank($get('slug'))) {
                            $set('slug', Str::slug((string) $state));
                        }
                    })
                    ->maxLength(255),
                TextInput::make('slug')
                    ->disabled()
                    ->dehydrated()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Textarea::make('description')
                    ->label('Deskripsi')
                    ->rows(4)
                    ->maxLength(1000)
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('Aktif')
                    ->hintIcon(
                        'heroicon-m-question-mark-circle',
                        'Bidang nonaktif tetap tersimpan pada data lama, tetapi tidak tersedia untuk penetapan baru.',
                    )
                    ->default(true),
            ])
            ->columns(2);
    }
}
