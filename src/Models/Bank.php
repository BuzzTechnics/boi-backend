<?php

namespace Boi\Backend\Models;

use Boi\Backend\Database\Factories\BankFactory;
use Boi\Backend\Models\Concerns\UsesBoiApiDatabase;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Eloquent model for shared `banks` table (boi-api DB connection in consuming apps). */
class Bank extends Model
{
    use HasFactory;
    use SoftDeletes;
    use UsesBoiApiDatabase;

    protected $fillable = [
        'name',
        'short_name',
        'code',
        'edoc_bank_id',
    ];

    protected function casts(): array
    {
        return [
            'edoc_bank_id' => 'integer',
        ];
    }

    protected static function newFactory(): BankFactory
    {
        return BankFactory::new();
    }
}
