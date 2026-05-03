<?php

declare(strict_types=1);

namespace Visualbuilder\FilamentScreenshotReview\Policies;

use Illuminate\Foundation\Auth\User;

trait ChecksConfiguredPermission
{
    /**
     * Resolve a permission key from screenshot-review.permissions and check it
     * against the host user. When the configured value is null (no host
     * permission system), every authenticated user passes.
     */
    protected function allowedBy(User $user, string $configKey): bool
    {
        $permission = config("screenshot-review.permissions.{$configKey}");

        if ($permission === null || $permission === '') {
            return true;
        }

        return method_exists($user, 'can')
            ? $user->can($permission)
            : true;
    }
}
