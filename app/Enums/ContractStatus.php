<?php

namespace App\Enums;

enum ContractStatus: string
{
    case Draft = 'DRAFT';
    case Active = 'ACTIVE';
    case Completed = 'COMPLETED';
    case Cancelled = 'CANCELLED';

    /** @return list<self> */
    public static function registrationChoices(?self $existing): array
    {
        return $existing === self::Draft ? self::cases() : [self::Active, self::Completed, self::Cancelled];
    }

    public function label(): string
    {
        return __("contracts.status.{$this->value}");
    }
}
