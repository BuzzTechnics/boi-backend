<?php

namespace Boi\Backend\Models\Concerns;

/**
 * Applicant-facing save for an application model: mass-assigns only
 * applicant-editable data (admin-only fields stripped) and syncs/detaches
 * uploaded files. The using model must expose a `files()` relationship.
 */
trait HandlesApplicationPersistence
{
    public function handleAndSave($data, $uploads = null, $filesToDetach = null)
    {
        $this->update(array_diff_key($data, array_flip($this->adminOnlyFields())));

        if ($uploads) {
            $uploadsToSync = [];
            foreach ($uploads as $file) {
                $path = $file['path'] ?? null;
                if (! is_string($path) || $path === '' || empty($file['id'])) {
                    continue;
                }
                if (str_contains($path, '..')) {
                    continue;
                }
                $uploadsToSync[$file['id']] = ['path' => $path];
            }

            if (! empty($uploadsToSync)) {
                $this->files()->syncWithoutDetaching($uploadsToSync);
            }
        }

        if ($filesToDetach && is_array($filesToDetach)) {
            foreach ($filesToDetach as $fileId) {
                $this->files()->detach($fileId);
            }
        }

        return $this;
    }

    /**
     * Fields only staff may set — stripped from applicant-supplied data.
     * Apps may override to add program-specific protected columns.
     *
     * @return list<string>
     */
    protected function adminOnlyFields(): array
    {
        return [
            'status', 'internal_status', 'rejection_reason', 'project_officer_comments',
            'project_officer_amount', 'workflow_id', 'resubmit_workflow_id', 'resubmitted_at',
            'sharepoint_response', 'bank_statement_analysis',
        ];
    }
}
