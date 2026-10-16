<?php

namespace App\Enums;

/**
 * Primary accountability categories (BR-010: each member is in exactly one
 * category at a reporting instant).
 *
 * These are the proposed categories from the concept document. Labels may be
 * renamed to match official MEPTDI terminology without changing the codes.
 */
enum AccountabilityCategory: string
{
    case Present = 'present';
    case Leave = 'leave';
    case Pass = 'pass';
    case Sick = 'sick';
    case Training = 'training';
    case Official = 'official';
    case Overdue = 'overdue';
    case Unverified = 'unverified';
    case Unaccounted = 'unaccounted';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Present & Accounted For',
            self::Leave => 'Authorized Leave',
            self::Pass => 'Authorized Pass',
            self::Sick => 'Sick / Medical',
            self::Training => 'Training / Schooling',
            self::Official => 'Official Business / Duty',
            self::Overdue => 'Overdue Return',
            self::Unverified => 'Unverified Status',
            self::Unaccounted => 'Reported Absent / Unaccounted',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Present => 'Present',
            self::Leave => 'Leave',
            self::Pass => 'Pass',
            self::Sick => 'Sick/Medical',
            self::Training => 'Training',
            self::Official => 'Official Bus.',
            self::Overdue => 'Overdue',
            self::Unverified => 'Unverified',
            self::Unaccounted => 'Unaccounted',
        };
    }

    /** CSS tone used by badges and charts. */
    public function tone(): string
    {
        return match ($this) {
            self::Present => 'green',
            self::Leave => 'blue',
            self::Pass => 'sky',
            self::Sick => 'violet',
            self::Training => 'teal',
            self::Official => 'indigo',
            self::Overdue => 'red',
            self::Unverified => 'amber',
            self::Unaccounted => 'rose',
        };
    }

    /** "accounted" categories are confirmed; "unresolved" need human verification. */
    public function group(): string
    {
        return match ($this) {
            self::Overdue, self::Unverified, self::Unaccounted => 'unresolved',
            default => 'accounted',
        };
    }

    public function isAuthorizedAway(): bool
    {
        return in_array($this, [self::Leave, self::Pass, self::Training, self::Official], true)
            || $this === self::Sick;
    }

    public function isUnresolved(): bool
    {
        return $this->group() === 'unresolved';
    }

    /** Categories an application type or duty assignment may map to. */
    public static function awayCategories(): array
    {
        return [self::Leave, self::Pass, self::Sick, self::Training, self::Official];
    }

    public static function ordered(): array
    {
        return self::cases();
    }
}
