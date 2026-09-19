<?php

namespace Boi\Backend\Tests\Support;

use Boi\Backend\Sla\Contracts\SlaDirectory;
use Boi\Backend\Sla\Models\SlaTracker;
use Boi\Backend\Sla\Support\SlaCaseSummary;
use Illuminate\Support\Collection;

/**
 * The portal half of the engine, as a test double: a fixed cast of recipients and a
 * case description, so the tests are about the timeline rather than about anyone's
 * user model.
 */
class FakeDirectory implements SlaDirectory
{
    /** @var array<string, Collection<int, FakeUser>> */
    public array $people = [];

    public bool $caseExists = true;

    public function describe(SlaTracker $tracker): ?SlaCaseSummary
    {
        if (! $this->caseExists) {
            return null;
        }

        return new SlaCaseSummary(
            reference: 'APP-'.$tracker->case_id,
            taskName: 'NSDC Application Review',
            companyName: 'Sugar Co Ltd',
            registrationNumber: 'RC123456',
            url: 'https://portal.test/applications/'.$tracker->case_id,
        );
    }

    public function owners(SlaTracker $tracker): Collection
    {
        return $this->people['owners'] ?? collect();
    }

    public function supervisors(SlaTracker $tracker, int $level): Collection
    {
        return $this->people["level_{$level}"] ?? collect();
    }
}
