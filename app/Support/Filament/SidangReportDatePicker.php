<?php

namespace App\Support\Filament;

use Filament\Forms\Components\DatePicker;

final class SidangReportDatePicker extends DatePicker
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->native(false)->locale('id')->displayFormat('d F Y')
            ->firstDayOfWeek(1)->closeOnDateSelection();
    }
}
