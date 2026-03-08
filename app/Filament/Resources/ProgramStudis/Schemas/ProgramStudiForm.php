<?php

namespace App\Filament\Resources\ProgramStudis\Schemas;

use App\Models\ProgramStudi;
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
                TagsInput::make('concentrations')
                    ->label('Konsentrasi')
                    ->required()
                    ->nestedRecursiveRules(['min:2', 'max:255'])
                    ->suggestions(ProgramStudi::ILMU_KOMPUTER_CONCENTRATIONS)
                    ->helperText('Pisahkan setiap konsentrasi dengan tombol Enter.')
                    ->placeholder('Tambah konsentrasi'),
            ]);
    }
}
