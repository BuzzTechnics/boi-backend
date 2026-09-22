<?php

namespace Boi\Backend\Tests\Support;

use Boi\Backend\DocumentLibrary\Concerns\HasDocumentRequests;
use Illuminate\Database\Eloquent\Model;

/** Stands in for a fund's case (Application / LoanApplication) that owns documents. */
class FakeCase extends Model
{
    use HasDocumentRequests;

    protected $table = 'cases';

    protected $guarded = [];

    public $timestamps = false;
}
