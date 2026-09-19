<?php

namespace Boi\Backend\Sla\Support;

/**
 * What the reminder and escalation letters need to say about a case, in the terms
 * the template uses: {Task Name}, {Process Name}, {Company Name}, {Company
 * Registration Number} and the portal link.
 *
 * A plain object rather than the portal's own model, so this package never learns
 * what an Application is.
 */
class SlaCaseSummary
{
    public function __construct(
        public string $reference,
        public string $taskName,
        public ?string $companyName = null,
        public ?string $registrationNumber = null,
        public ?string $url = null,
    ) {}
}
