<?php

namespace Boi\Backend\Tests\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

/**
 * Stands in for a portal's User: the engine only ever asks a recipient for its id,
 * name, email and the ability to be notified.
 */
class FakeUser extends Model
{
    use Notifiable;

    protected $table = 'users';

    protected $guarded = [];

    public $timestamps = false;
}
