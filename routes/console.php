<?php

use App\Console\Commands\ArchiveCompletedThesesCommand;
use App\Console\Commands\SendReminderDeadlineNotificationsCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('model:prune', [
    '--model' => [App\Models\SystemAuditLog::class],
])->daily();

Schedule::command(SendReminderDeadlineNotificationsCommand::class)->hourly();

// Permanent deletion: stays unregistered until the owner confirms a database backup
// and sets SITA_ARCHIVE_ENABLED=true.
if (config('sita.archive_enabled', false)) {
    Schedule::command(ArchiveCompletedThesesCommand::class)->daily();
}
