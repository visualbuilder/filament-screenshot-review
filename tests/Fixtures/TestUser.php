<?php

declare(strict_types=1);

namespace Visualbuilder\FilamentScreenshotReview\Tests\Fixtures;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;

class TestUser extends Authenticatable implements FilamentUser
{
    protected $table = 'users';

    protected $guarded = [];

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }
}
