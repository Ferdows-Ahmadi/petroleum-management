<?php

namespace App\Enums;

enum DealStatus: string
{
    case Draft = 'DRAFT';
    case InProgress = 'IN_PROGRESS';
    case Completed = 'COMPLETED';
    case Cancelled = 'CANCELLED';

    public function label(): string
    {
        return __("deals.status.{$this->value}");
    }
}
