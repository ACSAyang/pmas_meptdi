<?php

namespace App\Services;

use App\Enums\AccountabilityCategory;
use App\Models\AccountabilityEvent;
use App\Models\Personnel;
use App\Models\PersonnelMovement;
use App\Models\Unit;
use Carbon\CarbonInterface;

class AccountabilityRow
{
    public AccountabilityCategory $category = AccountabilityCategory::Unverified;

    public string $detail = '';

    public ?CarbonInterface $since = null;

    public ?PersonnelMovement $movement = null;

    public ?AccountabilityEvent $event = null;

    public bool $conflict = false;

    public function __construct(
        public Personnel $personnel,
        public ?Unit $unit,
    ) {}

    /** Detail line safe for users without medical/sensitive access. */
    public function publicDetail(bool $canSeeSensitive): string
    {
        if ($this->category === AccountabilityCategory::Sick && ! $canSeeSensitive) {
            return 'Medical-related status (restricted)';
        }
        if ($this->movement?->application?->type?->is_sensitive && ! $canSeeSensitive) {
            return 'Restricted category';
        }

        return $this->detail;
    }

    public function toArray(bool $canSeeSensitive = false): array
    {
        return [
            'personnel_id' => $this->personnel->id,
            'service_number' => $this->personnel->service_number,
            'rank' => $this->personnel->rank?->abbreviation,
            'name' => $this->personnel->full_name,
            'unit' => $this->unit?->name,
            'unit_code' => $this->unit?->code,
            'category' => $this->category->value,
            'category_label' => $this->category->label(),
            'detail' => $this->publicDetail($canSeeSensitive),
            'since' => $this->since?->format('Y-m-d H:i'),
            'conflict' => $this->conflict,
        ];
    }
}
