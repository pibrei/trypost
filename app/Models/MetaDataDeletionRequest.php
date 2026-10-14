<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MetaDataDeletionRequest\Status;
use Database\Factories\MetaDataDeletionRequestFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A Data Deletion Request Meta sent for one of its users. The confirmation code
 * is what Meta shows the person; the status page reads it without exposing who
 * asked. `affected_accounts` lists the removed channels as `{id, platform}`.
 */
class MetaDataDeletionRequest extends Model
{
    /** @use HasFactory<MetaDataDeletionRequestFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'confirmation_code',
        'meta_user_id',
        'status',
        'affected_accounts',
        'completed_at',
    ];

    protected $hidden = [
        'meta_user_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => Status::class,
            'affected_accounts' => 'array',
            'completed_at' => 'datetime',
        ];
    }
}
