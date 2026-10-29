<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

if (config('soloinventory.scheduled_backups')) {
    Schedule::exec('sh /var/www/html/docker/backup-cycle.sh')
        ->cron('20 8,20 * * *')
        ->timezone('Europe/Rome')
        ->withoutOverlapping(30)
        ->appendOutputTo(storage_path('logs/soloinventory-backup.log'));
}
