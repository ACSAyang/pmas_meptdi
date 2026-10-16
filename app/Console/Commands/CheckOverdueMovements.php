<?php

namespace App\Console\Commands;

use App\Services\VerificationService;
use Illuminate\Console\Command;

class CheckOverdueMovements extends Command
{
    protected $signature = 'pmas:check-overdue';

    protected $description = 'Flag open movements past their expected return (plus grace period) and open verification cases.';

    public function handle(VerificationService $service): int
    {
        $count = $service->openOverdueCases();
        $this->info($count ? "Opened {$count} overdue verification case(s)." : 'No new overdue movements.');

        return self::SUCCESS;
    }
}
