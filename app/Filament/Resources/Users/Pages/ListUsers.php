<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Imports\UserImporter;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadCsvTemplate')
                ->label('Download CSV Template')
                ->icon('heroicon-m-arrow-down-tray')
                ->url(route('admin.users.import-template', ['format' => 'csv'])),
            Action::make('downloadExcelTemplate')
                ->label('Download Excel Template')
                ->icon('heroicon-m-arrow-down-tray')
                ->url(route('admin.users.import-template', ['format' => 'xlsx'])),
            ImportAction::make()
                ->importer(UserImporter::class)
                ->modalDescription('Gunakan template CSV atau Excel di atas. Pilih Tipe Import dan Program Studi di bawah. Kolom "prodi" di file bisa dikosongkan.')
                ->fileRules('extensions:csv,txt'),
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'mahasiswa' => Tab::make('Mahasiswa')
                ->modifyQueryUsing(fn(Builder $query): Builder => $query->whereHas('roles', static fn(Builder $roleQuery): Builder => $roleQuery->where('name', 'mahasiswa'))),
            'dosen' => Tab::make('Dosen')
                ->modifyQueryUsing(fn(Builder $query): Builder => $query->whereHas('roles', static fn(Builder $roleQuery): Builder => $roleQuery->where('name', 'dosen'))),
            'admin' => Tab::make('Admin')
                ->modifyQueryUsing(fn(Builder $query): Builder => $query->whereHas('roles', static fn(Builder $roleQuery): Builder => $roleQuery->where('name', 'admin'))),
        ];
    }
}
