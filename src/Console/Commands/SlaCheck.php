<?php

namespace Boi\Backend\Console\Commands;

use Boi\Backend\Sla\SlaEngine;
use Illuminate\Console\Command;

/**
 * Walks the SLA clocks and sends whatever has come due. Schedule it every fifteen
 * minutes; each level stamps itself, so running more often changes nothing but the
 * timeliness.
 */
class SlaCheck extends Command
{
    protected $signature = 'boi:sla-check';

    protected $description = 'Send SLA reminders (75%) and escalations (100/150/200% of SLA)';

    public function handle(SlaEngine $engine): int
    {
        $counts = $engine->run();

        $this->info(sprintf(
            'Reminders: %d | Escalation 1: %d | Escalation 2: %d | Escalation 3: %d',
            $counts['reminder'] ?? 0,
            $counts['escalation_1'] ?? 0,
            $counts['escalation_2'] ?? 0,
            $counts['escalation_3'] ?? 0,
        ));

        return self::SUCCESS;
    }
}
