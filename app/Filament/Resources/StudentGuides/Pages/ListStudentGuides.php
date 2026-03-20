<?php

namespace App\Filament\Resources\StudentGuides\Pages;

use App\Filament\Resources\StudentGuides\StudentGuideResource;
use Filament\Resources\Pages\ListRecords;

class ListStudentGuides extends ListRecords
{
    protected static string $resource = StudentGuideResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
