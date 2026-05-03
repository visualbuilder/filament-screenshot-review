<?php

declare(strict_types=1);

namespace Visualbuilder\FilamentScreenshotReview\Policies;

use Illuminate\Foundation\Auth\User;
use Visualbuilder\FilamentScreenshotReview\Models\ScreenshotPage;

class ScreenshotPagePolicy
{
    use ChecksConfiguredPermission;

    public function viewAny(User $user): bool
    {
        return $this->allowedBy($user, 'pages.viewAny');
    }

    public function view(User $user, ScreenshotPage $page): bool
    {
        return $this->allowedBy($user, 'pages.viewAny');
    }

    public function create(User $user): bool
    {
        return $this->allowedBy($user, 'pages.manage');
    }

    public function update(User $user, ScreenshotPage $page): bool
    {
        return $this->allowedBy($user, 'pages.manage');
    }

    public function manage(User $user): bool
    {
        return $this->allowedBy($user, 'pages.manage');
    }

    public function delete(User $user, ScreenshotPage $page): bool
    {
        return $this->allowedBy($user, 'pages.manage');
    }
}
