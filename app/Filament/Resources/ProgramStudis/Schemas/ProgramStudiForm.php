<?php

namespace App\Filament\Resources\ProgramStudis\Schemas;

use App\Enums\DegreeLevel;
use App\Models\Faculty;
use App\Models\ProgramStudi;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ProgramStudiForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('faculty_id')
                    ->label('Fakultas')
                    ->hintIcon(
                        'heroicon-m-question-mark-circle',
                        'Satu program studi hanya berada di bawah satu fakultas. Relasi ini digunakan untuk pengelompokan data dan target pengumuman.',
                    )
                    ->options(fn(): array => Faculty::query()
                        ->where('is_active', true)
                        ->where('is_placeholder', false)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->preload()
                    ->required()
                    ->helperText('Satu program studi berada di bawah satu fakultas.'),
                TextInput::make('name')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (string $operation, $state, Set $set, Get $get): void {
                        if ($operation === 'create') {
                            $set('slug', Str::slug((string) $state));
                        }

                        if (
                            $operation === 'create'
                            && collect($get('concentrations'))->filter()->isEmpty()
                            && Str::slug((string) $state) === 'ilmu-komputer'
                        ) {
                            $set('concentrations', ProgramStudi::ILMU_KOMPUTER_CONCENTRATIONS);
                        }
                    })
                    ->maxLength(255),
                TextInput::make('slug')
                    ->disabled()
                    ->dehydrated()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                CheckboxList::make('degree_levels')
                    ->label('Jenjang Tersedia')
                    ->hintIcon(
                        'heroicon-m-information-circle',
                        'Hanya jenjang yang dicentang yang dapat dipilih saat membuat atau mengimpor mahasiswa pada prodi ini. Jenjang mahasiswa yang sudah tersimpan tidak berubah otomatis.',
                    )
                    ->options(DegreeLevel::options())
                    ->required()
                    ->minItems(1)
                    ->columns(3)
                    ->helperText('Jenjang mahasiswa yang dapat dipilih pada program studi ini.'),
                TagsInput::make('concentrations')
                    ->label('Konsentrasi')
                    ->hintIcon(
                        'heroicon-m-question-mark-circle',
                        'Konsentrasi adalah peminatan internal prodi, bukan bidang keilmuan dosen dan bukan syarat kelayakan pembimbing.',
                    )
                    ->required()
                    ->nestedRecursiveRules(['min:2', 'max:255'])
                    ->suggestions(ProgramStudi::ILMU_KOMPUTER_CONCENTRATIONS)
                    ->helperText('Pisahkan setiap konsentrasi dengan tombol Enter.')
                    ->placeholder('Tambah konsentrasi'),
            ]);
    }
}
