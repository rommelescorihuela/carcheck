<?php

namespace App\Console\Commands;

use App\Models\Vehicle;
use App\Services\ReminderGenerator;
use Illuminate\Console\Command;

class GenerateReminders extends Command
{
    protected $signature = 'reminders:generate';

    protected $description = 'Genera los recordatorios de mantenimiento y documentos y avisa a los usuarios.';

    public function handle(ReminderGenerator $generator): int
    {
        $created = $generator->generateAll(Vehicle::query());

        $this->info("Recordatorios creados: {$created}.");

        return self::SUCCESS;
    }
}
