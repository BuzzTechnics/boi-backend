<?php

namespace Boi\Backend\Nova\Actions;

use Boi\Backend\Enums\ApplicationStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Assign submitted applications to a Project Officer (role 3) and notify them.
 * The program-specific notification is supplied by {@see assignedNotification()};
 * the User model resolves from auth config (override {@see userModel()} if needed).
 */
abstract class AssignToProjectOfficer extends Action
{
    use InteractsWithQueue, Queueable;

    public $name = 'Assign to Project Officer';

    /** @return class-string the program's "assigned" notification. */
    abstract protected function assignedNotification(): string;

    public function handle(ActionFields $fields, Collection $models)
    {
        $projectOfficerId = $fields->project_officer_id;

        if (! $projectOfficerId) {
            return Action::danger('Please select a project officer.');
        }

        $projectOfficer = ($this->userModel())::find($projectOfficerId);

        if (! $projectOfficer) {
            return Action::danger('Project officer not found.');
        }

        // Only submitted (complete) applications may be assigned to a Project Officer
        $submitted = $models->filter(fn ($model) => $model->status === ApplicationStatus::SUBMITTED);
        $ineligible = $models->count() - $submitted->count();

        if ($submitted->isEmpty()) {
            return Action::danger(
                'Only complete and submitted applications can be assigned to a Project Officer.'
                .' None of the selected applications are submitted.'
            );
        }

        $updatedCount = 0;
        $modelClass = $submitted->first()->getMorphClass();
        $transactionType = $this->getTransactionType($modelClass);
        $notification = $this->assignedNotification();

        foreach ($submitted as $model) {
            $model->project_officer_id = $projectOfficerId;
            $model->save();
            $projectOfficer->notify(new $notification(
                $transactionType,
                $model->id,
                'Application'
            ));

            $updatedCount++;
        }

        $message = "Successfully assigned {$updatedCount} ".strtolower($modelClass).' to project officer.';
        if ($ineligible > 0) {
            $message .= " {$ineligible} selected application(s) were skipped because they are not submitted.";
        }

        return Action::message($message);
    }

    private function getTransactionType(string $modelClass): string
    {
        $className = class_basename($modelClass);

        return match (strtolower($className)) {
            'esg' => 'esg',
            'Adrr' => 'adrr',
            'loanapplication' => 'loan_app',
            'company' => 'account_opening',
            default => strtolower($className),
        };
    }

    public function fields(NovaRequest $request)
    {
        $projectOfficers = ($this->userModel())::where('role_id', 3)
            ->applyOfficerRouting()
            ->select('id', 'name', 'email')
            ->get()
            ->mapWithKeys(function ($user) {
                return [$user->id => $user->name.' ('.$user->email.')'];
            });

        return [
            Select::make('Project Officer', 'project_officer_id')
                ->options($projectOfficers)
                ->required()
                ->help('Select a project officer to assign these records to.'),
        ];
    }

    /** @return class-string the app's authenticatable User model. */
    protected function userModel(): string
    {
        return config('auth.providers.users.model');
    }
}
