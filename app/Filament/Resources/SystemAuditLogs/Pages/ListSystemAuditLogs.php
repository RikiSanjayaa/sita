<?php

namespace App\Filament\Resources\SystemAuditLogs\Pages;

use App\Filament\Resources\SystemAuditLogs\SystemAuditLogResource;
use Filament\Resources\Pages\ListRecords;

class ListSystemAuditLogs extends ListRecords
{
    protected static string $resource = SystemAuditLogResource::class;
}
