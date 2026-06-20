<?php

namespace Boi\Backend\Nova\Actions;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Trix;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Email each selected application's owner. The program-specific mail
 * notification is supplied by {@see mailNotification()}.
 */
abstract class SendEmail extends Action implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public static $chunkCount = 1000;

    /** @return class-string the program's mail notification (ctor: subject, lines, url, name:). */
    abstract protected function mailNotification(): string;

    public function handle(ActionFields $fields, Collection $models)
    {
        $notification = $this->mailNotification();

        $models->each(function ($model) use ($fields, $notification) {
            $lines = explode("\n", $fields->message);
            $user = $model->user;
            if ($user) {
                $user->notify(new $notification(
                    $fields->subject,
                    $lines,
                    $fields->action_url,
                    name: $user->name,
                ));
            }
        });

        return Action::message('Emails have been sent successfully!');
    }

    public function fields(NovaRequest $request)
    {
        return [
            Text::make('Subject')
                ->rules('required', 'string', 'max:255'),

            Trix::make('Message')
                ->rules('required'),

            Text::make('Action URL', 'action_url')
                ->rules('nullable', 'url')
                ->help('Optional URL for the email action button'),
        ];
    }
}
