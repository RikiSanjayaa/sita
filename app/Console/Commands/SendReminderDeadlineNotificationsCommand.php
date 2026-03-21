<?php

namespace App\Console\Commands;

use App\Services\ReminderDeadlineNotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class SendReminderDeadlineNotificationsCommand extends Command
{
    protected $signature = 'notifications:send-deadline-reminders {--at= : Optional ISO datetime for testing}';

    protected $description = 'Send upcoming reminderDeadline notifications for bimbingan, sempro, and sidang';

    public function handle(ReminderDeadlineNotificationService $service): int
    {
        $at = $this->option('at');
        $reference = is_string($at) && $at !== ''
            ? CarbonImmutable::parse($at)
            : null;

        $sentCount = $service->sendUpcomingReminders($reference);

        $this->info(sprintf('Sent %d reminder notifications.', $sentCount));

        return self::SUCCESS;
    }
}
