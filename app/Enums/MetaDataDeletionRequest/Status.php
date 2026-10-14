<?php

declare(strict_types=1);

namespace App\Enums\MetaDataDeletionRequest;

enum Status: string
{
    case Completed = 'completed';
    case Failed = 'failed';
}
