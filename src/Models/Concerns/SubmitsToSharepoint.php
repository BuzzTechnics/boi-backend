<?php

namespace Boi\Backend\Models\Concerns;

use Boi\Backend\Enums\ApplicationStatus;
use Boi\Backend\Services\SharepointClient;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates submitting an application to the SharePoint / Power Automate
 * workflow: build the payload (program-specific, via the abstract hook),
 * persist it for debugging, send it through {@see SharepointClient}, then map
 * the result onto the model's columns.
 *
 * The using model must provide the program-specific payload builder and expose
 * the columns it writes (sharepoint_payload, sharepoint_response, workflow_id,
 * resubmit_workflow_id, resubmitted_at, internal_status).
 */
trait SubmitsToSharepoint
{
    /** Build the SharePoint payload (template + program overlays). */
    abstract protected function sharepointPayloadFromTemplate(): array;

    public function submitToSharepoint(?string $urlOverride = null, bool $isResubmit = false): array
    {
        $this->loadMissing($this->sharepointRelations());

        // Reset prior-run state so stale payload/response from an earlier failed attempt
        // can't be mistaken for this attempt's data during debugging.
        $this->sharepoint_payload = null;
        $this->sharepoint_response = null;
        $this->save();

        $payload = SharepointClient::normalizePayload($this->sharepointPayloadFromTemplate());

        // Callers (e.g. the resubmit job) can target an alternate PA workflow.
        $url = $urlOverride ?: config('services.sharepoint.url');
        $key = config('services.sharepoint.key');
        $jsonPayload = SharepointClient::encode($payload);

        // Persist the payload BEFORE the HTTP send so a failed attempt still leaves a
        // reproducible JSON in the DB for debugging.
        $this->sharepoint_payload = json_decode($jsonPayload, true);
        $this->save();
        Log::info('Submitting to SharePoint..., SharePoint payload saved', [
            'application_id' => $this->id,
            'sharepoint_payload' => $jsonPayload,
        ]);

        $context = [
            'application_id' => $this->id,
            'user_id' => $this->user_id,
            'project_officer_id' => $this->project_officer_id,
            'workflow_id' => $this->workflow_id,
            'url' => $url,
            'payload_types' => collect($payload)
                ->map(fn ($v) => is_array($v) ? 'array' : (is_object($v) ? get_class($v) : gettype($v)))
                ->all(),
        ];

        $result = SharepointClient::submit($jsonPayload, $url, $key, $context);

        if ($result['ok']) {
            $this->sharepoint_response = ['response' => $result['response']];
            $extracted = SharepointClient::extractWorkflowId($result['response']);
            if ($isResubmit) {
                // Record the replay separately so the original submission trail (workflow_id,
                // internal_status=SHAREPOINT) stays intact.
                $this->resubmit_workflow_id = $extracted;
                $this->resubmitted_at = now();
            } else {
                $this->internal_status = ApplicationStatus::SHAREPOINT;
                $this->workflow_id = $extracted;
            }
            $this->save();

            return $this->sharepoint_response;
        }

        // Failure — persist the structured error (drops the internal `ok` flag).
        $this->sharepoint_response = collect($result)->except('ok')->all();
        $this->save();

        return $this->sharepoint_response;
    }

    /**
     * Relations eager-loaded before building the payload. Apps may override.
     *
     * @return list<string>
     */
    protected function sharepointRelations(): array
    {
        return ['user', 'projectOfficer', 'state.region', 'state.internalRegion', 'lga', 'boiOffice.state', 'files'];
    }
}
