<?php

namespace App\Enums;

enum DutyType: string
{
    case Training = 'training';
    case Schooling = 'schooling';
    case OfficialBusiness = 'official_business';
    case TemporaryDuty = 'temporary_duty';
    case Detail = 'detail';

    public function label(): string
    {
        return match ($this) {
            self::Training => 'Training',
            self::Schooling => 'Schooling',
            self::OfficialBusiness => 'Official Business',
            self::TemporaryDuty => 'Temporary Duty (TDY)',
            self::Detail => 'Detail / Other Assignment',
        };
    }

    public function category(): AccountabilityCategory
    {
        return match ($this) {
            self::Training, self::Schooling => AccountabilityCategory::Training,
            default => AccountabilityCategory::Official,
        };
    }
}
