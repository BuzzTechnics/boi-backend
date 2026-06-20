<?php

namespace Boi\Backend\Nova\Actions;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\File;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Trix;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Upload a document (e.g. offer letter) and queue its delivery per application.
 * The program-specific job is supplied by {@see documentJob()}.
 */
abstract class CreateApplicationDocument extends Action
{
    /** @return class-string the document job (dispatch: appId, createdBy, type, message, key). */
    abstract protected function documentJob(): string;

    public function handle(ActionFields $fields, Collection $models)
    {
        $type = trim((string) $fields->type);
        $message = $fields->message ? (string) $fields->message : null;
        $key = (string) $fields->document_file;

        if (! $type) {
            return Action::danger('Type is required');
        }

        if (! $key) {
            return Action::danger('Document file is required');
        }

        $createdBy = request()->user()?->id;
        $job = $this->documentJob();

        $models->each(function ($app) use ($type, $message, $key, $createdBy, $job) {
            $job::dispatch(
                (int) $app->id,
                $createdBy ? (int) $createdBy : null,
                $type,
                $message,
                $key
            );
        });

        return Action::message('Document queued and email will be sent');
    }

    public function fields(NovaRequest $request)
    {
        return [
            Select::make('Type', 'type')
                ->options([
                    'offer_letter' => 'Offer Letter',
                ])
                ->rules('required'),

            Trix::make('Message', 'message')
                ->rules('required'),

            File::make('Document File', 'document_file')
                ->disk('s3')
                ->path('application-documents')
                ->store(function (Request $request, $model, $attribute, $requestAttribute) {
                    return [
                        $attribute => $request->file($requestAttribute)->store('application-documents', 's3'),
                    ];
                })
                ->rules('required', 'file', 'mimes:pdf,doc,docx'),
        ];
    }
}
