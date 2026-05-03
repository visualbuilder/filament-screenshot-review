<?php

declare(strict_types=1);

namespace Visualbuilder\FilamentScreenshotReview\Policies;

use Illuminate\Foundation\Auth\User;
use Visualbuilder\FilamentScreenshotReview\Models\ScreenshotCapture;

class ScreenshotCapturePolicy
{
    use ChecksConfiguredPermission;

    public function viewAny(User $user): bool
    {
        return $this->allowedBy($user, 'captures.viewAny');
    }

    public function view(User $user, ScreenshotCapture $capture): bool
    {
        return $this->allowedBy($user, 'captures.viewAny');
    }

    public function approve(User $user, ScreenshotCapture $capture): bool
    {
        return $this->allowedBy($user, 'captures.review');
    }

    public function requestChanges(User $user, ScreenshotCapture $capture): bool
    {
        return $this->allowedBy($user, 'captures.review');
    }
}
