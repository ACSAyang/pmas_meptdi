<?php

namespace App\Enums;

enum MovementStatus: string
{
    case Scheduled = 'scheduled';
    case Departed = 'departed';
    case Returned = 'returned';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Scheduled – Departure Not Recorded',
            self::Departed => 'Departed – Away',
            self::Returned => 'Returned – Closed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Scheduled => 'Scheduled',
            self::Departed => 'Away',
            self::Returned => 'Returned',
            self::Cancelled => 'Cancelled',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Scheduled => 'amber',
            self::Departed => 'blue',
            self::Returned => 'green',
            self::Cancelled => 'gray',
        };
    }
}
