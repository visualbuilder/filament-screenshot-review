<?php

declare(strict_types=1);

namespace Visualbuilder\FilamentScreenshotReview\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ScreenshotStatus: string implements HasColor, HasIcon, HasLabel
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case CHANGES_REQUESTED = 'changes_requested';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::APPROVED => 'Approved',
            self::CHANGES_REQUESTED => 'Changes requested',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PENDING => 'gray',
            self::APPROVED => 'success',
            self::CHANGES_REQUESTED => 'warning',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::PENDING => 'heroicon-o-clock',
            self::APPROVED => 'heroicon-o-check-circle',
            self::CHANGES_REQUESTED => 'heroicon-o-exclamation-triangle',
        };
    }
}
